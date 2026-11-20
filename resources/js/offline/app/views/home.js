import { getState } from '../../sync';
import { sampleLabel } from '../store';
import { empty, h, isReachable, pendingBadge, qty, stageBadge } from '../util';
import { progressBar } from './orders';

/** Offline dashboard: the same sections as the online one, counted from the data on this device. */
export function home(ctx) {
    const st = getState();
    const days = ctx.historyDays || 10;
    const pending = ctx.dispatches.filter((d) => d.status === 'pending');
    const confirmed = ctx.dispatches.filter((d) => d.status === 'confirmed');
    const toLoad = confirmed.filter((d) => d.loading_status !== 'completed');
    const completed = confirmed.filter((d) => d.loading_status === 'completed');
    const rejected = ctx.dispatches.filter((d) => d.status === 'rejected');

    const stat = (label, value, tone, href, cls = 'col-6 col-xl-3') => `<div class="${cls}"><a href="${href}" class="card stat-card tone-${tone}"><div class="stat-label">${h(label)}</div><div class="stat-value mono">${Number(value).toLocaleString('en-US')}</div></a></div>`;
    const tile = (href, label, icon, tone) => `<div class="col-3"><a href="${href}" class="card quick-action p-2"><span class="stat-icon tone-${tone}"><i class="bi bi-${icon}"></i></span><span class="small">${label}</span></a></div>`;
    const byDate = (field) => (a, b) => String(a[field] || '').localeCompare(String(b[field] || ''));
    const sampleText = (d) => { const s = ctx.sample(d.sample_uuid); const l = s ? sampleLabel(ctx, s) : null; return l ? `<span class="mono">${h(l.number)}</span> ${h(l.variety)}` : '—'; };

    return {
        title: 'Dashboard',
        html: `${!isReachable() ? `<div class="alert alert-info d-flex gap-2 align-items-start"><i class="bi bi-wifi-off fs-5"></i><div><strong>You're working offline.</strong> Showing the last ${days} days and all open orders saved on this device. Everything you save syncs automatically when the internet returns.</div></div>` : ''}
        <div class="page-header keep-mobile"><div><h1>Dashboard</h1><p>${new Date().toLocaleDateString('en-GB', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })} · last synced ${ctx.lastSync ? new Date(ctx.lastSync).toLocaleString([], { dateStyle: 'medium', timeStyle: 'short' }) : 'never'}</p></div>
            <div class="page-actions d-none d-md-flex"><a href="#/dispatches/new" class="btn btn-light"><i class="bi bi-send me-1"></i> Dispatch</a><a href="#/samples/new" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> Add sample</a></div></div>
        <div class="row g-2 g-md-3 mb-4 d-md-none">${tile('#/samples/new', 'Add sample', 'basket', 'navy')}${tile('#/dispatches/new', 'Dispatch', 'send', 'blue')}${tile('#/loading', 'Loading', 'truck', 'teal')}${tile('#/mills/new', 'Add mill', 'buildings', 'slate')}</div>

        ${!ctx.samples.length && !ctx.mills.length ? `<div class="empty-panel mb-4">${empty('cloud-download', 'No data on this device yet', 'Open the app once while online so your records are downloaded for offline use.')}</div>` : ''}

        <div class="small-caps mb-2">Needs attention</div>
        <div class="row g-3 mb-4">
            ${stat('Awaiting buyer response', pending.length, 'gold', '#/dispatches?status=pending')}
            ${stat('Orders to load', toLoad.length, 'blue', '#/loading')}
            ${stat('Confirmed dispatches', confirmed.length, 'green', '#/dispatches?status=confirmed')}
            ${stat('Completed loadings', completed.length, 'teal', '#/loading?tab=completed')}
        </div>

        <div class="small-caps mb-2">Records <span class="text-muted fw-normal text-lowercase">· on this device</span></div>
        <div class="row g-3 mb-4">
            ${stat('Samples', ctx.samples.length, 'navy', '#/samples', 'col-6 col-md-4 col-xl')}
            ${stat('Dispatches', ctx.dispatches.length, 'blue', '#/dispatches', 'col-6 col-md-4 col-xl')}
            ${stat('Rejected', rejected.length, 'red', '#/dispatches?status=rejected', 'col-6 col-md-4 col-xl')}
            ${stat('Rice mills', ctx.mills.length, 'slate', '#/mills', 'col-6 col-md-6 col-xl')}
            ${stat('Buyers', ctx.buyers.length, 'purple', '#/buyers', 'col-12 col-md-6 col-xl')}
        </div>

        <div class="row g-4">
            <div class="col-lg-7">
                <div class="card mb-4"><div class="card-header justify-content-between"><h2><i class="bi bi-hourglass-split text-warning me-2"></i>Waiting longest for a response</h2><a href="#/dispatches?status=pending" class="small">View all</a></div>
                    ${pending.length ? `<div class="list-group list-group-flush">${[...pending].sort(byDate('dispatch_date')).slice(0, 5).map((d) => `<div class="list-group-item d-flex justify-content-between align-items-center gap-2 py-3">
                        <a href="#/dispatches/${h(d.uuid)}" class="min-w-0 text-body"><span class="d-block fw-medium text-truncate">${h(ctx.buyer(d.buyer_uuid)?.name || '—')} ${pendingBadge(d)}</span><span class="small text-muted">${sampleText(d)}</span></a>
                        <button type="button" class="btn btn-sm btn-soft text-nowrap" data-local-respond="${h(d.uuid)}"><i class="bi bi-reply"></i> Respond</button></div>`).join('')}</div>`
                        : '<div class="card-body text-muted small"><i class="bi bi-check-circle text-success"></i> No responses pending.</div>'}</div>
                <div class="card"><div class="card-header justify-content-between"><h2><i class="bi bi-truck text-primary me-2"></i>Confirmed orders to load</h2><a href="#/loading" class="small">View all</a></div>
                    ${toLoad.length ? `<div class="list-group list-group-flush">${[...toLoad].sort(byDate('confirmation_date')).slice(0, 5).map((d) => `<div class="list-group-item py-3">
                        <div class="d-flex justify-content-between align-items-center gap-2 mb-2"><a href="#/dispatches/${h(d.uuid)}" class="min-w-0 text-body"><span class="d-block fw-medium text-truncate">${h(ctx.buyer(d.buyer_uuid)?.name || '—')} ${pendingBadge(d)}</span>
                            <span class="small text-muted">${h(qty(d.confirmed_quantity, d.confirmed_quantity_unit))} · ${sampleText(d)}</span></a>
                            <a href="#/dispatches/${h(d.uuid)}/load" class="btn btn-sm btn-primary text-nowrap"><i class="bi bi-truck"></i> Load</a></div>${progressBar(ctx, d)}</div>`).join('')}</div>`
                        : '<div class="card-body text-muted small"><i class="bi bi-check-circle text-success"></i> Nothing waiting to be loaded.</div>'}</div>
            </div>
            <div class="col-lg-5">
                <div class="card"><div class="card-header"><h2><i class="bi bi-cloud-arrow-up text-primary me-2"></i>Saved on this device</h2></div>
                    ${ctx.ops.length ? `<div class="list-group list-group-flush">${ctx.ops.slice(-8).reverse().map((o) => `<div class="list-group-item py-3 d-flex justify-content-between gap-2"><span class="small">${h(o.summary)}</span>
                        <span class="status-badge status-${o.status === 'failed' ? 'rejected' : 'pending'}">${o.status === 'failed' ? 'Not accepted' : o.status === 'conflict' ? 'Conflict' : 'Waiting'}</span></div>`).join('')}</div>
                        <div class="card-footer"><button class="btn btn-sm btn-light" data-open-sync>Open sync details</button></div>`
                        : `<div class="card-body small text-muted">${st.phase === 'synced' ? '<i class="bi bi-check-circle text-success"></i> Everything is synced with the server.' : 'No changes waiting to sync.'}</div>`}</div>
                <p class="small text-muted mt-3 mb-0"><i class="bi bi-info-circle"></i> Recent activity from other users appears when you are online.</p>
            </div>
        </div>`,
        mount(root) {
            root.querySelector('[data-open-sync]')?.addEventListener('click', () => document.querySelector('[data-sync-indicator] button')?.click());
        },
    };
}

export { stageBadge };
