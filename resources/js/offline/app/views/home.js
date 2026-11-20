import { getState } from '../../sync';
import { sampleLabel } from '../store';
import { empty, fmtDate, h, isReachable, pendingBadge, stageBadge } from '../util';

export function home(ctx) {
    const st = getState();
    const pendingDispatches = ctx.dispatches.filter((d) => d.status === 'pending');
    const toLoad = ctx.dispatches.filter((d) => d.status === 'confirmed' && d.loading_status !== 'completed');
    const waiting = ctx.ops.length;
    const stat = (label, value, tone, href) => `<div class="col-6 col-xl-3"><a href="${href}" class="card stat-card tone-${tone}"><div class="stat-label">${h(label)}</div><div class="stat-value mono">${value}</div></a></div>`;
    const tile = (href, label, icon, tone) => `<div class="col-3"><a href="${href}" class="card quick-action p-2"><span class="stat-icon tone-${tone}"><i class="bi bi-${icon}"></i></span><span class="small">${label}</span></a></div>`;

    return {
        title: 'Dashboard',
        html: `${!isReachable() ? `<div class="alert alert-info d-flex gap-2 align-items-start"><i class="bi bi-wifi-off fs-5"></i><div><strong>You're working offline.</strong> Everything you save stays on this device and syncs automatically when the internet returns.</div></div>` : ''}
        <div class="page-header keep-mobile"><div><h1>Dashboard</h1><p>Data on this device · last synced ${ctx.lastSync ? new Date(ctx.lastSync).toLocaleString([], { dateStyle: 'medium', timeStyle: 'short' }) : 'never'}</p></div></div>
        <div class="row g-2 g-md-3 mb-4">${tile('#/samples/new', 'Add sample', 'basket', 'navy')}${tile('#/dispatches/new', 'Dispatch', 'send', 'blue')}${tile('#/loading', 'Loading', 'truck', 'teal')}${tile('#/mills/new', 'Add mill', 'buildings', 'slate')}</div>
        <div class="row g-3 mb-4">
            ${stat('Awaiting buyer response', pendingDispatches.length, 'gold', '#/dispatches?status=pending')}
            ${stat('Orders to load', toLoad.length, 'blue', '#/loading')}
            ${stat('Samples on device', ctx.samples.length, 'navy', '#/samples')}
            ${stat('Changes to sync', waiting, waiting ? 'red' : 'green', '#/sync')}
        </div>
        <div class="row g-4">
            <div class="col-lg-6"><div class="card"><div class="card-header"><h2><i class="bi bi-hourglass-split text-warning me-2"></i>Awaiting response</h2></div>
                ${pendingDispatches.length ? `<div class="list-group list-group-flush">${pendingDispatches.slice(0, 6).map((d) => {
                    const s = ctx.sample(d.sample_uuid);
                    return `<div class="list-group-item d-flex justify-content-between align-items-center gap-2 py-3"><a href="#/dispatches/${h(d.uuid)}" class="min-w-0 text-body">
                        <span class="d-block fw-medium text-truncate">${h(ctx.buyer(d.buyer_uuid)?.name || '—')} ${pendingBadge(d)}</span><span class="small text-muted">${h(s ? sampleLabel(ctx, s).number : '—')} · sent ${fmtDate(d.dispatch_date)}</span></a>
                        <a href="#/dispatches/${h(d.uuid)}/respond" class="btn btn-sm btn-soft text-nowrap"><i class="bi bi-reply"></i> Respond</a></div>`;
                }).join('')}</div>` : '<div class="card-body small text-muted">No responses pending.</div>'}</div></div>
            <div class="col-lg-6"><div class="card"><div class="card-header"><h2><i class="bi bi-cloud-arrow-up text-primary me-2"></i>Saved on this device</h2></div>
                ${ctx.ops.length ? `<div class="list-group list-group-flush">${ctx.ops.slice(-6).reverse().map((o) => `<div class="list-group-item py-3 d-flex justify-content-between gap-2"><span class="small">${h(o.summary)}</span>
                    <span class="status-badge status-${o.status === 'failed' ? 'rejected' : 'pending'}">${o.status === 'failed' ? 'Not accepted' : o.status === 'conflict' ? 'Conflict' : 'Waiting'}</span></div>`).join('')}</div>
                    <div class="card-footer"><button class="btn btn-sm btn-light" data-open-sync>Open sync details</button></div>` : `<div class="card-body small text-muted">${st.phase === 'synced' ? 'Everything is synced.' : 'No changes waiting.'}</div>`}</div></div>
        </div>
        ${!ctx.samples.length && !ctx.mills.length ? empty('cloud-download', 'No data on this device yet', 'Open the app once while online so your records are downloaded for offline use.') : ''}`,
        mount(root) {
            root.querySelector('[data-open-sync]')?.addEventListener('click', () => document.querySelector('[data-sync-indicator] button')?.click());
        },
    };
}

export { stageBadge };
