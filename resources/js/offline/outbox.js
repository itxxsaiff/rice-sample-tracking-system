import { RECORD_TABLES, requestPersistence, uuid } from './db';

/**
 * Outbox: every change made on the device becomes an operation.
 *
 * An operation is applied to the local tables immediately (so lists and
 * histories show it at once, marked "not synced yet") and queued for the
 * server. Payload field names match the online forms, so the server can
 * validate them with exactly the same rules.
 *
 * deps:    uuids of records this operation needs on the server first
 * creates: uuids of records this operation creates
 */

const now = () => new Date().toISOString();

/** Apply an operation's effect to the local tables (optimistic). */
async function applyLocally(db, op) {
    const p = op.payload;
    const f = p.fields || {};
    const pending = { _pending: true, _op: op.uuid };

    switch (op.type) {
        case 'mill.create':
            await db.mills.put({ uuid: p.uuid, id: null, version: 1, created_at: now(), ...f, ...pending });
            break;
        case 'buyer.create':
            await db.buyers.put({ uuid: p.uuid, id: null, version: 1, created_at: now(), ...f, ...pending });
            break;
        case 'mill.update':
            await db.mills.update(p.target, { ...f, ...pending });
            break;
        case 'buyer.update':
            await db.buyers.update(p.target, { ...f, ...pending });
            break;
        case 'variety.create':
            await db.varieties.put({ uuid: p.uuid, id: null, name: f.name, is_active: true, version: 1, ...pending });
            break;
        case 'sample.create':
            await db.samples.put(sampleRecord(p.uuid, f, f.sample_bundle_id || null, pending));
            break;
        case 'bundle.create':
            await db.bundles.put({ uuid: p.uuid, id: null, bundle_number: f.bundle_number || null, bundle_date: f.bundle_date, notes: f.notes || null, version: 1, ...pending });
            for (const row of Object.values(f.samples || {})) {
                await db.samples.put(sampleRecord(row.uuid, { ...row, collection_date: row.collection_date || f.bundle_date }, p.uuid, pending));
            }
            break;
        case 'bundle.add_samples': {
            const bundle = await db.bundles.get(p.target);
            for (const row of Object.values(f.samples || {})) {
                await db.samples.put(sampleRecord(row.uuid, { ...row, collection_date: row.collection_date || bundle?.bundle_date }, p.target, pending));
            }
            break;
        }
        case 'dispatch.create':
            for (const pair of p.pairs) {
                await db.dispatches.put({
                    uuid: pair.uuid, id: null, dispatch_number: null, sample_uuid: pair.sample, buyer_uuid: pair.buyer,
                    dispatch_date: f.dispatch_date, notes: f.notes || null, status: 'pending', loading_status: null, version: 1, ...pending,
                });
            }
            break;
        case 'dispatch.respond': {
            const confirmed = f.status === 'confirmed';
            const current = await db.dispatches.get(p.target);
            await db.dispatches.update(p.target, {
                status: f.status,
                confirmed_quantity: confirmed ? Number(f.confirmed_quantity) : null,
                confirmed_quantity_unit: confirmed ? f.confirmed_quantity_unit : null,
                rate: confirmed ? Number(f.rate) : null,
                rate_unit: confirmed ? f.rate_unit : null,
                packing_type: confirmed ? f.packing_type : null,
                packing_details: confirmed ? (f.packing_details || null) : null,
                confirmation_date: confirmed ? f.confirmation_date : null,
                rejection_reason: confirmed ? null : f.rejection_reason,
                rejection_date: confirmed ? null : f.rejection_date,
                loading_status: confirmed ? (current?.loading_status || 'pending') : null,
                ...pending,
            });
            break;
        }
        case 'loading.create':
            await db.loadings.put({
                uuid: p.uuid, id: null, loading_number: null, dispatch_uuid: p.target,
                ...f, quantity: Number(f.quantity), bag_count: f.bag_count ? Number(f.bag_count) : null,
                status: f.action === 'loaded' ? 'completed' : 'pending', photos_count: 0, version: 1, ...pending,
            });
            await db.dispatches.update(p.target, { loading_status: 'in_progress', ...pending });
            break;
        case 'loading.loaded':
            await db.loadings.update(p.target, { status: 'completed', ...pending });
            break;
        case 'order.complete':
            await db.dispatches.update(p.target, { loading_status: 'completed', loading_completed_at: now(), ...pending });
            break;
        case 'photo.add':
            break; // the blob itself is stored in db.photos
        default:
            throw new Error(`Unknown operation ${op.type}`);
    }
}

function sampleRecord(uuidValue, f, bundleUuid, pending) {
    return {
        uuid: uuidValue, id: null, sample_number: f.sample_number || null,
        mill_uuid: f.mill_id, variety_uuid: f.rice_variety_id, bundle_uuid: bundleUuid,
        collection_date: f.collection_date, quantity: f.quantity === '' || f.quantity == null ? null : Number(f.quantity),
        quantity_unit: f.quantity === '' || f.quantity == null ? null : f.quantity_unit, notes: f.notes || null,
        has_photo: false, version: 1, created_at: now(), ...pending,
    };
}

/** Which uuids an operation depends on and creates. */
function graph(op) {
    const p = op.payload;
    const f = p.fields || {};
    const deps = [];
    const creates = [];
    const add = (list, v) => { if (typeof v === 'string' && v.length === 36) list.push(v); };

    switch (op.type) {
        case 'mill.create': case 'buyer.create': case 'variety.create':
            add(creates, p.uuid); break;
        case 'mill.update': case 'buyer.update': case 'dispatch.respond': case 'loading.loaded': case 'order.complete':
            add(deps, p.target); break;
        case 'sample.create':
            add(creates, p.uuid); add(deps, f.mill_id); add(deps, f.rice_variety_id); add(deps, f.sample_bundle_id); break;
        case 'bundle.create': case 'bundle.add_samples':
            add(op.type === 'bundle.create' ? creates : deps, op.type === 'bundle.create' ? p.uuid : p.target);
            Object.values(f.samples || {}).forEach((row) => { add(creates, row.uuid); add(deps, row.mill_id); add(deps, row.rice_variety_id); });
            break;
        case 'dispatch.create':
            p.pairs.forEach((pair) => { add(creates, pair.uuid); add(deps, pair.sample); add(deps, pair.buyer); });
            break;
        case 'loading.create':
            add(creates, p.uuid); add(deps, p.target); break;
        case 'photo.add':
            add(deps, p.target); break;
    }

    return { deps: [...new Set(deps)], creates };
}

/**
 * Save a change on this device.
 * @param photos  [{ uuid, blob, name, target_type }] photo blobs to upload after the record
 */
export async function enqueue(db, { type, payload, summary, photos = [] }) {
    const ops = [{ uuid: uuid(), type, payload, summary }];

    for (const photo of photos) {
        ops.push({
            uuid: photo.uuid,
            type: 'photo.add',
            payload: { uuid: photo.uuid, target_type: photo.target_type, target: photo.target },
            summary: `Photo for ${summary.toLowerCase()}`,
            photo: true,
        });
    }

    await db.transaction('rw', [...RECORD_TABLES.map((t) => db[t]), db.ops, db.photos], async () => {
        for (const op of ops) {
            const { deps, creates } = graph(op);
            await db.ops.add({ ...op, deps, creates, status: 'pending', attempts: 0, created_at: now() });
            await applyLocally(db, op);
        }
        for (const photo of photos) {
            await db.photos.put({ uuid: photo.uuid, op_uuid: photo.uuid, target_uuid: photo.target, target_type: photo.target_type, blob: photo.blob, name: photo.name, size: photo.blob.size, type: photo.blob.type });
        }
    });

    requestPersistence();
    window.dispatchEvent(new CustomEvent('rst:outbox-changed'));

    return ops[0].uuid;
}

/** Queue extra photos for a record that already exists (locally or on the server). */
export async function enqueuePhotos(db, { targetType, target, label, files }) {
    await db.transaction('rw', db.ops, db.photos, async () => {
        for (const file of files) {
            const id = uuid();
            await db.ops.add({
                uuid: id, type: 'photo.add', payload: { uuid: id, target_type: targetType, target },
                summary: `Photo for ${label}`, deps: [target], creates: [], status: 'pending', attempts: 0, created_at: now(),
            });
            await db.photos.put({ uuid: id, op_uuid: id, target_uuid: target, target_type: targetType, blob: file, name: file.name, size: file.size, type: file.type });
        }
    });
    requestPersistence();
    window.dispatchEvent(new CustomEvent('rst:outbox-changed'));
}

/** Replace local records with the server's authoritative copies. */
export async function applyServerRecords(db, items) {
    for (const { type, record } of items || []) {
        if (!db[type]) continue;
        await db[type].put({ ...record, _pending: false, _op: null });
    }
}

/**
 * After the server confirmed a record, re-apply later queued changes to that
 * same record (e.g. a response recorded offline on a dispatch that was itself
 * created offline), so the screen keeps showing the newest local state.
 */
export async function reapplyPending(db, uuids) {
    const touched = new Set(uuids);
    const ops = await db.ops.orderBy('seq').toArray();
    for (const op of ops) {
        if (op.payload?.target && touched.has(op.payload.target) && op.type !== 'photo.add') {
            await applyLocally(db, op);
        }
    }
}

/**
 * Drop an operation the user chose to discard, together with everything that
 * depends on it, and remove the records it created locally.
 */
export async function discard(db, opUuid) {
    const all = await db.ops.orderBy('seq').toArray();
    const doomed = new Set([opUuid]);
    const createdBy = new Set();
    let grew = true;

    while (grew) {
        grew = false;
        for (const op of all) {
            if (doomed.has(op.uuid)) op.creates.forEach((u) => createdBy.add(u));
        }
        for (const op of all) {
            if (!doomed.has(op.uuid) && op.deps.some((d) => createdBy.has(d))) {
                doomed.add(op.uuid);
                grew = true;
            }
        }
    }

    await db.transaction('rw', [...RECORD_TABLES.map((t) => db[t]), db.ops, db.photos, db.meta], async () => {
        for (const table of RECORD_TABLES) {
            await db[table].where('uuid').anyOf([...createdBy]).delete();
            // Records only edited by a discarded op: let the next snapshot restore the server copy.
            await db[table].filter((r) => r._pending && doomed.has(r._op)).modify({ _pending: false, _op: null });
        }
        await db.ops.where('uuid').anyOf([...doomed]).delete();
        await db.photos.where('op_uuid').anyOf([...doomed]).delete();
        // Edited server records need their server copy back.
        await db.meta.put({ key: 'needsFullSnapshot', value: true });
    });

    window.dispatchEvent(new CustomEvent('rst:outbox-changed'));

    return doomed.size;
}

export function dependentsOf(all, opUuid) {
    const op = all.find((o) => o.uuid === opUuid);
    if (!op) return [];
    return all.filter((o) => o.uuid !== opUuid && o.deps.some((d) => op.creates.includes(d)));
}
