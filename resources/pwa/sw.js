/*
 * Service worker for the Rice Sample Tracking offline app.
 * Served by Laravel at /sw.js with VERSION and PRECACHE filled in from the
 * current build, so every deployment installs a fresh worker automatically.
 *
 * Caching policy
 *  - Precache: built CSS/JS, fonts, icons, logo and the public offline shell.
 *    None of these contain user data.
 *  - Page navigations: network first; if the server can't be reached the
 *    offline shell is served, which renders from the device's local database.
 *  - Signed-in HTML, /sync/*, /lookup/*, photos, login/logout: never cached.
 *  - IndexedDB (pending offline changes) is never touched by this worker.
 */
const VERSION = '__VERSION__';
const PRECACHE = __PRECACHE__;
const STATIC_CACHE = `rst-static-${VERSION}`;
const SHELL_URL = '/offline-shell';
const NAV_TIMEOUT_MS = 6000;

const NEVER_CACHE = [/^\/sync\//, /^\/lookup\//, /^\/login/, /^\/logout/, /\/photo$/, /^\/loading-photos\//, /^\/sw\.js$/, /\/pdf$/];
const STATIC_ASSET = /^\/(build\/|images\/|icon-|apple-touch-icon|favicon|site\.webmanifest)/;

self.addEventListener('install', (event) => {
    event.waitUntil((async () => {
        const cache = await caches.open(STATIC_CACHE);
        // credentials: 'omit' so the cached shell is the anonymous version.
        await Promise.all(PRECACHE.map(async (url) => {
            const response = await fetch(new Request(url, { credentials: url === SHELL_URL ? 'omit' : 'same-origin', cache: 'reload' }));
            if (!response.ok) throw new Error(`Precache failed for ${url}: ${response.status}`);
            await cache.put(url, response);
        }));
        await self.skipWaiting();
    })());
});

self.addEventListener('activate', (event) => {
    event.waitUntil((async () => {
        const keys = await caches.keys();
        await Promise.all(keys.filter((k) => k.startsWith('rst-static-') && k !== STATIC_CACHE).map((k) => caches.delete(k)));
        await self.clients.claim();
    })());
});

self.addEventListener('message', (event) => {
    if (event.data === 'version') event.source?.postMessage({ type: 'sw-version', version: VERSION });
});

function withTimeout(promise, ms) {
    return new Promise((resolve, reject) => {
        const timer = setTimeout(() => reject(new Error('timeout')), ms);
        promise.then((r) => { clearTimeout(timer); resolve(r); }, (e) => { clearTimeout(timer); reject(e); });
    });
}

async function shellResponse() {
    const cached = await caches.match(SHELL_URL, { cacheName: STATIC_CACHE }) || await caches.match(SHELL_URL);
    return cached || new Response('<h1>Offline</h1><p>This page is not available offline yet. Open the app once while online.</p>', {
        status: 503, headers: { 'Content-Type': 'text/html; charset=utf-8' },
    });
}

self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);

    if (request.method !== 'GET' || url.origin !== self.location.origin) return;
    if (NEVER_CACHE.some((re) => re.test(url.pathname))) return;

    // Page loads: network first, offline shell when the server is unreachable.
    if (request.mode === 'navigate') {
        event.respondWith((async () => {
            if (self.navigator && self.navigator.onLine === false) return shellResponse();
            try {
                return await withTimeout(fetch(request), NAV_TIMEOUT_MS);
            } catch {
                return shellResponse();
            }
        })());
        return;
    }

    // Versioned/public static files: cache first.
    if (STATIC_ASSET.test(url.pathname)) {
        event.respondWith((async () => {
            const cached = await caches.match(request);
            if (cached) return cached;
            const response = await fetch(request);
            if (response.ok && url.pathname.startsWith('/build/')) {
                const cache = await caches.open(STATIC_CACHE);
                cache.put(request, response.clone());
            }
            return response;
        })());
    }
    // Everything else (signed-in data) goes straight to the network, uncached.
});
