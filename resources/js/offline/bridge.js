import { initFormCapture } from './capture';
import { deleteUserDb, openDb } from './db';
import { mountIndicator } from './indicator';
import { clearActiveUser, confirmPageUser, pageUser, readActiveUser } from './session';
import { counts, startSyncTriggers, syncNow } from './sync';

/**
 * Connects the normal (online) pages to the offline layer:
 * registers the service worker, records which account is signed in,
 * keeps the local copy fresh, sends queued changes, and protects unsynced
 * changes when signing out.
 */

export function registerServiceWorker() {
    if (!('serviceWorker' in navigator)) return;
    const secure = location.protocol === 'https:' || ['localhost', '127.0.0.1'].includes(location.hostname);
    if (!secure) return;

    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js', { scope: '/' }).then((reg) => {
            document.addEventListener('visibilitychange', () => {
                if (document.visibilityState === 'visible') reg.update().catch(() => {});
            });
        }).catch(() => { /* offline mode unavailable; online app unaffected */ });
    });
}

function guardLogout() {
    document.addEventListener('submit', async (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || !/\/logout$/.test(new URL(form.action, location.href).pathname) || form.dataset.logoutChecked) return;

        const user = readActiveUser();
        if (!user) return;

        event.preventDefault();
        event.stopImmediatePropagation();

        const db = openDb(user.id);
        await syncNow({ pull: false }).catch(() => {});
        const c = await counts(db);

        if (c.total > 0) {
            const ok = window.confirm(`${c.total} change${c.total > 1 ? 's' : ''} on this device ${c.total > 1 ? 'have' : 'has'} not reached the server yet.\n\nIf you sign out, ${c.total > 1 ? 'they' : 'it'} will stay safely on this device and sync the next time ${user.name} signs in here.\n\nSign out anyway?`);
            if (!ok) return;
        } else {
            await deleteUserDb(user.id).catch(() => {}); // nothing unsynced: remove the local copy
        }

        clearActiveUser();
        form.dataset.logoutChecked = '1';
        form.submit();
    }, true);
}

export function initOfflineBridge() {
    registerServiceWorker();

    if (!pageUser()) return; // guest pages (login): nothing to sync

    confirmPageUser();
    startSyncTriggers();
    mountIndicator();
    guardLogout();
    initFormCapture();
}
