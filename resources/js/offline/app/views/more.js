import { uuid } from '../../db';
import { enqueue } from '../../outbox';
import { pdfButton, recordsReport, runExport } from '../reports';
import { sampleLabel } from '../store';
import { dateRange, empty, fmtDate, h, inRange, matches, newestFirst, pageHeader, pendingBadge, qty, rate, savedToast, stageBadge } from '../util';

/** Records, varieties and the online-only settings pages — offline. */

const DATE_LABELS = { dispatches: 'Dispatch date', confirmations: 'Confirmation date', rejections: 'Rejection date', loadings: 'Loading date', completed: 'Completion date' };
const TYPES = { dispatches: 'All dispatches', confirmations: 'Confirmations', rejections: 'Rejections', loadings: 'Truck loadings', completed: 'Completed orders' };

export function recordsList(ctx, params, query) {
    const type = TYPES[query.get('type')] ? query.get('type') : 'dispatches';
    const q = query.get('q') || '';
    const from = query.get('from') || '';
    const to = query.get('to') || '';
    const days = ctx.historyDays || 10;
    const tabs = Object.entries(TYPES).map(([k, l]) => `<a href="#/records?type=${k}&from=${from}&to=${to}" class="${k === type ? 'active' : ''}">${l}</a>`).join('');
    const dateLabel = DATE_LABELS[type];

    let rows;
    let data;
    if (type === 'loadings') {
        const found = ctx.loadings
            .map((t) => ({ t, d: ctx.dispatch(t.dispatch_uuid) }))
            .filter(({ t }) => inRange(t.loading_date, from, to))
            .filter(({ t, d }) => matches(`${t.truck_number} ${t.driver_name || ''} ${d?.dispatch_number || ''} ${ctx.buyer(d?.buyer_uuid)?.name || ''}`, q))
            .sort((a, b) => String(b.t.loading_date).localeCompare(String(a.t.loading_date)));
        data = found.map(({ t }) => t);
        rows = found.map(({ t, d }) => `<a href="#/loadings/${h(t.uuid)}" class="list-group-item list-group-item-action py-3">
                <div class="d-flex justify-content-between gap-2"><span class="fw-semibold mono">${h(t.truck_number)} ${pendingBadge(t)}</span><span class="small text-muted">${fmtDate(t.loading_date)}</span></div>
                <div class="small text-muted">${h(ctx.buyer(d?.buyer_uuid)?.name || '—')} · <span class="mono">${h(d?.dispatch_number || 'New')}</span> · ${h(qty(t.quantity, t.quantity_unit))}</div></a>`);
    } else {
        const [field, keep] = {
            dispatches: ['dispatch_date', () => true],
            confirmations: ['confirmation_date', (d) => d.status === 'confirmed'],
            rejections: ['rejection_date', (d) => d.status === 'rejected'],
            completed: ['loading_completed_at', (d) => d.loading_status === 'completed'],
        }[type];
        data = ctx.dispatches
            .filter(keep)
            .filter((d) => inRange(d[field], from, to))
            .filter((d) => { const s = ctx.sample(d.sample_uuid); return matches(`${d.dispatch_number} ${ctx.buyer(d.buyer_uuid)?.name} ${s?.sample_number} ${ctx.mill(s?.mill_uuid)?.name}`, q); })
            .sort(newestFirst(field));
        rows = data.map((d) => {
                const s = ctx.sample(d.sample_uuid);
                const l = s ? sampleLabel(ctx, s) : { number: '—', variety: '', mill: '' };
                return `<a href="#/dispatches/${h(d.uuid)}" class="list-group-item list-group-item-action py-3">
                    <div class="d-flex justify-content-between gap-2"><span class="min-w-0"><span class="fw-semibold mono">${h(d.dispatch_number || 'New dispatch')}</span> ${pendingBadge(d)}
                        <span class="d-block small text-muted text-truncate">${h(ctx.buyer(d.buyer_uuid)?.name || '—')} · ${h(l.number)} ${h(l.variety)} · ${h(l.mill)}</span></span><span>${stageBadge(d)}</span></div>
                    <div class="small mt-1">${fmtDate(d[field] || d.dispatch_date)}${d.status === 'confirmed' ? ` · <strong>${h(qty(d.confirmed_quantity, d.confirmed_quantity_unit))}</strong> · ${h(rate(d))}` : ''}</div></a>`;
            });
    }

    return {
        title: 'Records',
        html: `${pageHeader('Records & History', `Last ${days} days and open orders on this device. The full history is available online.`, pdfButton)}
        <div class="card"><nav class="nav-tabs-pills" aria-label="Record type">${tabs}</nav>
            <form class="filter-bar" data-search><div class="row g-2">
                <div class="col-12 col-lg-6"><div class="search-input"><i class="bi bi-search"></i><input type="search" name="q" value="${h(q)}" class="form-control" placeholder="${type === 'loadings' ? 'Truck no., driver, dispatch, buyer' : 'Dispatch no., sample no., buyer, mill'}"></div></div>
                ${dateRange(from, to, dateLabel)}
            </div><div class="mt-2 small text-muted">${rows.length} record(s) · dates filter by <strong>${h(dateLabel.toLowerCase())}</strong></div></form>
            ${rows.length ? `<div class="list-group list-group-flush">${rows.join('')}</div>` : empty('journal-text', 'Nothing here on this device')}
        </div>`,
        keepFocus: '[name=q]',
        mount(root) {
            const form = root.querySelector('[data-search]');
            form.addEventListener('submit', (e) => e.preventDefault());
            const update = () => { history.replaceState(null, '', `#/records?type=${type}&q=${encodeURIComponent(form.q.value)}&from=${form.from.value}&to=${form.to.value}`); window.dispatchEvent(new Event('rst:rerender')); };
            form.q.addEventListener('input', update);
            form.from.addEventListener('change', update);
            form.to.addEventListener('change', update);
            root.querySelector('[data-export-pdf]').addEventListener('click', (e) => runExport(e.currentTarget, () => recordsReport(ctx, type, q, data, { from, to, dateLabel })));
        },
    };
}

export function varietiesList(ctx) {
    const count = (v) => ctx.samples.filter((s) => s.variety_uuid === v.uuid).length;
    return {
        title: 'Rice Varieties',
        html: `${pageHeader('Rice Varieties', 'Varieties used when recording samples.', '<button type="button" class="btn btn-primary" data-add-variety><i class="bi bi-plus-lg me-1"></i> Add variety</button>')}
        <div class="card">${ctx.varieties.length ? `<div class="list-group list-group-flush">${ctx.varieties.map((v) => `<div class="list-group-item py-3 d-flex justify-content-between align-items-center gap-2">
            <span class="fw-semibold">${h(v.name)} ${pendingBadge(v)}</span>
            <span class="d-flex gap-2 align-items-center"><span class="small text-muted">${count(v)} sample(s) on device</span>${v.is_active ? '<span class="status-badge status-completed">Active</span>' : '<span class="status-badge status-rejected">Inactive</span>'}</span></div>`).join('')}</div>`
            : empty('tags', 'No varieties on this device')}</div>`,
        mount(root) {
            root.querySelector('[data-add-variety]').addEventListener('click', async () => {
                const name = window.prompt('New rice variety name (e.g. 1847)')?.trim();
                if (!name) return;
                if (ctx.varieties.some((v) => v.name.toLowerCase() === name.toLowerCase())) { window.alert('This variety already exists.'); return; }
                await enqueue(ctx.db, { type: 'variety.create', payload: { uuid: uuid(), fields: { name } }, summary: `New variety ${name}` });
                savedToast('Variety');
            });
        },
    };
}

/** Pages that change accounts or passwords need the server. */
export function onlineOnly(title, icon, text) {
    return () => ({
        title,
        html: `${pageHeader(title)}<div class="card">${empty(icon, 'Available when you are online', text, '<a href="/" class="btn btn-primary"><i class="bi bi-arrow-clockwise me-1"></i> Try again</a>')}</div>`,
    });
}
