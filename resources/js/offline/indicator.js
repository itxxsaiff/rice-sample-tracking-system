import { Offcanvas } from 'bootstrap';
import { escapeHtml } from '../modules/http';
import { openDb, storageEstimate } from './db';
import { dependentsOf, discard } from './outbox';
import { readActiveUser } from './session';
import { getState, syncNow } from './sync';

/** Small status pill in the top bar + a panel with details and actions. */

const PHASES = {
    idle: ['cloud', 'Sync', 'slate'],
    synced: ['cloud-check', 'Synced', 'green'],
    offline: ['wifi-off', 'Offline', 'slate'],
    pending: ['cloud-arrow-up', 'To sync', 'gold'],
    syncing: ['arrow-repeat', 'Syncing…', 'blue'],
    failed: ['exclamation-octagon', 'Sync failed', 'red'],
    conflict: ['exclamation-triangle', 'Action needed', 'gold'],
    signin: ['person-lock', 'Sign in to sync', 'red'],
    wrong_user: ['person-exclamation', 'Other account', 'red'],
};

const STATUS_TEXT = {
    synced: 'Online — everything is synced.',
    offline: 'Offline — working on this device. Changes sync automatically when the internet returns.',
    pending: 'Changes are waiting to be sent to the server.',
    syncing: 'Sending changes to the server…',
    failed: 'Some changes were not accepted by the server. Review them below.',
    conflict: 'Someone else changed a record you also edited. Choose which version to keep.',
    signin: 'Your session has ended. Sign in again to sync. Nothing is lost — changes stay on this device.',
    wrong_user: '',
};

const FIELD_LABELS = {
    name: 'Name', city: 'City', contact_person: 'Contact', phone: 'Phone', address: 'Address', notes: 'Notes', status: 'Response',
    confirmed_quantity: 'Quantity', confirmed_quantity_unit: 'Unit', rate: 'Rate', rate_unit: 'Rate per', packing_type: 'Packing',
    packing_details: 'Packing details', confirmation_date: 'Confirmed on', rejection_reason: 'Reason', rejection_date: 'Rejected on',
};

let panelEl = null;

function ago(ts) {
    if (!ts) return 'never';
    const s = Math.round((Date.now() - ts) / 1000);
    if (s < 60) return 'just now';
    if (s < 3600) return `${Math.round(s / 60)} min ago`;
    if (s < 86400) return `${Math.round(s / 3600)} h ago`;
    return new Date(ts).toLocaleDateString();
}

function renderPill(target, st) {
    const [icon, label, tone] = PHASES[st.phase] || PHASES.idle;
    const n = st.pending + st.failed + st.conflicts;
    const count = n && st.phase !== 'synced' ? `<span class="sync-pill-count">${n}</span>` : '';
    const text = st.phase === 'offline' && st.pending ? 'Offline' : label;
    target.innerHTML = `<button type="button" class="sync-pill tone-${tone}" aria-label="Sync status: ${escapeHtml(text)}${n ? `, ${n} change(s) on this device` : ''}">
        <i class="bi bi-${icon} ${st.phase === 'syncing' ? 'spin' : ''}"></i><span class="sync-pill-label">${escapeHtml(text)}</span>${count}</button>`;
    target.querySelector('button').addEventListener('click', openPanel);
}

function ensurePanel() {
    if (panelEl) return panelEl;
    panelEl = document.createElement('div');
    panelEl.className = 'offcanvas offcanvas-end sync-panel';
    panelEl.tabIndex = -1;
    panelEl.id = 'syncPanel';
    panelEl.innerHTML = `<div class="offcanvas-header border-bottom"><h5 class="offcanvas-title fw-semibold"><i class="bi bi-cloud-arrow-up me-2"></i>Sync</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button></div>
        <div class="offcanvas-body p-0" data-sync-body></div>`;
    document.body.appendChild(panelEl);
    panelEl.addEventListener('click', onPanelClick);
    return panelEl;
}

function openPanel() {
    ensurePanel();
    renderPanel();
    Offcanvas.getOrCreateInstance(panelEl).show();
}

async function renderPanel() {
    if (!panelEl) return;
    const user = readActiveUser();
    const body = panelEl.querySelector('[data-sync-body]');
    if (!user) {
        body.innerHTML = '<p class="p-3 text-muted">Sign in to use offline mode.</p>';
        return;
    }
    const db = openDb(user.id);
    const st = getState();
    const ops = await db.ops.orderBy('seq').toArray();
    const est = await storageEstimate();
    const [icon, label, tone] = PHASES[st.phase] || PHASES.idle;

    const opRow = (op) => {
        const when = new Date(op.created_at).toLocaleString([], { dateStyle: 'medium', timeStyle: 'short' });
        const badge = { pending: ['Waiting', 'pending'], syncing: ['Sending', 'in_progress'], blocked: ['Waiting', 'pending'], failed: ['Not accepted', 'rejected'], conflict: ['Conflict', 'pending'] }[op.status] || [op.status, 'pending'];
        let extra = '';
        if (op.status === 'failed') {
            const details = op.errors ? Object.values(op.errors).flat().map((e) => `<li>${escapeHtml(e)}</li>`).join('') : '';
            extra = `<div class="small text-danger mt-1">${escapeHtml(op.error || '')}</div>${details ? `<ul class="small text-danger mb-1 ps-3">${details}</ul>` : ''}
                <div class="d-flex gap-2 mt-2"><button class="btn btn-sm btn-light" data-retry="${op.seq}"><i class="bi bi-arrow-repeat"></i> Retry</button>
                <button class="btn btn-sm btn-outline-danger" data-discard="${op.uuid}"><i class="bi bi-trash"></i> Discard</button></div>`;
        } else if (op.status === 'conflict') {
            const server = op.server?.record || {};
            const mine = op.payload.fields || {};
            const rows = Object.keys(mine).filter((k) => FIELD_LABELS[k] && String(mine[k] ?? '') !== String(server[k] ?? ''))
                .map((k) => `<tr><th class="fw-medium">${FIELD_LABELS[k]}</th><td>${escapeHtml(server[k] ?? '—')}</td><td>${escapeHtml(mine[k] ?? '—')}</td></tr>`).join('');
            extra = `<div class="small mt-1">${escapeHtml(op.error || '')}</div>
                ${rows ? `<table class="table table-sm small mt-2 mb-2 conflict-table"><thead><tr><th></th><th>Server now</th><th>Your change</th></tr></thead><tbody>${rows}</tbody></table>` : ''}
                <div class="d-flex gap-2 flex-wrap"><button class="btn btn-sm btn-light" data-keep-server="${op.uuid}">Keep server version</button>
                <button class="btn btn-sm btn-primary" data-apply-mine="${op.seq}">Use my change</button></div>`;
        } else if (op.status === 'blocked' && op.error) {
            extra = `<div class="small text-muted mt-1">${escapeHtml(op.error)}</div>`;
        }
        return `<li class="list-group-item py-3"><div class="d-flex justify-content-between gap-2"><div class="min-w-0"><div class="fw-medium">${escapeHtml(op.summary)}</div>
            <div class="small text-muted">Saved on this device · ${when}</div></div><span><span class="status-badge status-${badge[1]}">${badge[0]}</span></span></div>${extra}</li>`;
    };

    const attention = ops.filter((o) => ['failed', 'conflict'].includes(o.status));
    const waiting = ops.filter((o) => !['failed', 'conflict'].includes(o.status));

    body.innerHTML = `
        <div class="p-3 border-bottom">
            <div class="d-flex align-items-center gap-3 mb-2"><span class="stat-icon tone-${tone}"><i class="bi bi-${icon}"></i></span>
                <div><div class="fw-semibold">${escapeHtml(label)}</div><div class="small text-muted">Last synced ${ago(st.lastSync)} · ${escapeHtml(user.name)}</div></div></div>
            <p class="small mb-3">${escapeHtml(st.message || STATUS_TEXT[st.phase] || '')}</p>
            ${st.progress ? `<div class="small text-muted mb-2">Sending ${st.progress.done + 1} of ${st.progress.total}: ${escapeHtml(st.progress.label)}</div>` : ''}
            <div class="d-flex gap-2">
                <button class="btn btn-primary flex-fill" data-sync-now ${st.phase === 'syncing' ? 'disabled' : ''}><i class="bi bi-arrow-repeat"></i> Sync now</button>
                ${st.phase === 'signin' || st.phase === 'wrong_user' ? '<a class="btn btn-light flex-fill" href="/login">Sign in</a>' : ''}
            </div>
        </div>
        ${attention.length ? `<div class="px-3 pt-3 small-caps">Needs your attention (${attention.length})</div><ul class="list-group list-group-flush">${attention.map(opRow).join('')}</ul>` : ''}
        <div class="px-3 pt-3 small-caps">Waiting to sync (${waiting.length})</div>
        ${waiting.length ? `<ul class="list-group list-group-flush">${waiting.map(opRow).join('')}</ul>` : '<p class="px-3 small text-muted">Nothing waiting. Everything on this device has reached the server.</p>'}
        <div class="p-3 small text-muted border-top mt-2">
            ${est ? `Device storage used: ${(est.usage / 1048576).toFixed(1)} MB${est.persisted ? ' · protected from automatic clean-up' : ''}.<br>` : ''}
            Sync runs while the app is open. On iPhone, keep the app installed on the Home Screen so offline data is kept safely.
        </div>`;
}

async function onPanelClick(e) {
    const user = readActiveUser();
    if (!user) return;
    const db = openDb(user.id);
    const t = e.target.closest('button');
    if (!t) return;

    if (t.matches('[data-sync-now]')) {
        syncNow();
    } else if (t.dataset.retry) {
        await db.ops.update(Number(t.dataset.retry), { status: 'pending', error: null, errors: null });
        syncNow({ pull: false });
    } else if (t.dataset.applyMine) {
        const op = await db.ops.get(Number(t.dataset.applyMine));
        await db.ops.update(op.seq, { status: 'pending', error: null, payload: { ...op.payload, force: true } });
        syncNow({ pull: false });
    } else if (t.dataset.keepServer || t.dataset.discard) {
        const opUuid = t.dataset.keepServer || t.dataset.discard;
        const all = await db.ops.orderBy('seq').toArray();
        const deps = dependentsOf(all, opUuid);
        const msg = t.dataset.keepServer
            ? 'Keep the server version and drop your change?'
            : `Discard this change from this device?${deps.length ? ` ${deps.length} later change(s) that depend on it will also be discarded.` : ''} This cannot be undone.`;
        if (!window.confirm(msg)) return;
        await discard(db, opUuid);
        syncNow();
    }
    renderPanel();
}

// Confirmation only once the server has really accepted the changes.
window.addEventListener('rst:synced', (e) => {
    import('../modules/ui').then(({ toast }) => toast(`${e.detail.count} change${e.detail.count > 1 ? 's' : ''} synced with the server.`, 'success'));
});

export function mountIndicator() {
    const targets = document.querySelectorAll('[data-sync-indicator]');
    if (!targets.length) return;
    const update = () => {
        targets.forEach((el) => renderPill(el, getState()));
        if (panelEl?.classList.contains('show')) renderPanel();
    };
    window.addEventListener('rst:sync-state', update);
    update();
}

export { openPanel as openSyncPanel };
