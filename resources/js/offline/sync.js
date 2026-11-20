import { RECORD_TABLES, getMeta, openDb, setMeta } from './db';
import { applyServerRecords, reapplyPending } from './outbox';
import { isExpired, lockActiveUser, readActiveUser, touchVerified } from './session';

/**
 * Sync engine.
 *
 * Runs whenever the app is open: on start, when it comes back to the
 * foreground, when the connection returns, on a timer while changes are
 * waiting, and when the user taps "Sync now". (iOS cannot sync while the
 * app is closed, so nothing here relies on Background Sync.)
 *
 * Push: operations are sent one at a time in the order they were made.
 * Each response says exactly what to do (applied / rejected / conflict …).
 * Pull: then the latest server data is downloaded (incrementally).
 */

const STATUS_TIMEOUT = 6000;
const OP_TIMEOUT = 45000;
const PULL_EVERY_MS = 5 * 60 * 1000;
const FULL_SNAPSHOT_EVERY_MS = 24 * 60 * 60 * 1000;
const MAX_BACKOFF_MS = 5 * 60 * 1000;

let running = null;
let state = { phase: 'idle', pending: 0, failed: 0, conflicts: 0, lastSync: null, message: '', progress: null };
let csrf = null;
let retryTimer = null;
let pushedCount = 0;

export function getState() {
    return state;
}

function setState(patch) {
    state = { ...state, ...patch };
    window.dispatchEvent(new CustomEvent('rst:sync-state', { detail: state }));
}

function fetchWithTimeout(url, options, ms) {
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), ms);
    return fetch(url, { ...options, signal: controller.signal, credentials: 'same-origin' }).finally(() => clearTimeout(timer));
}

/** Real reachability check — navigator.onLine is not trusted on its own. */
export async function checkServer() {
    if (navigator.onLine === false) return { reachable: false };
    try {
        const res = await fetchWithTimeout('/sync/status', { headers: { Accept: 'application/json' }, cache: 'no-store' }, STATUS_TIMEOUT);
        if (!res.ok) return { reachable: false };
        const data = await res.json();
        csrf = data.csrf;
        return { reachable: true, ...data };
    } catch {
        return { reachable: false };
    }
}

export async function counts(db) {
    const ops = await db.ops.toArray();
    return {
        pending: ops.filter((o) => ['pending', 'syncing', 'blocked'].includes(o.status)).length,
        failed: ops.filter((o) => o.status === 'failed').length,
        conflicts: ops.filter((o) => o.status === 'conflict').length,
        total: ops.length,
    };
}

async function refreshCounts(db) {
    const c = await counts(db);
    setState({ ...c, lastSync: await getMeta(db, 'lastSync') });
    return c;
}

function phaseFor(c, reachable) {
    if (c.conflicts) return 'conflict';
    if (c.failed) return 'failed';
    if (!reachable) return 'offline';
    return c.pending ? 'pending' : 'synced';
}

/** Run one sync cycle (deduplicated: concurrent calls share the same run). */
export function syncNow({ pull = true } = {}) {
    if (!running) {
        running = run(pull).finally(() => { running = null; });
    }
    return running;
}

async function run(doPull) {
    const user = readActiveUser();
    if (!user) return;
    const db = openDb(user.id);
    clearTimeout(retryTimer);

    const before = await refreshCounts(db);
    const server = await checkServer();

    if (!server.reachable) {
        setState({ phase: phaseFor(before, false), message: 'No connection to the server.' });
        scheduleRetry(db);
        return;
    }
    if (!server.authenticated) {
        lockActiveUser();
        setState({ phase: 'signin', message: 'Please sign in again. Your unsynced changes are kept on this device.' });
        return;
    }
    if (server.user.id !== user.id) {
        setState({ phase: 'wrong_user', message: `Changes on this device belong to ${user.name}. Sign in as ${user.name} to sync them.` });
        return;
    }

    touchVerified(server.user);
    setState({ phase: 'syncing', message: '' });

    const pushed = await push(db);
    if (pushed === 'auth') {
        lockActiveUser();
        setState({ phase: 'signin', message: 'Your session expired. Sign in again to finish syncing.' });
        return;
    }

    if (doPull && pushed !== 'network') {
        try {
            await pull(db);
        } catch { /* a failed download never affects pending changes */ }
    }

    if (pushedCount) window.dispatchEvent(new CustomEvent('rst:synced', { detail: { count: pushedCount } }));
    pushedCount = 0;

    const after = await refreshCounts(db);
    setState({ phase: phaseFor(after, pushed !== 'network'), progress: null, message: pushed === 'network' ? 'Connection lost while syncing. Will retry.' : '' });
    if (after.pending) scheduleRetry(db);
}

function scheduleRetry(db) {
    clearTimeout(retryTimer);
    counts(db).then(async (c) => {
        if (!c.pending) return;
        const attempts = (await getMeta(db, 'failedRuns', 0)) + 1;
        await setMeta(db, 'failedRuns', attempts);
        const delay = Math.min(MAX_BACKOFF_MS, 15000 * 2 ** Math.min(attempts - 1, 5)) * (0.8 + Math.random() * 0.4);
        retryTimer = setTimeout(() => { if (document.visibilityState === 'visible') syncNow(); }, delay);
    });
}

/** Send queued operations. Returns 'ok' | 'network' | 'auth'. */
async function push(db) {
    const ops = await db.ops.orderBy('seq').toArray();
    const unsynced = new Set();
    for (const op of ops) op.creates.forEach((u) => unsynced.add(u));

    const queue = ops.filter((o) => ['pending', 'syncing', 'blocked'].includes(o.status));
    let done = 0;

    for (const op of queue) {
        // Wait for the records this change needs; skip if one of them failed.
        const blockedBy = ops.find((o) => o !== op && o.seq < op.seq && ['failed', 'conflict', 'blocked'].includes(o.status) && o.creates.some((u) => op.deps.includes(u)));
        if (blockedBy) {
            await db.ops.update(op.seq, { status: 'blocked', error: `Waiting for: ${blockedBy.summary}` });
            op.status = 'blocked';
            continue;
        }

        setState({ progress: { done, total: queue.length, label: op.summary } });
        await db.ops.update(op.seq, { status: 'syncing' });

        const body = new FormData();
        body.append('operation', JSON.stringify({ uuid: op.uuid, type: op.type, payload: op.payload }));
        if (op.type === 'photo.add') {
            const photo = await db.photos.get(op.uuid);
            if (!photo) {
                await db.ops.delete(op.seq); // nothing left to upload
                continue;
            }
            body.append('photo', photo.blob, photo.name || 'photo.jpg');
        }

        let res;
        let data = {};
        try {
            res = await fetchWithTimeout('/sync/operations', {
                method: 'POST', body, headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf || '', 'X-Requested-With': 'XMLHttpRequest' },
            }, OP_TIMEOUT);
            data = await res.json().catch(() => ({}));
        } catch {
            await db.ops.update(op.seq, { status: 'pending', attempts: (op.attempts || 0) + 1, error: 'Connection interrupted' });
            return 'network';
        }

        if (res.status === 401 || res.status === 419) {
            await db.ops.update(op.seq, { status: 'pending' });
            return 'auth';
        }

        if (res.ok && data.status === 'applied') {
            await db.transaction('rw', [...RECORD_TABLES.map((t) => db[t]), db.ops, db.photos], async () => {
                await applyServerRecords(db, data.records);
                await db.ops.delete(op.seq);
                if (op.type === 'photo.add') await db.photos.delete(op.uuid);
                op.creates.forEach((u) => unsynced.delete(u));
                await reapplyPending(db, data.records.map((r) => r.record.uuid));
            });
            op.status = 'done';
            done++;
            pushedCount++;
            await setMeta(db, 'failedRuns', 0);
            continue;
        }

        if (data.status === 'conflict') {
            await db.ops.update(op.seq, { status: 'conflict', error: data.message, server: data.server });
            op.status = 'conflict';
        } else if (data.status === 'missing_reference' && unsynced.has(data.reference)) {
            await db.ops.update(op.seq, { status: 'blocked', error: data.message });
            op.status = 'blocked';
        } else if (['rejected', 'forbidden', 'missing_reference'].includes(data.status)) {
            await db.ops.update(op.seq, { status: 'failed', error: data.message, errors: data.errors || null });
            op.status = 'failed';
        } else {
            // Server error (5xx, maintenance…): keep it and retry later.
            await db.ops.update(op.seq, { status: 'pending', attempts: (op.attempts || 0) + 1, error: `Server error (${res.status})` });
            return 'network';
        }
    }

    return 'ok';
}

/** Download server changes into the local tables. */
export async function pull(db, { force = false } = {}) {
    const lastFull = await getMeta(db, 'lastFullSnapshot');
    const needsFull = force || !lastFull || (await getMeta(db, 'needsFullSnapshot')) || Date.now() - lastFull > FULL_SNAPSHOT_EVERY_MS;
    const since = needsFull ? null : await getMeta(db, 'lastSnapshotServerTime');

    const url = since ? `/sync/snapshot?since=${encodeURIComponent(since)}` : '/sync/snapshot';
    const res = await fetchWithTimeout(url, { headers: { Accept: 'application/json' }, cache: 'no-store' }, 60000);
    if (!res.ok) throw new Error(`snapshot ${res.status}`);
    const data = await res.json();

    await db.transaction('rw', [...RECORD_TABLES.map((t) => db[t]), db.meta], async () => {
        for (const table of RECORD_TABLES) {
            const pendingUuids = new Set((await db[table].filter((r) => r._pending).toArray()).map((r) => r.uuid));
            const rows = (data[table] || []).filter((r) => !pendingUuids.has(r.uuid)).map((r) => ({ ...r, _pending: false, _op: null }));
            if (rows.length) await db[table].bulkPut(rows);

            // Drop records the server no longer has (never pending local ones).
            const keep = new Set(data.keep?.[table] || []);
            await db[table].filter((r) => !r._pending && !keep.has(r.uuid)).delete();
        }
        await db.meta.bulkPut([
            { key: 'lastSnapshotServerTime', value: data.server_time },
            { key: 'lastSync', value: Date.now() },
            { key: 'historyDays', value: data.history_days },
            ...(needsFull ? [{ key: 'lastFullSnapshot', value: Date.now() }, { key: 'needsFullSnapshot', value: false }] : []),
        ]);
    });

    window.dispatchEvent(new CustomEvent('rst:data-changed'));
}

/** Start background triggers (call once per page). */
export function startSyncTriggers() {
    const user = readActiveUser();
    if (!user) return;
    const db = openDb(user.id);

    const maybeSync = async () => {
        if (document.visibilityState !== 'visible') return;
        const last = await getMeta(db, 'lastSync');
        const c = await counts(db);
        if (c.pending || !last || Date.now() - last > PULL_EVERY_MS) syncNow();
    };

    window.addEventListener('online', () => syncNow());
    window.addEventListener('offline', () => refreshCounts(db).then((c) => setState({ phase: phaseFor(c, false) })));
    document.addEventListener('visibilitychange', maybeSync);
    window.addEventListener('rst:outbox-changed', () => refreshCounts(db).then(() => syncNow({ pull: false })));
    setInterval(maybeSync, 60000);

    refreshCounts(db).then((c) => setState({ phase: phaseFor(c, navigator.onLine !== false) }));
    maybeSync();
}

export function isLocked(user, maxDays = 30) {
    return !user || user.locked || isExpired(user, maxDays);
}
