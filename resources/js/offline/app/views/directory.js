import { uuid } from '../../db';
import { enqueue } from '../../outbox';
import { input, showErrors, textarea, values } from '../fields';
import { sampleLabel } from '../store';
import { cfg, detail, empty, fmtDate, go, h, matches, newestFirst, pageHeader, pendingBadge, qty, rate, savedToast, stageBadge } from '../util';

/** Rice mills and buyers: list, profile with history, add/edit — offline. */

const KINDS = {
    mill: { table: 'mills', title: 'Rice Mills', one: 'Rice Mill', icon: 'buildings', route: 'mills', nameLabel: 'Mill name' },
    buyer: { table: 'buyers', title: 'Buyers', one: 'Buyer', icon: 'person-vcard', route: 'buyers', nameLabel: 'Buyer / company name' },
};

export function directoryList(kind) {
    const k = KINDS[kind];
    return (ctx, params, query) => {
        const q = query.get('q') || '';
        const rows = ctx[k.table].filter((r) => matches(`${r.name} ${r.city} ${r.contact_person} ${r.phone}`, q));
        return {
            title: k.title,
            html: `${pageHeader(k.title, `Saved on this device (${ctx[k.table].length}).`, `<a href="#/${k.route}/new" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> Add ${kind}</a>`)}
            <div class="card">
                <form class="filter-bar" data-search><div class="search-input"><i class="bi bi-search"></i>
                    <input type="search" name="q" value="${h(q)}" class="form-control" placeholder="Search name, city, contact or phone"></div></form>
                ${rows.length ? `<div class="list-group list-group-flush">${rows.map((r) => `
                    <a href="#/${k.route}/${h(r.uuid)}" class="list-group-item list-group-item-action py-3 d-flex justify-content-between align-items-center gap-2">
                        <span class="min-w-0"><span class="d-block fw-semibold text-truncate">${h(r.name)} ${pendingBadge(r)}</span>
                        <span class="small text-muted"><i class="bi bi-geo-alt"></i> ${h(r.city)}${r.phone ? ` · ${h(r.phone)}` : ''}</span></span>
                        <i class="bi bi-chevron-right text-muted"></i></a>`).join('')}</div>`
                    : empty(k.icon, q ? 'No matches on this device' : `No ${k.title.toLowerCase()} on this device yet`)}
            </div>`,
            mount(root) {
                const form = root.querySelector('[data-search]');
                form.addEventListener('submit', (e) => e.preventDefault());
                form.q.addEventListener('input', () => { history.replaceState(null, '', `#/${k.route}?q=${encodeURIComponent(form.q.value)}`); window.dispatchEvent(new Event('rst:rerender')); });
            },
            keepFocus: '[name=q]',
        };
    };
}

export function directoryShow(kind) {
    const k = KINDS[kind];
    return (ctx, { id }) => {
        const r = ctx[`${k.table}By`].get(id);
        if (!r) return notFound(k);

        let history = '';
        if (kind === 'mill') {
            const samples = ctx.samples.filter((s) => s.mill_uuid === r.uuid).sort(newestFirst('collection_date'));
            history = `<div class="card"><div class="card-header justify-content-between flex-wrap"><h2 class="flex-grow-1">Sample history <span class="badge rounded-pill text-bg-light border ms-1">${samples.length}</span></h2>
                <a href="#/samples/new?mill=${h(r.uuid)}" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i> Add sample</a></div>
                ${samples.length ? `<div class="list-group list-group-flush">${samples.map((s) => {
                    const l = sampleLabel(ctx, s);
                    return `<a href="#/samples/${h(s.uuid)}" class="list-group-item list-group-item-action py-3"><div class="d-flex justify-content-between gap-2">
                        <span><span class="fw-semibold mono">${h(l.number)}</span> <span class="badge text-bg-light border">${h(l.variety)}</span> ${pendingBadge(s)}</span>
                        <span class="small text-muted text-nowrap">${fmtDate(s.collection_date)}</span></div>
                        <div class="small text-muted">${h(qty(s.quantity, s.quantity_unit) || '')}${s.bundle_uuid ? ` · bundle ${h(ctx.bundle(s.bundle_uuid)?.bundle_number || 'new')}` : ''}</div></a>`;
                }).join('')}</div>` : empty('basket', 'No samples from this mill on this device')}</div>`;
        } else {
            const dispatches = ctx.dispatches.filter((d) => d.buyer_uuid === r.uuid).sort(newestFirst('dispatch_date'));
            history = `<div class="card"><div class="card-header justify-content-between"><h2>Dispatch &amp; order history <span class="badge rounded-pill text-bg-light border ms-1">${dispatches.length}</span></h2>
                <a href="#/dispatches/new?buyer=${h(r.uuid)}" class="btn btn-sm btn-primary text-nowrap"><i class="bi bi-send"></i> Send samples</a></div>
                ${dispatches.length ? `<div class="list-group list-group-flush">${dispatches.map((d) => {
                    const s = ctx.sample(d.sample_uuid);
                    const l = s ? sampleLabel(ctx, s) : { number: '—', variety: '', mill: '' };
                    return `<a href="#/dispatches/${h(d.uuid)}" class="list-group-item list-group-item-action py-3"><div class="d-flex justify-content-between gap-2">
                        <span class="min-w-0"><span class="fw-semibold mono">${h(d.dispatch_number || 'New dispatch')}</span> ${pendingBadge(d)}<span class="d-block small text-muted text-truncate">${h(l.number)} ${h(l.variety)} · ${h(l.mill)}</span></span>
                        <span>${stageBadge(d)}</span></div>
                        <div class="small mt-1">${fmtDate(d.dispatch_date)}${d.status === 'confirmed' ? ` · <strong>${h(qty(d.confirmed_quantity, d.confirmed_quantity_unit))}</strong> · ${h(rate(d))} · ${h(d.packing_type || '')}` : ''}${d.status === 'rejected' ? ` · ${h(d.rejection_reason || '')}` : ''}</div></a>`;
                }).join('')}</div>` : empty('send', 'No dispatches to this buyer on this device')}</div>`;
        }

        return {
            title: r.name,
            back: `#/${k.route}`,
            html: `<div class="card mb-4"><div class="card-body">
                <div class="d-flex flex-wrap gap-3 align-items-start justify-content-between">
                    <div class="d-flex gap-3 align-items-center min-w-0"><span class="stat-icon tone-${kind === 'mill' ? 'navy' : 'purple'}"><i class="bi bi-${k.icon}"></i></span>
                        <div class="min-w-0"><h1 class="h4 fw-bold mb-0">${h(r.name)}</h1><div class="text-muted"><i class="bi bi-geo-alt"></i> ${h(r.city)} ${pendingBadge(r)}</div></div></div>
                    <div class="page-actions"><a href="#/${k.route}/${h(r.uuid)}/edit" class="btn btn-light"><i class="bi bi-pencil"></i> Edit</a></div>
                </div><hr>
                <dl class="detail-list">
                    ${detail('Contact person', h(r.contact_person || '—'))}
                    ${detail('Phone', r.phone ? `<a href="tel:${h(r.phone)}"><i class="bi bi-telephone"></i> ${h(r.phone)}</a>` : '—')}
                    ${detail('Address', h(r.address || '—'))}
                    ${r.notes ? detail('Notes', h(r.notes), true) : ''}
                </dl></div></div>
                ${history}`,
        };
    };
}

export function directoryForm(kind) {
    const k = KINDS[kind];
    return (ctx, { id }) => {
        const r = id ? ctx[`${k.table}By`].get(id) : null;
        if (id && !r) return notFound(k);

        return {
            title: r ? `Edit ${r.name}` : `Add ${k.one}`,
            back: r ? `#/${k.route}/${r.uuid}` : `#/${k.route}`,
            isForm: true,
            actionBar: true,
            html: `${pageHeader(r ? `Edit ${r.name}` : `Add ${k.one}`, r ? '' : 'Saved on this device and synced when the internet returns.')}
            <form class="card" novalidate data-form>
                <div class="form-section"><div class="form-section-title"><i class="bi bi-${k.icon}"></i> Details</div><div class="row">
                    ${input('name', k.nameLabel, { value: r?.name, required: true, cls: 'col-md-6 mb-3', attrs: 'autocapitalize="words"' })}
                    ${input('city', 'City', { value: r?.city, required: true, cls: 'col-md-6 mb-3', attrs: 'list="offlineCityList" autocapitalize="words" autocomplete="off"' })}
                    ${input('contact_person', 'Contact person', { value: r?.contact_person, cls: 'col-md-6 mb-3' })}
                    ${input('phone', kind === 'mill' ? 'Phone' : 'Contact number', { value: r?.phone, type: 'tel', cls: 'col-md-6 mb-3', attrs: 'inputmode="tel"' })}
                    ${input('address', 'Address', { value: r?.address, cls: 'col-12 mb-3' })}
                    ${textarea('notes', 'Notes', { value: r?.notes, cls: 'col-12 mb-0' })}
                </div></div>
                <datalist id="offlineCityList">${[...new Set([...(cfg().cities || []), ...ctx.mills.map((m) => m.city), ...ctx.buyers.map((b) => b.city)])].map((c) => `<option value="${h(c)}"></option>`).join('')}</datalist>
                <div class="action-bar"><a href="${r ? `#/${k.route}/${h(r.uuid)}` : `#/${k.route}`}" class="btn btn-light">Cancel</a><button class="btn btn-primary"><i class="bi bi-check-lg"></i> Save</button></div>
            </form>`,
            mount(root) {
                const form = root.querySelector('[data-form]');
                form.addEventListener('submit', async (e) => {
                    e.preventDefault();
                    const result = await saveDirectory(ctx, kind, values(form), r);
                    if (result.errors) { showErrors(form, result.errors); return; }
                    savedToast(k.one);
                    go(`#/${k.route}/${result.uuid}`);
                });
            },
        };
    };
}

/** Validate and queue a mill/buyer create or edit. Used by forms and quick-add dialogs. */
export async function saveDirectory(ctx, kind, data, existing = null) {
    const k = KINDS[kind];
    const fields = {
        name: (data.name || '').replace(/\s+/g, ' ').trim(),
        city: (data.city || '').replace(/\s+/g, ' ').trim(),
        contact_person: data.contact_person || null,
        phone: data.phone || null,
        address: data.address ?? existing?.address ?? null,
        notes: data.notes ?? existing?.notes ?? null,
    };
    const errors = {};
    if (!fields.name) errors.name = `The ${kind} name is required.`;
    if (!fields.city) errors.city = 'The city is required.';
    const duplicate = ctx[k.table].find((r) => r.uuid !== existing?.uuid && r.name?.toLowerCase() === fields.name.toLowerCase() && r.city?.toLowerCase() === fields.city.toLowerCase());
    if (duplicate) errors.name = `A ${kind} with this name already exists in this city. Open the existing profile instead.`;
    if (Object.keys(errors).length) return { errors };

    if (existing) {
        await enqueue(ctx.db, { type: `${kind}.update`, payload: { target: existing.uuid, base_version: existing.version, fields }, summary: `Edit ${kind} ${fields.name}` });
        return { uuid: existing.uuid };
    }

    const id = uuid();
    await enqueue(ctx.db, { type: `${kind}.create`, payload: { uuid: id, fields }, summary: `New ${kind} ${fields.name}, ${fields.city}` });
    const record = { uuid: id, ...fields, _pending: true };
    ctx[k.table].push(record);
    ctx[`${k.table}By`].set(id, record);

    return { uuid: id, label: fields.name, sub: fields.city };
}

function notFound(k) {
    return { title: k.title, back: `#/${k.route}`, html: empty(k.icon, 'Not on this device', 'This record has not been downloaded to this device. Open it when you are online.', `<a href="#/${k.route}" class="btn btn-light">Back to ${k.title.toLowerCase()}</a>`) };
}
