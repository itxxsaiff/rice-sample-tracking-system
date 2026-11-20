import { uuid } from '../../db';
import { enqueue } from '../../outbox';
import { input, mountPicker, picker, quickAddDialog, select, setPicker, showErrors, textarea, values } from '../fields';
import { sampleLabel } from '../store';
import { cfg, detail, empty, fmtDate, go, h, matches, newestFirst, pageHeader, pendingBadge, qty, savedToast, stageBadge, today } from '../util';
import { saveDirectory } from './directory';

/** Samples — offline. */

export function samplesList(ctx, params, query) {
    const q = query.get('q') || '';
    const variety = query.get('variety') || '';
    const rows = ctx.samples
        .filter((s) => !variety || s.variety_uuid === variety)
        .filter((s) => { const l = sampleLabel(ctx, s); return matches(`${l.number} ${l.mill} ${l.city} ${l.variety}`, q); })
        .sort(newestFirst('collection_date'));

    return {
        title: 'Samples',
        html: `${pageHeader('Samples', 'Samples on this device.', '<a href="#/samples/new" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> Add sample</a>')}
        <div class="card">
            <form class="filter-bar" data-search><div class="row g-2">
                <div class="col-12 col-lg-8"><div class="search-input"><i class="bi bi-search"></i><input type="search" name="q" value="${h(q)}" class="form-control" placeholder="Sample no., mill, variety"></div></div>
                <div class="col-12 col-lg-4">${select('variety', '', ctx.varietyOptions(variety), { value: variety, placeholder: 'All varieties', cls: '' })}</div>
            </div></form>
            ${rows.length ? `<div class="list-group list-group-flush">${rows.map((s) => sampleItem(ctx, s)).join('')}</div>` : empty('basket', 'No samples on this device', 'Record samples as you collect them at mills.', '<a href="#/samples/new" class="btn btn-primary">Add sample</a>')}
        </div>`,
        keepFocus: '[name=q]',
        mount(root) {
            const form = root.querySelector('[data-search]');
            const update = () => { history.replaceState(null, '', `#/samples?q=${encodeURIComponent(form.q.value)}&variety=${form.variety.value}`); window.dispatchEvent(new Event('rst:rerender')); };
            form.addEventListener('submit', (e) => e.preventDefault());
            form.q.addEventListener('input', update);
            form.variety.addEventListener('change', update);
        },
    };
}

function sampleItem(ctx, s) {
    const l = sampleLabel(ctx, s);
    const sent = ctx.dispatchesOf(s.uuid).length;
    return `<a href="#/samples/${h(s.uuid)}" class="list-group-item list-group-item-action py-3">
        <div class="d-flex justify-content-between gap-2"><span class="min-w-0"><span class="fw-semibold mono">${h(l.number)}</span> <span class="badge text-bg-light border">${h(l.variety)}</span> ${pendingBadge(s)}
        <span class="d-block small text-muted text-truncate">${h(l.mill)}${l.city ? `, ${h(l.city)}` : ''}</span></span>
        <span class="small text-muted text-nowrap">${fmtDate(s.collection_date)}</span></div>
        <div class="small text-muted mt-1">${h(qty(s.quantity, s.quantity_unit) || '')}${sent ? ` · sent to ${sent} buyer${sent > 1 ? 's' : ''}` : ' · not sent'}</div></a>`;
}

export function sampleShow(ctx, { id }) {
    const s = ctx.sample(id);
    if (!s) return { title: 'Sample', back: '#/samples', html: empty('basket', 'Not on this device', 'Open this sample when you are online.') };
    const l = sampleLabel(ctx, s);
    const dispatches = ctx.dispatchesOf(s.uuid);
    const localPhotos = ctx.photosOf(s.uuid); // only photos queued before sample photos were removed

    const photoCard = localPhotos.length || s.has_photo
        ? `<div class="col-lg-4"><div class="card"><div class="card-header"><h2>Photo</h2></div><div class="card-body">
            ${localPhotos.length
                ? `<div class="photo-grid">${localPhotos.map((p) => `<div class="photo-tile"><img data-blob="${h(p.uuid)}" alt="Sample photo"></div>`).join('')}</div><p class="small text-muted mt-2 mb-0"><i class="bi bi-cloud-arrow-up"></i> Waiting to upload</p>`
                : '<p class="small text-muted mb-0"><i class="bi bi-image"></i> Photo stored on the server — visible when online.</p>'}
            </div></div></div>`
        : '';

    return {
        title: l.number,
        back: '#/samples',
        html: `<div class="row g-4"><div class="${photoCard ? 'col-lg-8' : 'col-12'}">
            <div class="card mb-4"><div class="card-body">
                <div class="d-flex flex-wrap gap-3 justify-content-between align-items-start">
                    <div class="d-flex gap-3 align-items-center"><span class="stat-icon tone-navy"><i class="bi bi-basket"></i></span>
                        <div><div class="small-caps">Sample</div><h1 class="h4 fw-bold mb-0 mono">${h(l.number)}</h1>${pendingBadge(s)}</div></div>
                    <div class="page-actions"><a href="#/dispatches/new?sample=${h(s.uuid)}" class="btn btn-primary"><i class="bi bi-send"></i> Dispatch</a></div>
                </div><hr>
                <dl class="detail-list">
                    ${detail('Rice mill', `<a href="#/mills/${h(s.mill_uuid)}">${h(l.mill)}</a>`)}
                    ${detail('City', h(l.city || '—'))}
                    ${detail('Variety', h(l.variety))}
                    ${detail('Collected', fmtDate(s.collection_date))}
                    ${detail('Quantity', h(qty(s.quantity, s.quantity_unit) || '—'))}
                    ${s.notes ? detail('Notes', h(s.notes), true) : ''}
                </dl></div></div>
            <div class="card"><div class="card-header"><h2>Dispatched to buyers <span class="badge rounded-pill text-bg-light border ms-1">${dispatches.length}</span></h2></div>
                ${dispatches.length ? `<div class="list-group list-group-flush">${dispatches.map((d) => `<a href="#/dispatches/${h(d.uuid)}" class="list-group-item list-group-item-action py-3 d-flex justify-content-between gap-2">
                    <span class="min-w-0"><span class="d-block fw-medium text-truncate">${h(ctx.buyer(d.buyer_uuid)?.name || '—')}</span><span class="small text-muted mono">${h(d.dispatch_number || 'New dispatch')} · ${fmtDate(d.dispatch_date)}</span> ${pendingBadge(d)}</span>
                    <span>${stageBadge(d)}</span></a>`).join('')}</div>` : empty('send', 'Not sent to any buyer yet')}
            </div></div>${photoCard}</div>`,
        mount(root) {
            root.querySelectorAll('img[data-blob]').forEach((img) => {
                const p = localPhotos.find((x) => x.uuid === img.dataset.blob);
                if (p) { img.src = URL.createObjectURL(p.blob); img.onload = () => URL.revokeObjectURL(img.src); }
            });
        },
    };
}

/* ---------------------------- add samples ---------------------------- */

/** One sample row of the "Add Sample" form (same fields as the online form). */
function sampleRow(ctx, key, row = {}) {
    const n = (f) => `samples[${key}][${f}]`;
    const mill = row.mill_id ? ctx.mill(row.mill_id) : null;
    return `<div class="sample-row" data-key="${h(key)}">
        <div class="sample-row-head"><div class="sample-row-num"><span data-row-number>1</span> Sample</div>
            <button type="button" class="btn btn-sm btn-light text-danger" data-remove-row aria-label="Remove this sample"><i class="bi bi-trash"></i> Remove</button></div>
        <div class="row">
            ${picker(n('mill_id'), 'Rice mill', { value: mill?.uuid || '', display: mill ? `${mill.name} — ${mill.city}` : '', required: true, quickAdd: 'mill', cls: 'col-md-6 mb-3', placeholder: 'Search mill name or city' })}
            <div class="col-md-6 mb-3 quick-scope"><div class="d-flex justify-content-between align-items-end gap-2"><label class="form-label required" for="o_${n('rice_variety_id').replace(/[^a-z0-9]+/gi, '_')}">Rice variety</label>
                <button type="button" class="btn btn-link btn-sm p-0 mb-1 text-decoration-none" data-local-quick-add="variety"><i class="bi bi-plus-circle"></i> New variety</button></div>
                ${select(n('rice_variety_id'), '', ctx.varietyOptions(), { value: row.rice_variety_id || '', placeholder: 'Select variety', required: true, cls: '', attrs: 'data-variety-select' })}</div>
            ${input(n('collection_date'), 'Collection date', { type: 'date', value: row.collection_date || today(), required: true, cls: 'col-md-6 mb-3', attrs: `max="${today()}" data-row-date` })}

            ${textarea(n('notes'), 'Notes', { rows: 1, cls: 'col-md-6 mb-0', placeholder: 'Moisture, broken %, colour, price indication…' })}
        </div></div>`;
}

/** Client-side copy of the server rules for one sample (the server re-validates on sync). */
function validateSample(ctx, row, prefix, errors) {
    const n = (f) => `${prefix}[${f}]`;
    if (!row.mill_id || !ctx.mill(row.mill_id)) errors[n('mill_id')] = 'Choose the mill this sample came from.';
    if (!row.rice_variety_id) errors[n('rice_variety_id')] = 'Choose the rice variety.';
    if (!row.collection_date) errors[n('collection_date')] = 'The collection date is required.';
    else if (row.collection_date > today()) errors[n('collection_date')] = 'The collection date cannot be in the future.';
}

function wireQuickAdds(ctx, root) {
    root.addEventListener('click', async (e) => {
        const btn = e.target.closest('[data-local-quick-add]');
        if (!btn) return;
        const scope = btn.closest('.quick-scope');
        if (btn.dataset.localQuickAdd === 'mill') {
            const item = await quickAddDialog('mill', (data) => saveDirectory(ctx, 'mill', data));
            if (item) { setPicker(scope.querySelector('[data-local-picker]'), item); savedToast('Mill'); }
        } else {
            const name = window.prompt('New rice variety name (e.g. 1847)')?.trim();
            if (!name) return;
            if (ctx.varieties.some((v) => v.name.toLowerCase() === name.toLowerCase())) { window.alert('This variety already exists.'); return; }
            const id = uuid();
            await enqueue(ctx.db, { type: 'variety.create', payload: { uuid: id, fields: { name } }, summary: `New variety ${name}` });
            const variety = { uuid: id, name, is_active: true, _pending: true };
            ctx.varieties.push(variety);
            ctx.varietiesBy.set(id, variety);
            root.querySelectorAll('select[data-variety-select]').forEach((sel) => sel.add(new Option(name, id)));
            scope.querySelector('select').value = id;
        }
    });
}

export function sampleNew(ctx, params, query) {
    const millUuid = query.get('mill') || '';

    return {
        title: 'Add Sample',
        back: '#/samples',
        isForm: true,
        actionBar: true,
        html: `${pageHeader('Add Sample', 'Use “Add another sample” to save several at once — each one is a separate sample. Saved on this device until it syncs.')}
        <form novalidate data-form>
            <div class="card mb-4"><div class="card-body"><div data-rows></div>
                <button type="button" class="btn btn-outline-primary w-100 mt-3 py-3" data-add-row style="border-style:dashed"><i class="bi bi-plus-circle"></i> Add another sample</button></div></div>
            <div class="action-bar action-bar-float"><a href="#/samples" class="btn btn-light">Cancel</a><button class="btn btn-primary"><i class="bi bi-check-lg"></i> <span data-save-label>Save sample</span></button></div>
        </form>`,
        mount(root) {
            const form = root.querySelector('[data-form]');
            const rows = form.querySelector('[data-rows]');
            let next = 0;
            const renumber = () => {
                const all = rows.querySelectorAll('.sample-row');
                all.forEach((r, i) => { r.querySelector('[data-row-number]').textContent = i + 1; r.querySelector('[data-remove-row]').classList.toggle('invisible', all.length === 1); });
                form.querySelector('[data-save-label]').textContent = all.length > 1 ? `Save ${all.length} samples` : 'Save sample';
            };
            const addRow = (copy = {}) => {
                const wrap = document.createElement('div');
                wrap.innerHTML = sampleRow(ctx, `r${next++}`, copy);
                const row = wrap.firstElementChild;
                rows.appendChild(row);
                row.querySelectorAll('[data-local-picker]').forEach((el) => mountPicker(el, () => ctx.millItems()));
                renumber();
                return row;
            };
            form.addEventListener('click', (e) => {
                if (e.target.closest('[data-add-row]')) {
                    // Same day as the row above; mill and variety are chosen per sample.
                    const prev = rows.querySelector('.sample-row:last-child');
                    const row = addRow(prev ? { collection_date: prev.querySelector('[data-row-date]').value } : {});
                    row.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
                const rm = e.target.closest('[data-remove-row]');
                if (rm && rows.children.length > 1) { rm.closest('.sample-row').remove(); renumber(); }
            });
            wireQuickAdds(ctx, root);
            addRow({ mill_id: millUuid });

            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                const data = values(form);
                const errors = {};
                const entries = [...rows.querySelectorAll('.sample-row')].map((el) => {
                    const prefix = `samples[${el.dataset.key}]`;
                    const row = Object.fromEntries(['mill_id', 'rice_variety_id', 'collection_date', 'notes'].map((f) => [f, data[`${prefix}[${f}]`] ?? '']));
                    validateSample(ctx, row, prefix, errors);
                    return row;
                });
                if (showErrors(form, errors)) return;

                const ids = [];
                for (const row of entries) {
                    const id = uuid();
                    await enqueue(ctx.db, {
                        type: 'sample.create',
                        payload: { uuid: id, fields: {
                            mill_id: row.mill_id, rice_variety_id: row.rice_variety_id, collection_date: row.collection_date, notes: row.notes || null,
                        } },
                        summary: `New sample from ${ctx.mill(row.mill_id).name}`,
                    });
                    ids.push(id);
                }
                savedToast(ids.length > 1 ? `${ids.length} samples` : 'Sample');
                go(ids.length === 1 ? `#/samples/${ids[0]}` : '#/samples');
            });
        },
    };
}
