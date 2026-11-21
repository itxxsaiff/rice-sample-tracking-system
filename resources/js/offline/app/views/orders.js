import { uuid } from '../../db';
import { enqueue, enqueuePhotos } from '../../outbox';
import { enableImages, input, photoInput, photosFrom, quickAddDialog, select, showErrors, textarea, values } from '../fields';
import { Modal } from 'bootstrap';
import { isActiveDispatch, loadContext, loadingProgress, sampleLabel } from '../store';
import { badge, cfg, detail, empty, fmtDate, go, h, matches, newestFirst, pageHeader, pendingBadge, qty, rate, savedToast, stageBadge, today } from '../util';
import { saveDirectory } from './directory';

/** Dispatches, buyer responses and loading — offline. */

function dispatchRow(ctx, d, { showBuyer = true } = {}) {
    const s = ctx.sample(d.sample_uuid);
    const l = s ? sampleLabel(ctx, s) : { number: '—', variety: '', mill: '' };
    return `<div class="list-group-item py-3"><div class="d-flex justify-content-between gap-2">
        <a href="#/dispatches/${h(d.uuid)}" class="min-w-0 text-body"><span class="fw-semibold mono">${h(d.dispatch_number || 'New dispatch')}</span> ${pendingBadge(d)}
            <span class="d-block small text-muted text-truncate">${showBuyer ? `${h(ctx.buyer(d.buyer_uuid)?.name || '—')} · ` : ''}${h(l.number)} ${h(l.variety)} · ${h(l.mill)}</span></a>
        <span>${stageBadge(d)}</span></div>
        <div class="d-flex justify-content-between align-items-center gap-2 mt-1"><span class="small">${fmtDate(d.dispatch_date)}${d.status === 'confirmed' ? ` · <strong>${h(qty(d.confirmed_quantity, d.confirmed_quantity_unit))}</strong> · ${h(rate(d))}` : ''}</span>
        ${d.status === 'pending' ? `<button type="button" class="btn btn-sm btn-soft text-nowrap" data-local-respond="${h(d.uuid)}"><i class="bi bi-reply"></i> Record response</button>` : ''}</div></div>`;
}

export function dispatchList(ctx, params, query) {
    const status = query.get('status') || '';
    const q = query.get('q') || '';
    const filtered = ctx.dispatches.filter((d) => {
        const s = ctx.sample(d.sample_uuid);
        return matches(`${d.dispatch_number} ${ctx.buyer(d.buyer_uuid)?.name} ${s?.sample_number} ${ctx.mill(s?.mill_uuid)?.name}`, q);
    });
    const rows = filtered.filter((d) => !status || d.status === status).sort(newestFirst('dispatch_date'));
    const count = (st) => filtered.filter((d) => !st || d.status === st).length;
    const tab = (st, label) => `<a href="#/dispatches?status=${st}&q=${encodeURIComponent(q)}" class="${status === st ? 'active' : ''}">${label}<span class="count">${count(st)}</span></a>`;

    return {
        title: 'Dispatches',
        html: `${pageHeader('Sample Dispatches', 'Each buyer\'s answer is tracked separately.', '<a href="#/dispatches/new" class="btn btn-primary"><i class="bi bi-send me-1"></i> Dispatch samples</a>')}
        <div class="card">
            <nav class="nav-tabs-pills">${tab('', 'All')}${tab('pending', 'Pending')}${tab('confirmed', 'Confirmed')}${tab('rejected', 'Rejected')}</nav>
            <form class="filter-bar" data-search><div class="search-input"><i class="bi bi-search"></i><input type="search" name="q" value="${h(q)}" class="form-control" placeholder="Dispatch no., sample no., buyer, mill"></div></form>
            ${rows.length ? `<div class="list-group list-group-flush">${rows.map((d) => dispatchRow(ctx, d)).join('')}</div>` : empty('send', 'No dispatches here', 'Send samples to one or more buyers.', '<a href="#/dispatches/new" class="btn btn-primary">Dispatch samples</a>')}
        </div>`,
        keepFocus: '[name=q]',
        mount(root) {
            const form = root.querySelector('[data-search]');
            form.addEventListener('submit', (e) => e.preventDefault());
            form.q.addEventListener('input', () => { history.replaceState(null, '', `#/dispatches?status=${status}&q=${encodeURIComponent(form.q.value)}`); window.dispatchEvent(new Event('rst:rerender')); });
        },
    };
}

export function dispatchNew(ctx, params, query) {
    const preSamples = new Set([query.get('sample'), ...(query.get('samples') || '').split(',')].filter(Boolean));
    const preBuyers = new Set([query.get('buyer')].filter(Boolean));
    const samples = [...ctx.samples].sort(newestFirst('collection_date'));

    const sampleItem = (s) => {
        const l = sampleLabel(ctx, s);
        return `<label class="choice-item"><input class="form-check-input" type="checkbox" name="samples[]" value="${h(s.uuid)}" ${preSamples.has(s.uuid) ? 'checked' : ''}>
            <span class="min-w-0"><span class="d-block fw-medium text-truncate"><span class="mono">${h(l.number)}</span> · ${h(l.variety)} ${pendingBadge(s)}</span><span class="small text-muted">${h(l.mill)}, ${h(l.city)} · ${fmtDate(s.collection_date)}</span></span></label>`;
    };
    const buyerItem = (b, checked = false) => `<label class="choice-item"><input class="form-check-input" type="checkbox" name="buyers[]" value="${h(b.uuid)}" ${checked || preBuyers.has(b.uuid) ? 'checked' : ''}>
        <span class="min-w-0"><span class="d-block fw-medium text-truncate">${h(b.name)} ${pendingBadge(b)}</span><span class="small text-muted">${h(b.city)}</span></span></label>`;

    return {
        title: 'Dispatch Samples',
        back: '#/dispatches',
        isForm: true,
        actionBar: true,
        html: `${pageHeader('Dispatch Samples', 'A separate dispatch is recorded for every sample–buyer pair.')}
        <form novalidate data-form><div class="row g-4">
            <div class="col-lg-6"><div class="card h-100"><div class="card-header"><h2><i class="bi bi-basket text-primary me-2"></i>Samples <span class="badge rounded-pill text-bg-light border ms-1" data-choice-count="samples">0</span></h2></div>
                <div class="card-body"><div class="search-input mb-2"><i class="bi bi-search"></i><input type="search" class="form-control" placeholder="Filter samples" data-filter="#sampleList" aria-label="Filter samples"></div>
                <div id="sampleList" class="choice-list" data-choice-group="samples">${samples.map(sampleItem).join('') || '<div class="picker-empty">No samples on this device.</div>'}</div>
                <div class="invalid-feedback d-block" data-error-for="samples"></div></div></div></div>
            <div class="col-lg-6"><div class="card h-100"><div class="card-header justify-content-between"><h2><i class="bi bi-people text-primary me-2"></i>Buyers <span class="badge rounded-pill text-bg-light border ms-1" data-choice-count="buyers">0</span></h2>
                <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none" data-new-buyer><i class="bi bi-plus-circle"></i> New buyer</button></div>
                <div class="card-body"><div class="search-input mb-2"><i class="bi bi-search"></i><input type="search" class="form-control" placeholder="Filter buyers" data-filter="#buyerList" aria-label="Filter buyers"></div>
                <div id="buyerList" class="choice-list" data-choice-group="buyers">${ctx.buyers.map((b) => buyerItem(b)).join('')}</div>
                <div class="invalid-feedback d-block" data-error-for="buyers"></div></div></div></div>
            <div class="col-12"><div class="card"><div class="card-body"><div class="row">
                ${input('dispatch_date', 'Dispatch date', { type: 'date', value: today(), required: true, cls: 'col-md-4 mb-3', attrs: `max="${today()}"` })}
                ${input('notes', 'Notes', { cls: 'col-md-8 mb-3', placeholder: 'Courier, tracking no., who carried it…' })}
            </div><div class="alert alert-secondary mb-0 d-flex align-items-center gap-2" data-summary><i class="bi bi-info-circle"></i><span>Select at least one sample and one buyer</span></div></div></div></div>
        </div>
        <div class="action-bar action-bar-float"><a href="#/dispatches" class="btn btn-light">Cancel</a><button class="btn btn-primary"><i class="bi bi-send"></i> Record dispatch</button></div></form>`,
        mount(root) {
            const form = root.querySelector('[data-form]');
            const refresh = () => {
                const s = form.querySelectorAll('[name="samples[]"]:checked').length;
                const b = form.querySelectorAll('[name="buyers[]"]:checked').length;
                form.querySelectorAll('.choice-item').forEach((el) => el.classList.toggle('checked', el.querySelector('input').checked));
                form.querySelector('[data-choice-count="samples"]').textContent = s;
                form.querySelector('[data-choice-count="buyers"]').textContent = b;
                const summary = form.querySelector('[data-summary]');
                summary.querySelector('span').textContent = s && b ? `${s} sample${s > 1 ? 's' : ''} × ${b} buyer${b > 1 ? 's' : ''} = ${s * b} dispatch record${s * b > 1 ? 's' : ''}` : 'Select at least one sample and one buyer';
                summary.classList.toggle('alert-success', s * b > 0);
                summary.classList.toggle('alert-secondary', !(s * b));
            };
            form.addEventListener('change', refresh);
            form.querySelectorAll('[data-filter]').forEach((inp) => inp.addEventListener('input', () => {
                form.querySelectorAll(`${inp.dataset.filter} .choice-item`).forEach((el) => el.classList.toggle('d-none', !matches(el.textContent, inp.value)));
            }));
            form.querySelector('[data-new-buyer]').addEventListener('click', async () => {
                const item = await quickAddDialog('buyer', (data) => saveDirectory(ctx, 'buyer', data));
                if (!item) return;
                const wrap = document.createElement('div');
                wrap.innerHTML = buyerItem({ uuid: item.uuid, name: item.label, city: item.sub, _pending: true }, true);
                form.querySelector('#buyerList').prepend(wrap.firstElementChild);
                savedToast('Buyer');
                refresh();
            });
            refresh();

            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                const data = values(form);
                const sampleUuids = data.samples || [];
                const buyerUuids = data.buyers || [];
                const errors = {};
                if (!sampleUuids.length) errors.samples = 'Choose at least one sample to send.';
                if (!buyerUuids.length) errors.buyers = 'Choose at least one buyer.';
                if (!data.dispatch_date) errors.dispatch_date = 'The dispatch date is required.';
                else if (data.dispatch_date > today()) errors.dispatch_date = 'The dispatch date cannot be in the future.';
                const early = sampleUuids.map((u) => ctx.sample(u)).filter((s) => s && s.collection_date > data.dispatch_date);
                if (early.length) errors.dispatch_date = `The dispatch date is before the collection date of ${early.map((s) => s.sample_number || 'a new sample').join(', ')}.`;
                const dupes = [];
                for (const su of sampleUuids) for (const bu of buyerUuids) {
                    if (ctx.dispatches.some((d) => d.sample_uuid === su && d.buyer_uuid === bu && isActiveDispatch(d))) {
                        dupes.push(`${ctx.sample(su)?.sample_number || 'new sample'} → ${ctx.buyer(bu)?.name || 'buyer'}`);
                    }
                }
                if (dupes.length) errors.buyers = `Already sent and awaiting a response or confirmed: ${dupes.join(', ')}.`;
                if (showErrors(form, errors)) return;

                const pairs = [];
                for (const su of sampleUuids) for (const bu of buyerUuids) pairs.push({ uuid: uuid(), sample: su, buyer: bu });
                await enqueue(ctx.db, {
                    type: 'dispatch.create',
                    payload: { fields: { dispatch_date: data.dispatch_date, notes: data.notes || null }, pairs },
                    summary: `${pairs.length} dispatch${pairs.length > 1 ? 'es' : ''} to ${buyerUuids.map((b) => ctx.buyer(b)?.name).join(', ')}`,
                });
                savedToast(pairs.length > 1 ? `${pairs.length} dispatches` : 'Dispatch');
                go(pairs.length === 1 ? `#/dispatches/${pairs[0].uuid}` : '#/dispatches?status=pending');
            });
        },
    };
}

function sampleSummary(ctx, d) {
    const s = ctx.sample(d.sample_uuid);
    const l = s ? sampleLabel(ctx, s) : { number: '—', variety: '', mill: '', city: '' };
    return `<div class="d-flex gap-3 align-items-center"><span class="sample-thumb d-inline-flex align-items-center justify-content-center text-muted" style="width:56px;height:56px"><i class="bi bi-basket fs-5"></i></span>
        <div class="min-w-0"><a href="#/samples/${h(d.sample_uuid)}" class="fw-semibold mono">${h(l.number)}</a> <span class="badge text-bg-light border ms-1">${h(l.variety)}</span>
        <div class="small text-muted text-truncate">${h(l.mill)}, ${h(l.city)}</div>
        <div class="small">To <a href="#/buyers/${h(d.buyer_uuid)}" class="fw-medium">${h(ctx.buyer(d.buyer_uuid)?.name || '—')}</a> · sent ${fmtDate(d.dispatch_date)}</div></div></div>`;
}

export function progressBar(ctx, d) {
    const { totals, percent } = loadingProgress(ctx, d);
    const text = Object.entries(totals).map(([u, q]) => qty(q, u)).join(' + ') || 'Nothing';
    return `<div class="d-flex justify-content-between small mb-1"><span><strong>${h(text)}</strong> loaded</span><span class="text-muted">of ${h(qty(d.confirmed_quantity, d.confirmed_quantity_unit))}</span></div>
        ${percent !== null ? `<div class="progress" style="height:8px"><div class="progress-bar" style="width:${percent}%;background-color:var(--brand)"></div></div>` : ''}`;
}

export function dispatchShow(ctx, { id }) {
    const d = ctx.dispatch(id);
    if (!d) return { title: 'Dispatch', back: '#/dispatches', html: empty('send', 'Not on this device') };
    const trucks = ctx.loadingsOf(d.uuid);
    const siblings = ctx.dispatchesOf(d.sample_uuid).filter((x) => x.uuid !== d.uuid);
    const canLoad = d.status === 'confirmed' && d.loading_status !== 'completed';
    const canComplete = canLoad && trucks.length > 0 && trucks.every((t) => t.status === 'completed');

    let response;
    if (d.status === 'confirmed') {
        response = `<div class="card mb-4"><div class="card-header"><h2><i class="bi bi-clipboard-check text-success me-2"></i>Confirmed order</h2></div><div class="card-body"><dl class="detail-list">
            ${detail('Quantity', h(qty(d.confirmed_quantity, d.confirmed_quantity_unit)))}${detail('Agreed rate', h(rate(d)))}${detail('Packing', h(d.packing_type || '—'))}${detail('Confirmed on', fmtDate(d.confirmation_date))}
            ${d.packing_details ? detail('Packing details', h(d.packing_details), true) : ''}</dl></div></div>
            <div class="card mb-4"><div class="card-header justify-content-between flex-wrap"><h2><i class="bi bi-truck text-primary me-2"></i>Loading ${stageBadge(d)}</h2>
                <div class="d-flex gap-2">${canLoad ? `<a href="#/dispatches/${h(d.uuid)}/load" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i> ${trucks.length ? 'Add truck' : 'Record loading'}</a>` : ''}
                ${canComplete ? '<button class="btn btn-sm btn-soft" data-complete><i class="bi bi-check2-all"></i> Complete order</button>' : ''}</div></div>
                <div class="card-body">${progressBar(ctx, d)}</div>
                ${trucks.length ? `<div class="list-group list-group-flush">${trucks.map((t) => `<a href="#/loadings/${h(t.uuid)}" class="list-group-item list-group-item-action py-3 d-flex justify-content-between align-items-center gap-3">
                    <span class="min-w-0"><span class="d-block fw-semibold mono">${h(t.truck_number)} ${pendingBadge(t)}</span><span class="small text-muted">${fmtDate(t.loading_date)} · ${h(qty(t.quantity, t.quantity_unit))}${t.bag_count ? ` · ${num(t.bag_count)} bags` : ''} · <i class="bi bi-camera"></i> ${(t.photos_count || 0) + ctx.photosOf(t.uuid).length}</span></span>
                    ${badge(t.status === 'completed' ? 'completed' : 'in_progress', t.status === 'completed' ? 'Loaded' : 'Loading')}</a>`).join('')}</div>` : '<div class="card-body pt-0"><p class="text-muted small mb-0">No trucks recorded yet.</p></div>'}
            </div>`;
    } else if (d.status === 'rejected') {
        response = `<div class="card mb-4"><div class="card-header"><h2><i class="bi bi-x-octagon text-danger me-2"></i>Rejected</h2></div><div class="card-body"><dl class="detail-list">
            ${detail('Rejected on', fmtDate(d.rejection_date))}</dl>
            <p class="small text-muted mb-0 mt-3"><i class="bi bi-info-circle"></i> Rejected dispatches stay in the buyer's history and do not enter loading.</p></div></div>`;
    } else {
        response = `<div class="card mb-4">${empty('hourglass-split', `Awaiting ${ctx.buyer(d.buyer_uuid)?.name || 'buyer'}'s response`, 'Record whether the buyer confirmed or rejected the sample.', `<button type="button" class="btn btn-primary" data-local-respond="${h(d.uuid)}"><i class="bi bi-reply"></i> Record response</button>`)}</div>`;
    }

    return {
        title: d.dispatch_number || 'New dispatch',
        back: '#/dispatches',
        html: `<div class="row g-4"><div class="col-lg-8">
            <div class="card mb-4"><div class="card-body"><div class="d-flex gap-3 align-items-center mb-3"><span class="stat-icon tone-blue"><i class="bi bi-send"></i></span>
                <div><div class="small-caps">Dispatch</div><h1 class="h4 fw-bold mb-1 mono">${h(d.dispatch_number || 'New dispatch')}</h1>${stageBadge(d)} ${pendingBadge(d)}</div></div>
                ${sampleSummary(ctx, d)}${d.notes ? `<div class="small mt-3"><span class="text-muted">Notes:</span> ${h(d.notes)}</div>` : ''}</div></div>
            ${response}</div>
            <div class="col-lg-4">${siblings.length ? `<div class="card"><div class="card-header"><h2>Same sample, other buyers</h2></div><div class="list-group list-group-flush">
                ${siblings.map((x) => `<a href="#/dispatches/${h(x.uuid)}" class="list-group-item list-group-item-action py-3 d-flex justify-content-between gap-2"><span class="fw-medium text-truncate">${h(ctx.buyer(x.buyer_uuid)?.name || '—')}</span>${stageBadge(x)}</a>`).join('')}
            </div></div>` : ''}</div></div>`,
        mount(root) {
            root.querySelector('[data-complete]')?.addEventListener('click', async () => {
                if (!window.confirm(`Mark this order as fully loaded and completed? (${trucks.length} truck${trucks.length > 1 ? 's' : ''})`)) return;
                await enqueue(ctx.db, { type: 'order.complete', payload: { target: d.uuid }, summary: `Complete order for ${ctx.buyer(d.buyer_uuid)?.name || 'buyer'}` });
                savedToast('Order completion');
                window.dispatchEvent(new Event('rst:rerender'));
            });
        },
    };
}

const num = (v) => Number(v).toLocaleString('en-US');

/**
 * "Record buyer response" popup (same as online). Buttons: data-local-respond="<dispatch uuid>".
 * Offline only a first response can be recorded; changing one needs the internet.
 */
export function openRespondDialog(ctx, d) {
    if (d.status !== 'pending') {
        window.alert('A response is already recorded. To change it, open this dispatch when you are online.');
        return;
    }
    const buyer = ctx.buyer(d.buyer_uuid)?.name || 'the buyer';
    const s = ctx.sample(d.sample_uuid);
    const l = s ? sampleLabel(ctx, s) : { number: '—', variety: '', mill: '', city: '' };
    const option = (value, icon, tone, title, hint) => `<div class="col-6"><label class="card response-option p-3 h-100 mb-0" style="cursor:pointer">
        <input type="radio" name="status" value="${value}" class="visually-hidden"><span class="stat-icon tone-${tone} mb-2"><i class="bi bi-${icon}"></i></span>
        <span class="fw-semibold">${title}</span><span class="small text-muted">${hint}</span></label></div>`;

    const el = document.createElement('div');
    el.className = 'modal fade';
    el.tabIndex = -1;
    el.innerHTML = `<div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg"><form class="modal-content" novalidate>
        <div class="modal-header"><div class="min-w-0"><h5 class="modal-title fw-semibold">Record buyer response</h5>
            <div class="small text-muted text-truncate">Dispatch ${h(d.dispatch_number || '(new)')} · sent to ${h(buyer)} on ${fmtDate(d.dispatch_date)}</div></div>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
        <div class="modal-body">
            <div class="d-flex gap-3 align-items-center mb-3"><span class="stat-icon tone-navy flex-shrink-0"><i class="bi bi-basket"></i></span>
                <div class="min-w-0 small"><div class="fw-semibold mono">${h(l.number)} · ${h(l.variety)}</div><div class="text-muted text-truncate">${h(l.mill)}${l.city ? `, ${h(l.city)}` : ''}</div></div></div>
            <div class="form-section-title"><i class="bi bi-reply"></i> What did ${h(buyer)} decide?</div>
            <div class="row g-2">${option('confirmed', 'check-circle-fill', 'green', 'Confirmed', 'Buyer wants to purchase')}${option('rejected', 'x-circle-fill', 'red', 'Rejected', 'Buyer declined the sample')}</div>
            <div class="invalid-feedback d-block" data-error-for="status"></div>
            <div class="mt-4 d-none" data-section="confirmed"><div class="form-section-title"><i class="bi bi-clipboard-check"></i> Confirmed order</div><div class="row">
                ${input('confirmed_quantity', 'Quantity (maund)', { type: 'number', required: true, cls: 'col-md-6 mb-3', attrs: 'step="0.01" min="0" inputmode="decimal"', placeholder: 'e.g. 500' })}
                ${input('rate', 'Rate per maund', { type: 'number', required: true, cls: 'col-md-6 mb-3', attrs: 'step="0.01" min="0" inputmode="decimal"', prefix: 'Rs' })}
                ${select('packing_type', 'Packing (bag size)', cfg().packing_types || {}, { placeholder: 'Select bag size', required: true, cls: 'col-md-6 mb-3' })}
                ${input('confirmation_date', 'Confirmation date', { type: 'date', value: today(), required: true, cls: 'col-md-6 mb-3', attrs: `max="${today()}" min="${h(d.dispatch_date)}"` })}
                ${textarea('packing_details', 'Packing details / notes', { cls: 'col-12 mb-0', placeholder: 'e.g. 50 kg PP bags, buyer\'s printed bags' })}
            </div></div>
            <div class="mt-3 d-none" data-section="rejected"><div class="alert alert-light border mb-0 small"><i class="bi bi-info-circle me-1"></i> The dispatch will be marked as rejected. It stays in the buyer's history and does not go to loading.</div></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary"><i class="bi bi-check-lg"></i> Save response</button></div>
    </form></div>`;
    document.body.appendChild(el);
    const modal = new Modal(el);
    const form = el.querySelector('form');
    let shown = false;
    const close = () => (shown ? modal.hide() : el.addEventListener('shown.bs.modal', () => modal.hide(), { once: true }));

    const sync = () => {
        const st = form.querySelector('[name=status]:checked')?.value;
        form.querySelectorAll('[data-section]').forEach((sec) => sec.classList.toggle('d-none', sec.dataset.section !== st));
        form.querySelectorAll('.response-option').forEach((o) => o.classList.toggle('selected', o.querySelector('input').checked));
    };
    form.addEventListener('change', (e) => { if (e.target.name === 'status') sync(); });

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const button = form.querySelector('.btn-primary');
        if (button.disabled) return;
        const v = values(form);
        const errors = {};
        let fields;
        if (v.status === 'confirmed') {
            if (!(Number(v.confirmed_quantity) > 0)) errors.confirmed_quantity = 'Enter the confirmed quantity.';
            if (!(Number(v.rate) > 0)) errors.rate = 'Enter the agreed rate.';
            if (!v.packing_type) errors.packing_type = 'Choose the bag size.';
            if (!v.confirmation_date) errors.confirmation_date = 'The confirmation date is required.';
            else if (v.confirmation_date > today()) errors.confirmation_date = 'The confirmation date cannot be in the future.';
            else if (v.confirmation_date < d.dispatch_date) errors.confirmation_date = 'The confirmation date cannot be before the dispatch date.';
            fields = { status: 'confirmed', confirmed_quantity: Number(v.confirmed_quantity), confirmed_quantity_unit: 'maund', rate: Number(v.rate), rate_unit: 'per_maund', packing_type: v.packing_type, packing_details: v.packing_details || null, confirmation_date: v.confirmation_date };
        } else if (v.status === 'rejected') {
            fields = { status: 'rejected', rejection_date: today() < d.dispatch_date ? d.dispatch_date : today() };
        } else {
            errors.status = 'Choose whether the buyer confirmed or rejected the sample.';
        }
        if (showErrors(form, errors)) return;

        button.disabled = true;
        try {
            await enqueue(ctx.db, {
                type: 'dispatch.respond', payload: { target: d.uuid, base_version: d.version || 1, fields },
                summary: `${fields.status === 'confirmed' ? 'Confirmation' : 'Rejection'} from ${buyer}`,
            });
            savedToast(fields.status === 'confirmed' ? 'Confirmation' : 'Rejection');
            close();
        } finally {
            button.disabled = false;
        }
    });
    el.addEventListener('shown.bs.modal', () => { shown = true; });
    el.addEventListener('hidden.bs.modal', () => el.remove());
    modal.show();
}

/** Delegated handler for every "Record response" button in the offline screens. */
export function initRespondButtons() {
    document.addEventListener('click', async (e) => {
        const btn = e.target.closest('[data-local-respond]');
        if (!btn) return;
        e.preventDefault();
        const ctx = await loadContext();
        const d = ctx.dispatch(btn.dataset.localRespond);
        if (d) openRespondDialog(ctx, d);
    });
}

/** Old "#/dispatches/:id/respond" links: show the dispatch with the popup open. */
export function respondRoute(ctx, params) {
    const view = dispatchShow(ctx, params);
    const d = ctx.dispatch(params.id);
    return {
        ...view,
        mount(root) {
            view.mount?.(root);
            history.replaceState(null, '', `#/dispatches/${params.id}`);
            if (d?.status === 'pending') openRespondDialog(ctx, d);
        },
    };
}

export function loadingIndex(ctx, params, query) {
    const tab = ['pending', 'in_progress', 'completed'].includes(query.get('tab')) ? query.get('tab') : 'pending';
    const confirmed = ctx.dispatches.filter((d) => d.status === 'confirmed');
    const rows = confirmed.filter((d) => (d.loading_status || 'pending') === tab).sort(newestFirst('confirmation_date'));
    const count = (t) => confirmed.filter((d) => (d.loading_status || 'pending') === t).length;

    return {
        title: 'Loading',
        html: `${pageHeader('Loading', 'Confirmed orders on this device.')}
        <div class="card"><nav class="nav-tabs-pills">${[['pending', 'Pending'], ['in_progress', 'In progress'], ['completed', 'Completed']].map(([k, l]) => `<a href="#/loading?tab=${k}" class="${tab === k ? 'active' : ''}">${l}<span class="count">${count(k)}</span></a>`).join('')}</nav>
        ${rows.length ? `<div class="list-group list-group-flush">${rows.map((d) => {
            const s = ctx.sample(d.sample_uuid);
            const l = s ? sampleLabel(ctx, s) : { number: '—', variety: '', mill: '' };
            const trucks = ctx.loadingsOf(d.uuid).length;
            return `<div class="list-group-item py-3"><div class="row g-3 align-items-center">
                <div class="col-md-5"><a href="#/dispatches/${h(d.uuid)}" class="fw-semibold">${h(ctx.buyer(d.buyer_uuid)?.name || '—')}</a> ${pendingBadge(d)}
                    <div class="small text-muted text-truncate"><span class="mono">${h(d.dispatch_number || 'New')}</span> · ${h(l.number)} ${h(l.variety)} · ${h(l.mill)}</div>
                    <div class="small mt-1"><span class="fw-medium">${h(qty(d.confirmed_quantity, d.confirmed_quantity_unit))}</span> · ${h(rate(d))} · ${h(d.packing_type || '')}</div></div>
                <div class="col-md-4">${progressBar(ctx, d)}<div class="small text-muted mt-1">${trucks} truck(s)</div></div>
                <div class="col-md-3 text-md-end d-grid d-md-block">${d.loading_status !== 'completed' ? `<a href="#/dispatches/${h(d.uuid)}/load" class="btn btn-primary"><i class="bi bi-truck"></i> ${trucks ? 'Add truck' : 'Record loading'}</a>` : `<a href="#/dispatches/${h(d.uuid)}" class="btn btn-light">View order</a>`}</div>
            </div></div>`;
        }).join('')}</div>` : empty('truck', 'Nothing here', 'Confirmed orders appear here automatically.')}</div>`,
    };
}

export function loadingNew(ctx, { id }) {
    const d = ctx.dispatch(id);
    if (!d) return { title: 'Record Loading', back: '#/loading', html: empty('truck', 'Not on this device') };
    if (d.status !== 'confirmed' || d.loading_status === 'completed') {
        return { title: 'Record Loading', back: `#/dispatches/${d.uuid}`, html: empty('truck', d.status === 'confirmed' ? 'This order is already completed' : 'Only confirmed orders can be loaded', 'Rejected or pending dispatches do not enter loading.') };
    }
    const { remaining } = loadingProgress(ctx, d);

    return {
        title: 'Record Loading',
        back: `#/dispatches/${d.uuid}`,
        isForm: true,
        actionBar: true,
        html: `${pageHeader('Record Truck Loading', `${ctx.buyer(d.buyer_uuid)?.name || ''} · ${d.dispatch_number || 'New dispatch'}`)}
        <form class="card" novalidate data-form>
            <div class="form-section">${sampleSummary(ctx, d)}<div class="small mt-2">Confirmed: <strong>${h(qty(d.confirmed_quantity, d.confirmed_quantity_unit))}</strong> · ${h(d.packing_type || '')}</div></div>
            <div class="form-section"><div class="form-section-title"><i class="bi bi-truck"></i> Truck</div><div class="row">
                ${input('truck_number', 'Truck number', { required: true, cls: 'col-6 mb-3', placeholder: 'e.g. TLE-1234', attrs: 'autocapitalize="characters" autocomplete="off"' })}
                ${input('loading_date', 'Loading date', { type: 'date', value: today(), required: true, cls: 'col-6 mb-3', attrs: `max="${today()}"` })}
                ${input('driver_name', 'Driver name', { cls: 'col-6 mb-3', attrs: 'autocapitalize="words"' })}
                ${input('driver_phone', 'Driver phone', { type: 'tel', cls: 'col-6 mb-3', attrs: 'inputmode="tel"' })}
            </div></div>
            <div class="form-section"><div class="form-section-title"><i class="bi bi-box-seam"></i> Quantity &amp; packing</div><div class="row">
                ${input('quantity', 'Loaded quantity', { type: 'number', value: remaining || '', required: true, cls: 'col-md-4 mb-3', attrs: 'step="0.01" min="0" inputmode="decimal"', suffix: cfg().quantity_units?.[d.confirmed_quantity_unit || 'maund'] || 'Maund', hint: 'When the full order quantity is loaded, the order is completed automatically.' })}
                ${input('bag_count', 'No. of bags', { type: 'number', cls: 'col-6 col-md-4 mb-3', attrs: 'min="0" step="1" inputmode="numeric"' })}
                ${select('packing_type', 'Packing (bag size)', cfg().packing_types || {}, { value: d.packing_type || '', placeholder: 'Select', cls: 'col-6 col-md-4 mb-3' })}
                ${textarea('packing_details', 'Packing details', { cls: 'col-md-6 mb-3', placeholder: 'Bag size, marking, stitching…' })}
                ${textarea('notes', 'Loading notes', { cls: 'col-md-6 mb-0' })}
            </div></div>
            <div class="form-section"><div class="form-section-title"><i class="bi bi-camera"></i> Loading photos</div>${photoInput('photos', { multiple: true })}
                <div class="form-text">Truck, number plate, bags and loading slip. Photos are kept on this phone until they upload.</div></div>
            <div class="action-bar"><a href="#/dispatches/${h(d.uuid)}" class="btn btn-light">Cancel</a><button class="btn btn-primary"><i class="bi bi-check-lg"></i> Save truck</button></div>
        </form>`,
        mount(root) {
            const form = root.querySelector('[data-form]');
            enableImages(form);
            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                const v = values(form);
                const truck = (v.truck_number || '').toUpperCase().replace(/\s+/g, ' ');
                const errors = {};
                if (!truck) errors.truck_number = 'The truck number is required.';
                else if (!/^[A-Z0-9][A-Z0-9 \-/]*$/.test(truck)) errors.truck_number = 'Use letters, numbers, spaces and dashes only (e.g. TLE-1234).';
                if (!v.loading_date) errors.loading_date = 'The loading date is required.';
                else if (v.loading_date > today()) errors.loading_date = 'The loading date cannot be in the future.';
                else if (d.confirmation_date && v.loading_date < d.confirmation_date) errors.loading_date = 'The loading date cannot be before the order was confirmed.';
                if (!(Number(v.quantity) > 0)) errors.quantity = 'Enter the loaded quantity.';
                if (showErrors(form, errors)) return;

                const id2 = uuid();
                const photos = photosFrom(form, 'photos').map((p) => ({ uuid: uuid(), blob: p, name: p.name, target_type: 'loading', target: id2 }));
                await enqueue(ctx.db, {
                    type: 'loading.create',
                    payload: { uuid: id2, target: d.uuid, fields: {
                        truck_number: truck, loading_date: v.loading_date, quantity: Number(v.quantity),
                        bag_count: v.bag_count ? Number(v.bag_count) : null, packing_type: v.packing_type || null, packing_details: v.packing_details || null,
                        driver_name: v.driver_name || null, driver_phone: v.driver_phone || null, notes: v.notes || null, action: 'loaded',
                    } },
                    summary: `Truck ${truck} for ${ctx.buyer(d.buyer_uuid)?.name || 'order'}`,
                    photos,
                });
                savedToast(`Truck ${truck}`);
                go(`#/dispatches/${d.uuid}`);
            });
        },
    };
}

export function loadingShow(ctx, { id }) {
    const t = ctx.loading(id);
    if (!t) return { title: 'Truck', back: '#/loading', html: empty('truck', 'Not on this device') };
    const d = ctx.dispatch(t.dispatch_uuid);
    const local = ctx.photosOf(t.uuid);
    const orderOpen = d && d.loading_status !== 'completed';

    return {
        title: t.truck_number,
        back: d ? `#/dispatches/${d.uuid}` : '#/loading',
        html: `<div class="card mb-4"><div class="card-body">
            <div class="d-flex flex-wrap gap-3 justify-content-between align-items-start">
                <div class="d-flex gap-3 align-items-center"><span class="stat-icon tone-teal"><i class="bi bi-truck"></i></span>
                    <div><div class="small-caps">Truck loading · <span class="mono">${h(t.loading_number || 'new')}</span></div><h1 class="h4 fw-bold mb-1 mono">${h(t.truck_number)}</h1>
                    ${badge(t.status === 'completed' ? 'completed' : 'in_progress', t.status === 'completed' ? 'Loaded' : 'Loading')} ${pendingBadge(t)}</div></div>
                <div class="page-actions">${t.status !== 'completed' ? '<button class="btn btn-primary" data-loaded><i class="bi bi-check2-all"></i> Mark loaded</button>' : ''}</div>
            </div><hr>
            ${d ? sampleSummary(ctx, d) : ''}<hr>
            <dl class="detail-list">
                ${detail('Loading date', fmtDate(t.loading_date))}${detail('Quantity', h(qty(t.quantity, t.quantity_unit)))}
                ${detail('Bags', t.bag_count != null ? h(num(t.bag_count)) : '—')}${detail('Packing', h(t.packing_type || '—'))}
                ${detail('Responsible person', h(t.receiver_name || '—'))}${detail('Driver', `${h(t.driver_name || '—')}${t.driver_phone ? `<a href="tel:${h(t.driver_phone)}" class="d-block small"><i class="bi bi-telephone"></i> ${h(t.driver_phone)}</a>` : ''}`)}
                ${t.packing_details ? detail('Packing details', h(t.packing_details), true) : ''}${t.notes ? detail('Notes', h(t.notes), true) : ''}
            </dl></div></div>
        <div class="card"><div class="card-header"><h2><i class="bi bi-images text-primary me-2"></i>Photos</h2></div><div class="card-body">
            ${local.length ? `<div class="photo-grid mb-2">${local.map((p) => `<div class="photo-tile"><img data-blob="${h(p.uuid)}" alt="Loading photo"></div>`).join('')}</div><p class="small text-muted"><i class="bi bi-cloud-arrow-up"></i> ${local.length} photo(s) waiting to upload</p>` : ''}
            ${t.photos_count ? `<p class="small text-muted"><i class="bi bi-image"></i> ${t.photos_count} photo(s) stored on the server — visible when online.</p>` : ''}
            ${!local.length && !t.photos_count ? '<p class="small text-muted">No photos yet.</p>' : ''}
            ${orderOpen ? `<form data-photos novalidate>${photoInput('photos', { multiple: true, label: 'Add photos' })}<button class="btn btn-primary w-100 mt-2"><i class="bi bi-cloud-arrow-up"></i> Save photos</button></form>` : ''}
        </div></div>`,
        mount(root) {
            root.querySelectorAll('img[data-blob]').forEach((img) => {
                const p = local.find((x) => x.uuid === img.dataset.blob);
                if (p) { img.src = URL.createObjectURL(p.blob); img.onload = () => URL.revokeObjectURL(img.src); }
            });
            root.querySelector('[data-loaded]')?.addEventListener('click', async () => {
                if (!window.confirm(`Mark truck ${t.truck_number} as fully loaded?`)) return;
                await enqueue(ctx.db, { type: 'loading.loaded', payload: { target: t.uuid }, summary: `Truck ${t.truck_number} loaded` });
                savedToast('Truck status');
                window.dispatchEvent(new Event('rst:rerender'));
            });
            const pf = root.querySelector('[data-photos]');
            if (pf) {
                enableImages(pf);
                pf.addEventListener('submit', async (e) => {
                    e.preventDefault();
                    const files = photosFrom(pf, 'photos');
                    if (!files.length) { window.alert('Choose or take at least one photo.'); return; }
                    await enqueuePhotos(ctx.db, { targetType: 'loading', target: t.uuid, label: `truck ${t.truck_number}`, files });
                    savedToast(`${files.length} photo(s)`);
                    window.dispatchEvent(new Event('rst:rerender'));
                });
            }
        },
    };
}
