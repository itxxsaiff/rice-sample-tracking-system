import { mountIndicator, openSyncPanel } from '../indicator';
import { openDb } from '../db';
import { readActiveUser } from '../session';
import { getState, isLocked, startSyncTriggers } from '../sync';
import { loadContext } from './store';
import { toast } from '../../modules/ui';
import { h } from './util';
import { directoryForm, directoryList, directoryShow } from './views/directory';
import { home } from './views/home';
import { dispatchList, dispatchNew, dispatchShow, loadingIndex, loadingNew, loadingShow, respondForm } from './views/orders';
import { bundleNew, bundleShow, bundlesList, sampleNew, sampleShow, samplesList } from './views/samples';

/**
 * Offline app: served by the service worker when the server can't be
 * reached. Screens render from the device's local database; navigation
 * happens on the device (#/…) so it never waits for the network.
 */

const ROUTES = [
    ['/', home],
    ['/samples', samplesList], ['/samples/new', sampleNew], ['/samples/:id', sampleShow],
    ['/bundles', bundlesList], ['/bundles/new', bundleNew], ['/bundles/:id', bundleShow],
    ['/mills', directoryList('mill')], ['/mills/new', directoryForm('mill')], ['/mills/:id/edit', directoryForm('mill')], ['/mills/:id', directoryShow('mill')],
    ['/buyers', directoryList('buyer')], ['/buyers/new', directoryForm('buyer')], ['/buyers/:id/edit', directoryForm('buyer')], ['/buyers/:id', directoryShow('buyer')],
    ['/dispatches', dispatchList], ['/dispatches/new', dispatchNew], ['/dispatches/:id/respond', respondForm], ['/dispatches/:id/load', loadingNew], ['/dispatches/:id', dispatchShow],
    ['/loading', loadingIndex], ['/loadings/:id', loadingShow],
];

const NAV = [
    ['', [['/', 'Dashboard', 'grid-1x2']]],
    ['Samples', [['/samples', 'Samples', 'basket'], ['/bundles', 'Bundles', 'boxes']]],
    ['Orders', [['/dispatches', 'Dispatches', 'send'], ['/loading', 'Loading', 'truck']]],
    ['Directory', [['/mills', 'Rice Mills', 'buildings'], ['/buyers', 'Buyers', 'people']]],
];

function match(path) {
    for (const [pattern, view] of ROUTES) {
        const keys = [];
        const re = new RegExp(`^${pattern.replace(/:(\w+)/g, (_, k) => { keys.push(k); return '([^/]+)'; })}$`);
        const m = path.match(re);
        if (m) return { view, params: Object.fromEntries(keys.map((k, i) => [k, decodeURIComponent(m[i + 1])])) };
    }
    return null;
}

/** Map the real URL the user opened (e.g. /samples/create, /mills/12) to an offline route. */
async function initialRoute(user) {
    const path = location.pathname.replace(/\/+$/, '') || '/';
    const simple = { '/samples/create': '/samples/new', '/bundles/create': '/bundles/new', '/dispatches/create': '/dispatches/new', '/mills/create': '/mills/new', '/buyers/create': '/buyers/new', '/records': '/dispatches', '/varieties': '/', '/search': '/' };
    if (simple[path]) return simple[path];

    const m = path.match(/^\/(samples|bundles|mills|buyers|dispatches|loadings)\/(\d+)(\/edit|\/respond|\/loadings\/create)?$/);
    if (m) {
        const table = { samples: 'samples', bundles: 'bundles', mills: 'mills', buyers: 'buyers', dispatches: 'dispatches', loadings: 'loadings' }[m[1]];
        const record = await openDb(user.id)[table].where('id').equals(Number(m[2])).first();
        if (record) {
            const suffix = { '/edit': '/edit', '/respond': '/respond', '/loadings/create': '/load' }[m[3]] || '';
            return `/${m[1]}/${record.uuid}${suffix}`;
        }
        return `/${m[1] === 'loadings' ? 'loading' : m[1]}`;
    }
    const m2 = path.match(/^\/dispatches\/(\d+)\/loadings\/create$/);
    if (m2) return '/loading';
    return match(path) ? path : '/';
}

function buildNav(active) {
    const html = NAV.map(([heading, links]) => `${heading ? `<div class="sidebar-heading">${heading}</div>` : ''}${links.map(([href, label, icon]) => {
        const on = href === '/' ? active === '/' : active.startsWith(href);
        return `<a href="#${href}" class="sidebar-link ${on ? 'active' : ''}"><i class="bi bi-${icon}"></i><span>${label}</span></a>`;
    }).join('')}`).join('');
    document.querySelector('[data-offline-nav]').innerHTML = `${html}<div class="sidebar-heading">Online only</div>
        <div class="sidebar-link" style="opacity:.55"><i class="bi bi-journal-text"></i><span>Records &amp; user management</span></div>`;
    document.querySelectorAll('.bottom-nav a').forEach((a) => {
        const href = a.getAttribute('href').replace(/^#/, '');
        a.classList.toggle('active', href === '/' ? active === '/' : active.startsWith(href));
    });
}

let rendering = false;

async function render() {
    if (rendering) return;
    rendering = true;
    try {
        const root = document.getElementById('offline-root');
        const raw = location.hash.slice(1) || '/';
        if (raw === '/sync') { history.replaceState(null, '', '#/'); openSyncPanel(); }
        const [path, qs] = (location.hash.slice(1) || '/').split('?');
        const found = match(path) || match('/');
        const ctx = await loadContext();
        const keep = document.activeElement?.name;
        const result = await found.view(ctx, found.params, new URLSearchParams(qs || ''));

        root.innerHTML = result.html;
        document.title = `${result.title} · Rice Samples`;
        document.querySelector('[data-page-title]').textContent = result.title;
        const back = document.querySelector('[data-back]');
        back.setAttribute('href', result.back || '#/');
        back.classList.toggle('invisible', path === '/');
        document.body.classList.toggle('has-action-bar', !!result.actionBar);
        document.body.dataset.editing = result.isForm ? '1' : '';
        buildNav(path);
        result.mount?.(root);

        if (result.keepFocus && keep === 'q') {
            const el = root.querySelector(result.keepFocus);
            if (el) { el.focus(); el.setSelectionRange(el.value.length, el.value.length); }
        } else if (!result.keepFocus) {
            window.scrollTo(0, 0);
        }
    } finally {
        rendering = false;
    }
}

function onlineBanner() {
    const banner = document.createElement('div');
    banner.className = 'offline-banner d-none';
    banner.innerHTML = '<i class="bi bi-wifi"></i><span>You\'re back online.</span><a href="/" class="btn btn-sm btn-light">Open full app</a>';
    document.querySelector('.app-main').prepend(banner);
    window.addEventListener('rst:sync-state', () => {
        const st = getState();
        banner.classList.toggle('d-none', !['synced', 'pending', 'syncing'].includes(st.phase));
    });
}

export async function bootOfflineApp() {
    const root = document.getElementById('offline-root');
    const user = readActiveUser();
    const maxDays = window.RST_CONFIG?.max_days_without_server || 30;

    if (isLocked(user, maxDays)) {
        document.querySelector('[data-page-title]').textContent = 'Sign in required';
        root.innerHTML = `<div class="empty-state"><div class="empty-icon"><i class="bi bi-person-lock"></i></div><h3>${user ? 'Please sign in again' : 'Not signed in on this device'}</h3>
            <p class="mb-3">${user ? 'For security, offline data is hidden until you sign in again with an internet connection. Unsynced changes are kept safely and will sync after you sign in.' : 'Connect to the internet and sign in once to use the app offline on this device.'}</p>
            <a class="btn btn-primary" href="/login">Sign in</a></div>`;
        document.querySelector('.bottom-nav')?.classList.add('d-none');
        return;
    }

    document.querySelector('[data-user-name]').textContent = user.name;
    document.querySelector('[data-user-initials]').textContent = user.name.split(' ').filter(Boolean).slice(0, 2).map((p) => p[0].toUpperCase()).join('');

    if (!location.hash) history.replaceState(null, '', `#${await initialRoute(user)}`);

    window.addEventListener('hashchange', render);
    window.addEventListener('rst:rerender', render);
    // Fresh server data arrived: refresh list/detail screens, never a form being filled in.
    window.addEventListener('rst:data-changed', () => { if (!document.body.dataset.editing) render(); });
    window.addEventListener('rst:outbox-changed', () => { if (!document.body.dataset.editing) setTimeout(render, 50); });

    startSyncTriggers();
    mountIndicator();
    onlineBanner();
    try {
        const flash = sessionStorage.getItem('rst.flash');
        if (flash) { sessionStorage.removeItem('rst.flash'); setTimeout(() => toast(flash, 'info'), 300); }
    } catch { /* ignore */ }

    await render().catch((e) => {
        root.innerHTML = `<div class="alert alert-danger">This screen could not be opened offline: ${h(e.message)}</div>`;
    });
}
