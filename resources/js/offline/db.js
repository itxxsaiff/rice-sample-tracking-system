import Dexie from 'dexie';

/**
 * Local database (IndexedDB) — one per user account.
 *
 * Record tables mirror the server snapshot (relations by uuid). Records
 * created or changed on this device carry `_pending: true` until the server
 * confirms them; snapshots never overwrite pending records.
 *
 * `ops` is the outbox of changes waiting to be sent, in the order they were
 * made (which is also dependency order). `photos` holds photo blobs until
 * the server confirms they are stored.
 */
const cache = new Map();

export const RECORD_TABLES = ['mills', 'buyers', 'varieties', 'bundles', 'samples', 'dispatches', 'loadings'];

export function openDb(userId) {
    if (!userId) throw new Error('No user for local database');
    if (cache.has(userId)) return cache.get(userId);

    const db = new Dexie(`rst-user-${userId}`);
    db.version(1).stores({
        mills: 'uuid, id, name, city',
        buyers: 'uuid, id, name, city',
        varieties: 'uuid, id, name',
        bundles: 'uuid, id, bundle_date',
        samples: 'uuid, id, mill_uuid, variety_uuid, bundle_uuid, collection_date',
        dispatches: 'uuid, id, sample_uuid, buyer_uuid, status, loading_status, dispatch_date',
        loadings: 'uuid, id, dispatch_uuid, status',
        photos: 'uuid, op_uuid, target_uuid',
        ops: '++seq, &uuid, status',
        meta: 'key',
    });

    cache.set(userId, db);
    return db;
}

export async function getMeta(db, key, fallback = null) {
    const row = await db.meta.get(key);
    return row ? row.value : fallback;
}

export function setMeta(db, key, value) {
    return db.meta.put({ key, value });
}

export async function deleteUserDb(userId) {
    const db = cache.get(userId);
    if (db) db.close();
    cache.delete(userId);
    await Dexie.delete(`rst-user-${userId}`);
}

export function uuid() {
    if (crypto.randomUUID) return crypto.randomUUID();
    // RFC 4122 v4 fallback
    const b = crypto.getRandomValues(new Uint8Array(16));
    b[6] = (b[6] & 0x0f) | 0x40;
    b[8] = (b[8] & 0x3f) | 0x80;
    const h = [...b].map((x) => x.toString(16).padStart(2, '0')).join('');
    return `${h.slice(0, 8)}-${h.slice(8, 12)}-${h.slice(12, 16)}-${h.slice(16, 20)}-${h.slice(20)}`;
}

/** Ask the browser not to evict our data (important on iOS). */
export async function requestPersistence() {
    try {
        if (navigator.storage?.persist && !(await navigator.storage.persisted())) {
            await navigator.storage.persist();
        }
    } catch { /* not supported */ }
}

export async function storageEstimate() {
    try {
        if (!navigator.storage?.estimate) return null;
        const { usage, quota } = await navigator.storage.estimate();
        const persisted = navigator.storage.persisted ? await navigator.storage.persisted() : false;
        return { usage, quota, persisted };
    } catch {
        return null;
    }
}
