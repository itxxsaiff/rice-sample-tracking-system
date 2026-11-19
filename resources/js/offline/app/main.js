import { mountIndicator } from '../indicator';
import { readActiveUser } from '../session';
import { isLocked, startSyncTriggers } from '../sync';

export function bootOfflineApp() {
    const root = document.getElementById('offline-root');
    const user = readActiveUser();
    if (isLocked(user)) {
        root.innerHTML = '<div class="empty-state"><h3>Sign in required</h3><p>Connect to the internet and sign in to use the app on this device.</p><a class="btn btn-primary" href="/login">Sign in</a></div>';
        return;
    }
    startSyncTriggers();
    mountIndicator();
    root.innerHTML = '<div class="empty-state"><h3>Working offline</h3></div>';
}
