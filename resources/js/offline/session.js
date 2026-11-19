/**
 * Which account this device is working for.
 *
 * Online pages publish the signed-in user (meta "app-user"). The marker in
 * localStorage lets the offline shell know whose local database to open.
 * Each account has its own IndexedDB database, so data never mixes between
 * users, and changes are only ever synced under the account that made them.
 */
const KEY = 'rst.activeUser';

export function readActiveUser() {
    try {
        return JSON.parse(localStorage.getItem(KEY) || 'null');
    } catch {
        return null;
    }
}

export function writeActiveUser(user) {
    try {
        localStorage.setItem(KEY, JSON.stringify(user));
    } catch { /* private mode: offline mode simply unavailable */ }
}

export function clearActiveUser() {
    try {
        localStorage.removeItem(KEY);
    } catch { /* ignore */ }
}

/** The user rendered into the current (online) page, if any. */
export function pageUser() {
    const meta = document.querySelector('meta[name="app-user"]');
    if (!meta) return null;
    try {
        return JSON.parse(meta.content);
    } catch {
        return null;
    }
}

/**
 * Called on every online page: the server has just confirmed this session.
 * Returns the active user record.
 */
export function confirmPageUser() {
    const user = pageUser();
    if (!user) return readActiveUser();

    const record = { id: user.id, name: user.name, role: user.role, verifiedAt: Date.now(), locked: false };
    writeActiveUser(record);

    return record;
}

/** Mark that the server confirmed the session (from a background status check). */
export function touchVerified(user) {
    const current = readActiveUser();
    if (current && current.id === user.id) {
        writeActiveUser({ ...current, name: user.name, role: user.role, verifiedAt: Date.now(), locked: false });
    }
}

/** The server says the session is gone: hide local records until the user signs in again. */
export function lockActiveUser() {
    const current = readActiveUser();
    if (current) writeActiveUser({ ...current, locked: true });
}

export function isExpired(user, maxDays) {
    if (!user?.verifiedAt) return true;
    return Date.now() - user.verifiedAt > maxDays * 86400000;
}
