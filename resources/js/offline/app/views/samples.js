import { uuid } from '../../db';
import { enqueue } from '../../outbox';
import { enableImages, input, mountPicker, photoInput, photosFrom, picker, quickAddDialog, select, setPicker, showErrors, textarea, values } from '../fields';
import { sampleLabel } from '../store';
import { cfg, detail, empty, fmtDate, go, h, matches, newestFirst, pageHeader, pendingBadge, qty, savedToast, stageBadge, today } from '../util';
import { saveDirectory } from './directory';

/** Samples and bundles — offline. */

export function samplesList(ctx, params, query) {
    const q = query.get('q') || '';
    const variety = query.get('variety') || '';
    const rows = ctx.samples
        .filter((s) => !variety || s.variety_uuid === variety)
        .filter((s) => { const l = sampleLabel(ctx, s); return matches(`${l.number} ${l.mill} ${l.city} ${l.variety} ${ctx.bundle(s.bundle_uuid)?.bundle_number || ''}`, q); })
        .sort(newestFirst('collection_date'));

    return {
        title: 'Samples',
        html: `${pageHeader('Samples', 'Samples on this device.', `<a href="#/bundles/new" class="btn btn-light"><i class="bi bi-boxes me-1"></i> New bundle</a><a href="#/samples/new" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> Add sample</a>`)}
        <div class="card">
            <form class="filter-bar" data-search><div class="row g-2">
                <div class="col-12 col-lg-8"><div class="search-input"><i class="bi bi-search"></i><input type="search" name="q" value="${h(q)}" class="form-control" placeholder="Sample no., mill, variety, bundle"></div></div>
                <div class="col-12 col-lg-4">${select('variety', '', ctx.varietyOptions(variety), { value: variety, placeholder: 'All varieties', cls: '' })}</div>
            </div></form>
            ${rows.length ? `<div class="list-group list-group-flush">${rows.map((s) => sampleRow(ctx, s)).join('')}</div>` : empty('basket', 'No samples on this device', 'Record samples as you collect them at mills.', '<a href="#/samples/new" class="btn btn-primary">Add sample</a>')}
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

function sampleRow(ctx, s) {
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
    const localPhotos = ctx.photosOf(s.uuid);

    return {
        title: l.number,
        back: '#/samples',
        html: `<div class="row g-4"><div class="col-lg-8">
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
                    ${detail('Bundle', s.bundle_uuid ? `<a href="#/bundles/${h(s.bundle_uuid)}" class="mono">${h(ctx.bundle(s.bundle_uuid)?.bundle_number || 'New bundle')}</a>` : '—')}
                    ${s.notes ? detail('Notes', h(s.notes), true) : ''}
                </dl></div></div>
            <div class="card"><div class="card-header"><h2>Dispatched to buyers <span class="badge rounded-pill text-bg-light border ms-1">${dispatches.length}</span></h2></div>
                ${dispatches.length ? `<div class="list-group list-group-flush">${dispatches.map((d) => `<a href="#/dispatches/${h(d.uuid)}" class="list-group-item list-group-item-action py-3 d-flex justify-content-between gap-2">
                    <span class="min-w-0"><span class="d-block fw-medium text-truncate">${h(ctx.buyer(d.buyer_uuid)?.name || '—')}</span><span class="small text-muted mono">${h(d.dispatch_number || 'New dispatch')} · ${fmtDate(d.dispatch_date)}</span> ${pendingBadge(d)}</span>
                    <span>${stageBadge(d)}</span></a>`).join('')}</div>` : empty('send', 'Not sent to any buyer yet')}
            </div></div>
            <div class="col-lg-4"><div class="card"><div class="card-header"><h2>Photo</h2></div><div class="card-body">
                ${localPhotos.length ? `<div class="photo-grid">${localPhotos.map((p) => `<div class="photo-tile"><img data-blob="${h(p.uuid)}" alt="Sample photo"></div>`).join('')}</div><p class="small text-muted mt-2 mb-0"><i class="bi bi-cloud-arrow-up"></i> Waiting to upload</p>`
                    : s.has_photo ? '<p class="small text-muted mb-0"><i class="bi bi-image"></i> Photo stored on the server — visible when online.</p>' : '<p class="small text-muted mb-0">No photo.</p>'}
            </div></div></div></div>`,
        mount(root) {
            root.querySelectorAll('img[data-blob]').forEach((img) => {
                const p = localPhotos.find((x) => x.uuid === img.dataset.blob);
                if (p) { img.src = URL.createObjectURL(p.blob); img.onload = () => URL.revokeObjectURL(img.src); }
            });
        },
    };
}

/* ---------- shared sample fields (single form + bundle rows) ---------- */

function sampleFields(ctx, prefix, row = {}, { withDate = true } = {}) {
    const n = (f) => (prefix ? `${prefix}[${f}]` : f);
    const mill = row.mill_id ? ctx.mill(row.mill_id) : null;
    return `<div class="row">
        ${picker(n('mill_id'), 'Rice mill', { value: mill?.uuid || '', display: mill ? `${mill.name} — ${mill.city}` : '', required: true, quickAdd: 'mill', cls: 'col-md-6 mb-3', placeholder: 'Search mill name or city' })}
        <div class="col-md-6 mb-3 quick-scope"><div class="d-flex justify-content-between align-items-end gap-2"><label class="form-label required" for="o_${n('rice_variety_id').replace(/[^a-z0-9]+/gi, '_')}">Rice variety</label>
            <button type="button" class="btn btn-link btn-sm p-0 mb-1 text-decoration-none" data-local-quick-add="variety"><i class="bi bi-plus-circle"></i> New variety</button></div>
            ${select(n('rice_variety_id'), '', ctx.varietyOptions(), { value: row.rice_variety_id || '', placeholder: 'Select variety', required: true, cls: '', attrs: 'data-variety-select' })}</div>
        ${withDate ? input(n('collection_date'), 'Collection date', { type: 'date', value: row.collection_date || today(), required: true, cls: 'col-6 mb-3', attrs: `max="${today()}"` }) : ''}
        ${withDate ? input(n('sample_number'), 'Sample no.', { value: row.sample_number || '', cls: 'col-6 mb-3', placeholder: 'Auto', hint: 'Leave blank to auto-number', attrs: 'autocapitalize="characters"' }) : ''}
        ${input(n('quantity'), 'Sample quantity', { type: 'number', value: row.quantity ?? '', cls: 'col-6 mb-3', attrs: 'step="0.01" min="0" inputmode="decimal"', placeholder: 'e.g. 500' })}
        ${select(n('quantity_unit'), 'Unit', cfg().sample_units || {}, { value: row.quantity_unit || 'g', cls: 'col-6 mb-3', attrs: 'data-unit-select' })}
    </div>`;
}

/** Client-side copy of the server rules for one sample (server re-validates on sync). */
function validateSample(ctx, row, prefix, errors, takenNumbers) {
    const n = (f) => (prefix ? `${prefix}[${f}]` : f);
    if (!row.mill_id || !ctx.mill(row.mill_id)) errors[n('mill_id')] = 'Choose the mill this sample came from.';
    if (!row.rice_variety_id) errors[n('rice_variety_id')] = 'Choose the rice variety.';
    if (row.collection_date !== undefined) {
        if (!row.collection_date) errors[n('collection_date')] = 'The collection date is required.';
        else if (row.collection_date > today()) errors[n('collection_date')] = 'The collection date cannot be in the future.';
    }
    if (row.quantity !== '' && row.quantity != null && (Number.isNaN(Number(row.quantity)) || Number(row.quantity) < 0)) errors[n('quantity')] = 'Enter a valid quantity.';
    const number = (row.sample_number || '').toUpperCase();
    if (number) {
        if (!/^[A-Z0-9][A-Z0-9\-/_. ]*$/.test(number)) errors[n('sample_number')] = 'Use letters, numbers and dashes only.';
        else if (ctx.samples.some((s) => s.sample_number === number) || takenNumbers.has(number)) errors[n('sample_number')] = 'This sample number already exists.';
        takenNumbers.add(number);
    }
}

function cleanSample(row) {
    const hasQty = row.quantity !== '' && row.quantity != null;
    return {
        mill_id: row.mill_id, rice_variety_id: row.rice_variety_id,
        collection_date: row.collection_date, sample_number: (row.sample_number || '').toUpperCase() || null,
        quantity: hasQty ? Number(row.quantity) : null, quantity_unit: hasQty ? row.quantity_unit : null, notes: row.notes || null,
    };
}

/** Wire pickers + quick-add buttons inside a scope. */
function wireSampleScope(ctx, scope) {
    scope.querySelectorAll('[data-local-picker]').forEach((el) => mountPicker(el, () => ctx.millItems()));
    enableImages(scope);
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
            document.querySelectorAll('select[data-variety-select]').forEach((sel) => sel.add(new Option(name, id)));
            root.querySelectorAll('template').forEach((t) => t.content.querySelectorAll('select[data-variety-select]').forEach((sel) => sel.add(new Option(name, id))));
            scope.querySelector('select').value = id;
        }
    });
}

export function sampleNew(ctx, params, query) {
    const millUuid = query.get('mill') || '';
    const bundleOptions = Object.fromEntries(ctx.bundles.sort(newestFirst('bundle_date')).slice(0, 30).map((b) => [b.uuid, `${b.bundle_number || 'New bundle'} · ${fmtDate(b.bundle_date)}`]));

    return {
        title: 'Add Sample',
        back: '#/samples',
        isForm: true,
        actionBar: true,
        html: `${pageHeader('Add Sample', 'Saved on this device and synced when the internet returns.', '<a href="#/bundles/new" class="btn btn-light"><i class="bi bi-boxes me-1"></i> Add several as a bundle</a>')}
        <form class="card" novalidate data-form>
            <div class="form-section"><div class="form-section-title"><i class="bi bi-basket"></i> Sample details</div>${sampleFields(ctx, '', { mill_id: millUuid })}</div>
            <div class="form-section"><div class="form-section-title"><i class="bi bi-camera"></i> Photo <span class="text-muted fw-normal small">(optional)</span></div>${photoInput('photo')}</div>
            <div class="form-section"><div class="form-section-title"><i class="bi bi-three-dots"></i> More</div><div class="row">
                ${select('sample_bundle_id', 'Bundle', bundleOptions, { placeholder: 'Not in a bundle', cls: 'col-md-6 mb-3', value: query.get('bundle') || '' })}
                ${textarea('notes', 'Notes', { cls: 'col-md-6 mb-0', placeholder: 'Moisture, broken %, colour, price indication…' })}
            </div></div>
            <div class="action-bar"><button class="btn btn-light" name="after" value="another"><i class="bi bi-plus-lg"></i> Save &amp; add another</button><button class="btn btn-primary" name="after" value="view"><i class="bi bi-check-lg"></i> Save sample</button></div>
        </form>`,
        mount(root) {
            const form = root.querySelector('[data-form]');
            wireSampleScope(ctx, form);
            wireQuickAdds(ctx, root);
            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                const data = values(form);
                const errors = {};
                validateSample(ctx, data, '', errors, new Set());
                if (showErrors(form, errors)) return;

                const id = uuid();
                const fields = { ...cleanSample(data), sample_bundle_id: data.sample_bundle_id || null };
                const photo = photosFrom(form, 'photo')[0];
                await enqueue(ctx.db, {
                    type: 'sample.create', payload: { uuid: id, fields },
                    summary: `New sample from ${ctx.mill(fields.mill_id).name}`,
                    photos: photo ? [{ uuid: uuid(), blob: photo, name: photo.name, target_type: 'sample', target: id }] : [],
                });
                savedToast('Sample');
                go(e.submitter?.value === 'another' ? `#/samples/new?mill=${fields.mill_id}&t=${Date.now()}` : `#/samples/${id}`);
            });
        },
    };
}

/* ---------------------------- bundles ---------------------------- */

export function bundlesList(ctx) {
    const rows = [...ctx.bundles].sort(newestFirst('bundle_date'));
    return {
        title: 'Bundles',
        html: `${pageHeader('Sample Bundles', 'Groups of samples received together.', '<a href="#/bundles/new" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> New bundle</a>')}
        <div class="card">${rows.length ? `<div class="list-group list-group-flush">${rows.map((b) => {
            const samples = ctx.samples.filter((s) => s.bundle_uuid === b.uuid);
            const mills = [...new Set(samples.map((s) => ctx.mill(s.mill_uuid)?.name).filter(Boolean))];
            return `<a href="#/bundles/${h(b.uuid)}" class="list-group-item list-group-item-action py-3"><div class="d-flex justify-content-between"><span class="fw-semibold mono">${h(b.bundle_number || 'New bundle')} ${pendingBadge(b)}</span><span class="small text-muted">${fmtDate(b.bundle_date)}</span></div>
                <div class="small text-muted">${samples.length} sample(s) · ${h(mills.slice(0, 3).join(', '))}</div></a>`;
        }).join('')}</div>` : empty('boxes', 'No bundles on this device')}</div>`,
    };
}

export function bundleShow(ctx, { id }) {
    const b = ctx.bundle(id);
    if (!b) return { title: 'Bundle', back: '#/bundles', html: empty('boxes', 'Not on this device') };
    const samples = ctx.samples.filter((s) => s.bundle_uuid === b.uuid);
    return {
        title: b.bundle_number || 'New bundle',
        back: '#/bundles',
        html: `<div class="card mb-4"><div class="card-body"><div class="d-flex flex-wrap gap-3 justify-content-between align-items-start">
            <div class="d-flex gap-3 align-items-center"><span class="stat-icon tone-gold"><i class="bi bi-boxes"></i></span><div><div class="small-caps">Bundle</div><h1 class="h4 fw-bold mb-0 mono">${h(b.bundle_number || 'New bundle')}</h1>${pendingBadge(b)}</div></div>
            <div class="page-actions">${samples.length ? `<a href="#/dispatches/new?samples=${samples.map((s) => s.uuid).join(',')}" class="btn btn-primary"><i class="bi bi-send"></i> Dispatch samples</a>` : ''}</div></div><hr>
            <dl class="detail-list">${detail('Date', fmtDate(b.bundle_date))}${detail('Samples', String(samples.length))}${detail('Mills', String(new Set(samples.map((s) => s.mill_uuid)).size))}${b.notes ? detail('Notes', h(b.notes), true) : ''}</dl>
        </div></div>
        <div class="card"><div class="card-header"><h2>Samples</h2></div>${samples.length ? `<div class="list-group list-group-flush">${samples.map((s) => sampleRow(ctx, s)).join('')}</div>` : empty('basket', 'This bundle is empty')}</div>`,
    };
}

function bundleRow(ctx, key, row = {}) {
    return `<div class="bundle-row" data-key="${h(key)}">
        <div class="bundle-row-head"><div class="bundle-row-num"><span data-row-number>1</span> Sample</div>
            <button type="button" class="btn btn-sm btn-light text-danger" data-remove-row aria-label="Remove this sample"><i class="bi bi-trash"></i> Remove</button></div>
        ${sampleFields(ctx, `samples[${key}]`, row, { withDate: false })}
        ${photoInput(`samples[${key}][photo]`, { label: 'Photo (optional)' })}
        <details class="mt-2"><summary class="small fw-semibold text-primary py-1" style="cursor:pointer">Sample no., date &amp; notes</summary><div class="row mt-2">
            ${input(`samples[${key}][sample_number]`, 'Sample no.', { cls: 'col-6 mb-3', placeholder: 'Auto', attrs: 'autocapitalize="characters"' })}
            ${input(`samples[${key}][collection_date]`, 'Collected', { type: 'date', value: row.collection_date || today(), cls: 'col-6 mb-3', attrs: `max="${today()}" data-row-date` })}
            ${textarea(`samples[${key}][notes]`, 'Notes', { cls: 'col-12 mb-0' })}
        </div></details></div>`;
}

export function bundleNew(ctx) {
    return {
        title: 'New Bundle',
        back: '#/bundles',
        isForm: true,
        actionBar: true,
        html: `${pageHeader('New Sample Bundle', 'Each sample keeps its own mill. Saved on this device until it syncs.')}
        <form novalidate data-form>
            <div class="card mb-4"><div class="card-header"><h2><i class="bi bi-boxes text-primary me-2"></i>Bundle details</h2></div><div class="card-body pb-0"><div class="row">
                ${input('bundle_number', 'Bundle no.', { cls: 'col-6 col-md-4 mb-3', placeholder: 'Auto', attrs: 'autocapitalize="characters"' })}
                ${input('bundle_date', 'Date', { type: 'date', value: today(), required: true, cls: 'col-6 col-md-4 mb-3', attrs: `max="${today()}" data-bundle-date` })}
                ${input('notes', 'Notes', { cls: 'col-12 col-md-4 mb-3', placeholder: 'Optional' })}
            </div></div></div>
            <div class="card mb-4"><div class="card-header justify-content-between"><h2><i class="bi bi-basket text-primary me-2"></i>Samples in this bundle</h2><span class="badge rounded-pill text-bg-light border fs-6" data-count>1</span></div>
                <div class="card-body"><div class="invalid-feedback d-block" data-error-for="samples"></div><div data-rows></div>
                <button type="button" class="btn btn-outline-primary w-100 mt-3 py-3" data-add-row style="border-style:dashed"><i class="bi bi-plus-circle"></i> Add another sample</button></div></div>
            <div class="action-bar action-bar-float"><a href="#/bundles" class="btn btn-light">Cancel</a><button class="btn btn-primary"><i class="bi bi-check-lg"></i> <span>Save bundle (<span data-count>1</span>)</span></button></div>
        </form>`,
        mount(root) {
            const form = root.querySelector('[data-form]');
            const rows = form.querySelector('[data-rows]');
            let next = 0;
            const renumber = () => {
                const all = rows.querySelectorAll('.bundle-row');
                all.forEach((r, i) => { r.querySelector('[data-row-number]').textContent = i + 1; r.querySelector('[data-remove-row]').classList.toggle('invisible', all.length === 1); });
                form.querySelectorAll('[data-count]').forEach((el) => { el.textContent = all.length; });
            };
            const addRow = () => {
                const prev = rows.querySelector('.bundle-row:last-child');
                const copy = prev ? {
                    mill_id: prev.querySelector('[data-local-picker] input[type=hidden]').value,
                    rice_variety_id: prev.querySelector('select[data-variety-select]').value,
                    quantity_unit: prev.querySelector('[data-unit-select]').value,
                    collection_date: form.bundle_date.value,
                } : { collection_date: form.bundle_date.value };
                const wrap = document.createElement('div');
                wrap.innerHTML = bundleRow(ctx, `r${next++}`, copy);
                const row = wrap.firstElementChild;
                rows.appendChild(row);
                wireSampleScope(ctx, row);
                renumber();
                return row;
            };
            form.addEventListener('click', (e) => {
                if (e.target.closest('[data-add-row]')) addRow().scrollIntoView({ behavior: 'smooth', block: 'center' });
                const rm = e.target.closest('[data-remove-row]');
                if (rm && rows.children.length > 1) { rm.closest('.bundle-row').remove(); renumber(); }
            });
            let prevDate = form.bundle_date.value;
            form.bundle_date.addEventListener('change', () => {
                rows.querySelectorAll('[data-row-date]').forEach((el) => { if (!el.value || el.value === prevDate) el.value = form.bundle_date.value; });
                prevDate = form.bundle_date.value;
            });
            wireQuickAdds(ctx, root);
            addRow();

            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                const data = values(form);
                const errors = {};
                if (!data.bundle_date) errors.bundle_date = 'The date is required.';
                else if (data.bundle_date > today()) errors.bundle_date = 'The date cannot be in the future.';
                const bundleNumber = (data.bundle_number || '').toUpperCase();
                if (bundleNumber && ctx.bundles.some((b) => b.bundle_number === bundleNumber)) errors.bundle_number = 'This bundle number already exists.';

                const taken = new Set();
                const rowEls = [...rows.querySelectorAll('.bundle-row')];
                const samples = {};
                const photos = [];
                for (const el of rowEls) {
                    const key = el.dataset.key;
                    const prefix = `samples[${key}]`;
                    const row = Object.fromEntries(['mill_id', 'rice_variety_id', 'quantity', 'quantity_unit', 'sample_number', 'collection_date', 'notes'].map((f) => [f, data[`${prefix}[${f}]`] ?? '']));
                    row.collection_date ||= data.bundle_date;
                    validateSample(ctx, row, prefix, errors, taken);
                    const sampleUuid = uuid();
                    samples[key] = { uuid: sampleUuid, ...cleanSample(row) };
                    const photo = photosFrom(el, `${prefix}[photo]`)[0];
                    if (photo) photos.push({ uuid: uuid(), blob: photo, name: photo.name, target_type: 'sample', target: sampleUuid });
                }
                if (!rowEls.length) errors.samples = 'Add at least one sample to the bundle.';
                if (showErrors(form, errors)) return;

                const id = uuid();
                await enqueue(ctx.db, {
                    type: 'bundle.create',
                    payload: { uuid: id, fields: { bundle_number: bundleNumber || null, bundle_date: data.bundle_date, notes: data.notes || null, samples } },
                    summary: `New bundle with ${rowEls.length} sample(s)`,
                    photos,
                });
                savedToast('Bundle');
                go(`#/bundles/${id}`);
            });
        },
    };
}
