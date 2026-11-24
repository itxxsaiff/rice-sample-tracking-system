import { escapeHtml } from '../../modules/http';
import { toast } from '../../modules/ui';
import { getState } from '../sync';

export const h = escapeHtml;
export const cfg = () => window.RST_CONFIG || {};

export function today() {
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

export function fmtDate(value) {
    if (!value) return '—';
    const d = new Date(String(value).length === 10 ? `${value}T00:00:00` : value);
    return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
}

export function num(value) {
    if (value == null || value === '') return '';
    return Number(value).toLocaleString('en-US', { maximumFractionDigits: 2 });
}

const UNIT_PLURAL = { maund: 'maund', kg: 'kg', ton: 'ton', bag: 'bags' };

export function qty(value, unit) {
    if (value == null || value === '') return null;
    return `${num(value)} ${UNIT_PLURAL[unit] ?? unit ?? ''}`.trim();
}

export function rate(d) {
    if (d.rate == null) return null;
    return `Rs ${num(d.rate)} ${cfg().rate_units?.[d.rate_unit] ?? d.rate_unit ?? ''}`;
}

/** Same stage logic as SampleDispatch::stageKey() / stageLabel(). */
export function stage(d) {
    if (d.status === 'confirmed' && d.loading_status) {
        if (d.loading_status === 'completed') return ['completed', 'Completed'];
        if (d.loading_status === 'in_progress') return ['in_progress', 'Loading in progress'];
        return ['confirmed', 'Loading pending'];
    }
    return { pending: ['pending', 'Awaiting response'], confirmed: ['confirmed', 'Confirmed'], rejected: ['rejected', 'Rejected'] }[d.status] || ['pending', d.status];
}

export function badge(key, label) {
    return `<span class="status-badge status-${h(key)}">${h(label)}</span>`;
}

export function stageBadge(d) {
    const [key, label] = stage(d);
    return badge(key, label);
}

export function pendingBadge(record) {
    return record?._pending ? '<span class="pending-badge" title="Saved on this device, not yet on the server"><i class="bi bi-cloud-arrow-up"></i> Not synced</span>' : '';
}

export function ref(record, field, placeholder = 'New') {
    return record?.[field] ? h(record[field]) : `<span class="text-muted">${placeholder}</span>`;
}

export function empty(icon, title, text = '', action = '') {
    return `<div class="empty-state"><div class="empty-icon"><i class="bi bi-${icon}"></i></div><h3>${h(title)}</h3>${text ? `<p class="mb-3">${h(text)}</p>` : ''}${action}</div>`;
}

export function pageHeader(title, subtitle = '', actions = '') {
    return `<div class="page-header"><div class="min-w-0"><h1>${h(title)}</h1>${subtitle ? `<p>${h(subtitle)}</p>` : ''}</div>${actions ? `<div class="page-actions">${actions}</div>` : ''}</div>`;
}

export function detail(label, value, wide = false) {
    return `<div${wide ? ' style="grid-column: 1 / -1"' : ''}><dt>${h(label)}</dt><dd>${value ?? '—'}</dd></div>`;
}

export function isReachable() {
    return !['offline', 'idle'].includes(getState().phase);
}

/**
 * Honest feedback: a save is only ever "on this device" until the server
 * confirms it (the sync engine then announces "synced with the server").
 */
export function savedToast(what) {
    toast(`${what} saved on this device. It will sync automatically when the internet is available.`, 'info');
}

export function go(hash) {
    if (location.hash === hash) window.dispatchEvent(new HashChangeEvent('hashchange'));
    else location.hash = hash;
}

export function byName(a, b) {
    return String(a.name || '').localeCompare(String(b.name || ''));
}

export function newestFirst(field) {
    return (a, b) => String(b[field] || '').localeCompare(String(a[field] || '')) || String(b.created_at || '').localeCompare(String(a.created_at || ''));
}

export function matches(text, query) {
    return String(text || '').toLowerCase().includes(String(query || '').trim().toLowerCase());
}

/** Is a date (YYYY-MM-DD or a full timestamp) inside an optional from/to range? */
export function inRange(value, from, to) {
    if (!from && !to) return true;
    if (!value) return false;
    const day = String(value).slice(0, 10);
    return (!from || day >= from) && (!to || day <= to);
}

/** "From / To" date filter, same look as the online x-date-range component. */
export function dateRange(from, to, label = 'Date', cls = 'col-6 col-lg-3') {
    const box = (name, text, value) => `<div class="${cls}"><div class="input-group date-filter"><span class="input-group-text">${text}</span>
        <input type="date" name="${name}" value="${h(value || '')}" max="${today()}" class="form-control" aria-label="${h(label)} ${name}" title="${h(label)} ${name}"></div></div>`;
    return box('from', 'From', from) + box('to', 'To', to);
}

/** "255", "0255" or "SMP-0255" → 255 (null when empty). */
export function refNumber(value) {
    const m = String(value ?? '').trim().match(/(\d+)\s*$/);
    return m ? parseInt(m[1], 10) : null;
}

/** Is a reference number (e.g. SMP-0260) inside an optional from/to range? Unsynced records have none. */
export function inNumberRange(reference, from, to) {
    const lo = refNumber(from);
    const hi = refNumber(to);
    if (lo === null && hi === null) return true;
    const n = reference ? refNumber(reference) : null;
    return n !== null && (lo === null || n >= lo) && (hi === null || n <= hi);
}

/** "From / To" reference-number filter, same look as the online x-number-range component. */
export function numberRange(fromNo, toNo, prefix = 'SMP', label = 'Sample no.', cls = 'col-6 col-lg-3') {
    const box = (name, text, value, placeholder) => `<div class="${cls}"><div class="input-group date-filter"><span class="input-group-text">${text}</span><span class="input-group-text number-prefix">${h(prefix)}-</span>
        <input type="text" name="${name}" value="${h(value || '')}" inputmode="numeric" autocomplete="off" class="form-control" placeholder="${placeholder}" aria-label="${h(label)} ${text.toLowerCase()}"></div></div>`;
    return box('from_no', 'From', fromNo, '0255') + box('to_no', 'To', toNo, '0300');
}
