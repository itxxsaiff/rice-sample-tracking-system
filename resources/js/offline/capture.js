import { openDb, uuid } from './db';
import { enqueue } from './outbox';
import { readActiveUser } from './session';
import { checkServer } from './sync';

/**
 * Safety net for normal (online) pages: if the connection drops while a
 * form is being filled in, tapping Save keeps the entry on the device as an
 * offline change instead of losing it to a browser error page.
 *
 * Forms opt in with data-offline-op="<operation type>" (plus
 * data-offline-target / data-offline-version for edits). Field names are the
 * same as the sync API, so the server validates them with the same rules.
 */

const SKIP = new Set(['_token', '_method', 'after', 'return_to']);

function formFields(form) {
    const fields = {};
    for (const [key, value] of new FormData(form).entries()) {
        if (SKIP.has(key) || value instanceof File) continue;
        if (key.endsWith('[]')) (fields[key.slice(0, -2)] ??= []).push(value);
        else fields[key] = typeof value === 'string' ? value.trim() : value;
    }
    return fields;
}

function files(form, name) {
    const input = form.querySelector(`input[type=file][name="${name}"]`);
    return input ? Array.from(input.files || []) : [];
}

/** Server ids in the form → local uuids (so the offline screens can show names). */
async function toUuid(db, table, id) {
    if (!id) return id || null;
    const row = await db[table].where('id').equals(Number(id)).first();
    return row?.uuid || Number(id);
}

const nullable = (v) => (v === '' || v === undefined ? null : v);

async function build(db, form) {
    const type = form.dataset.offlineOp;
    const target = form.dataset.offlineTarget;
    const version = Number(form.dataset.offlineVersion || 1);
    const f = formFields(form);

    switch (type) {
        case 'mill.create': case 'buyer.create': {
            const id = uuid();
            return { op: { type, payload: { uuid: id, fields: f }, summary: `New ${type.split('.')[0]} ${f.name || ''}`.trim() }, open: `/${type.split('.')[0]}s/${id}` };
        }
        case 'mill.update': case 'buyer.update':
            return { op: { type, payload: { target, base_version: version, fields: f }, summary: `Edit ${type.split('.')[0]} ${f.name || ''}`.trim() }, open: `/${type.split('.')[0]}s/${target}` };
        case 'sample.create': {
            // One independent sample per row of the "Add Sample" form.
            const keys = new Set([...new FormData(form).keys()].map((k) => k.match(/^samples\[([^\]]+)\]/)?.[1]).filter(Boolean));
            const ops = [];
            for (const key of keys) {
                const g = (name) => f[`samples[${key}][${name}]`];
                const id = uuid();
                ops.push({
                    type,
                    payload: {
                        uuid: id,
                        fields: {
                            mill_id: await toUuid(db, 'mills', g('mill_id')), rice_variety_id: await toUuid(db, 'varieties', g('rice_variety_id')),
                            collection_date: g('collection_date'),
                            notes: nullable(g('notes')),
                        },
                    },
                    summary: 'New sample',
                });
            }
            return { ops, open: ops.length === 1 ? `/samples/${ops[0].payload.uuid}` : '/samples' };
        }
        case 'dispatch.create': {
            const samples = await Promise.all((f.sample_ids || []).map((s) => toUuid(db, 'samples', s)));
            const buyers = await Promise.all((f.buyer_ids || []).map((b) => toUuid(db, 'buyers', b)));
            const pairs = samples.flatMap((s) => buyers.map((b) => ({ uuid: uuid(), sample: s, buyer: b })));
            return { op: { type, payload: { fields: { dispatch_date: f.dispatch_date, notes: nullable(f.notes) }, pairs }, summary: `${pairs.length} dispatch(es)` }, open: pairs.length === 1 ? `/dispatches/${pairs[0].uuid}` : '/dispatches' };
        }
        case 'dispatch.respond': {
            const fields = f.status === 'confirmed'
                ? { status: 'confirmed', confirmed_quantity: f.confirmed_quantity, confirmed_quantity_unit: 'maund', rate: f.rate, rate_unit: 'per_maund', packing_type: f.packing_type, packing_details: nullable(f.packing_details), confirmation_date: f.confirmation_date }
                : { status: f.status, rejection_date: f.rejection_date };
            return { op: { type, payload: { target, base_version: version, fields }, summary: `Buyer response (${f.status || 'unknown'})` }, open: `/dispatches/${target}` };
        }
        case 'loading.create': {
            const id = uuid();
            const photos = files(form, 'photos[]').map((p) => ({ uuid: uuid(), blob: p, name: p.name, target_type: 'loading', target: id }));
            return {
                op: { type, payload: { uuid: id, target, fields: { ...f, action: form.dataset.offlineAction === 'save' ? 'save' : 'loaded' } }, summary: `Truck ${(f.truck_number || '').toUpperCase()}`, photos },
                open: `/loadings/${id}`,
                complete: form.dataset.offlineAction === 'complete',
            };
        }
        default:
            return null;
    }
}

export function initFormCapture() {
    // Remember which submit button was used (needed for "save / mark loaded / complete").
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('form[data-offline-op] button[name="action"]');
        if (btn) btn.form.dataset.offlineAction = btn.value;
    }, true);

    document.addEventListener('submit', async (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || !form.dataset.offlineOp || form.dataset.offlineChecked === '1') return;
        const user = readActiveUser();
        if (!user) return;

        event.preventDefault();
        event.stopImmediatePropagation();
        const submitter = event.submitter;

        const server = await checkServer();
        if (server.reachable) {
            // Normal online save.
            form.dataset.offlineChecked = '1';
            if (form.requestSubmit) form.requestSubmit(submitter && submitter.form === form ? submitter : undefined);
            else form.submit();
            setTimeout(() => { form.dataset.offlineChecked = ''; }, 1500);
            return;
        }

        const ok = window.confirm('No internet connection right now.\n\nSave this on your device instead? It will sync automatically when the internet returns.');
        if (!ok) return;

        const db = openDb(user.id);
        const built = await build(db, form);
        if (!built) return;

        for (const op of built.ops || [built.op]) await enqueue(db, op);
        if (built.complete) {
            await enqueue(db, { type: 'order.complete', payload: { target: form.dataset.offlineTarget }, summary: 'Complete order' });
        }
        try {
            sessionStorage.setItem('rst.flash', 'Saved on this device. It will sync when the internet returns.');
        } catch { /* ignore */ }
        location.href = `/#${built.open}`;
    }, true);
}
