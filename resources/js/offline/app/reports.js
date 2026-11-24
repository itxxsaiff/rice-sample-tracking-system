import { Modal } from 'bootstrap';
import { readActiveUser } from '../session';
import { toast } from '../../modules/ui';
import { loadingProgress, sampleLabel } from './store';
import { cfg, fmtDate, newestFirst, num, qty, rate, refNumber, stage } from './util';

/**
 * Offline versions of the server PDFs (Records, Buyer and Mill statements,
 * Sample register), made from the data on this device: the last
 * OFFLINE_HISTORY_DAYS days plus open orders.
 */

const RECORD_TITLES = {
    dispatches: 'Dispatch Register', confirmations: 'Confirmed Orders', rejections: 'Rejected Samples',
    loadings: 'Truck Loading Register', completed: 'Completed Orders',
};
const RECORD_LABELS = {
    dispatches: 'All dispatches', confirmations: 'Confirmations', rejections: 'Rejections',
    loadings: 'Truck loadings', completed: 'Completed orders',
};

/** Same wording as PdfReport::period() on the server. */
export function period(from, to) {
    if (from && to) return `${fmtDate(from)} - ${fmtDate(to)}`;
    if (from) return `from ${fmtDate(from)}`;
    if (to) return `up to ${fmtDate(to)}`;
    return 'All dates';
}

/** Same wording as ReferenceNumber::rangeLabel() on the server. */
export function numberRangeLabel(prefix, fromNo, toNo) {
    const fmt = (v) => `${prefix}-${String(refNumber(v)).padStart(4, '0')}`;
    const lo = refNumber(fromNo);
    const hi = refNumber(toNo);
    if (lo !== null && hi !== null) return `${fmt(lo)} - ${fmt(hi)}`;
    if (lo !== null) return `from ${fmt(lo)}`;
    if (hi !== null) return `up to ${fmt(hi)}`;
    return 'All';
}

const deviceNote = (ctx) => ['Data', `on this device (last ${ctx.historyDays || 10} days and open orders)`];
const totalsLabel = (totals) => Object.entries(totals).map(([unit, value]) => qty(value, unit)).join(', ') || '-';

function sumBy(rows, valueOf, unitOf) {
    const totals = {};
    for (const r of rows) {
        const v = Number(valueOf(r));
        if (!Number.isFinite(v) || v === 0) continue;
        totals[unitOf(r)] = (totals[unitOf(r)] || 0) + v;
    }
    return totals;
}

function bags(packing) {
    if (!packing) return null;
    return /kg$/i.test(packing) ? `${packing} bags` : packing;
}

function dispatchColumns({ hideBuyer = false, showLoaded = false } = {}) {
    return [
        { header: '#', no: true, width: 20 },
        { header: 'Dispatch', width: 62 },
        { header: 'Sample' },
        ...(hideBuyer ? [] : [{ header: 'Buyer' }]),
        { header: 'Sent', width: 58 },
        { header: 'Status', width: 88 },
        { header: 'Agreed', align: 'right', width: 96 },
        ...(showLoaded ? [{ header: 'Loaded', align: 'right', width: 70 }] : []),
    ];
}

function dispatchRows(ctx, dispatches, { hideBuyer = false, showLoaded = false, sampleDetails = true } = {}) {
    return dispatches.map((d, i) => {
        const s = ctx.sample(d.sample_uuid);
        const l = s ? sampleLabel(ctx, s) : { number: '-', variety: '', mill: '', city: '' };
        const buyer = ctx.buyer(d.buyer_uuid);
        const [key, label] = stage(d);
        const confirmed = d.status === 'confirmed';
        const row = [
            String(i + 1),
            { t: d.dispatch_number || 'New', bold: true, s: d.dispatch_number ? [] : ['not synced yet'] },
            { t: `${l.number} · ${l.variety}`, bold: true, s: sampleDetails ? [`${l.mill}${l.city ? `, ${l.city}` : ''}`] : [] },
        ];
        if (!hideBuyer) row.push({ t: buyer?.name || '-', bold: true, s: [buyer?.city] });
        row.push(fmtDate(d.dispatch_date));
        row.push({ t: label, bold: true, tone: key, small: true, s: confirmed && d.confirmation_date ? [`${key === 'confirmed' ? '' : 'Confirmed '}${fmtDate(d.confirmation_date)}`] : [] });
        if (confirmed) row.push({ t: qty(d.confirmed_quantity, d.confirmed_quantity_unit) || '-', bold: true, s: [rate(d), bags(d.packing_type)] });
        else if (d.status === 'rejected') row.push({ t: `Rejected ${fmtDate(d.rejection_date)}`, tone: 'muted' });
        else row.push({ t: '-', tone: 'muted' });
        if (showLoaded) {
            if (confirmed) {
                const { totals, percent } = loadingProgress(ctx, d);
                const trucks = ctx.loadingsOf(d.uuid).length;
                row.push({ t: qty(totals[d.confirmed_quantity_unit] || 0, d.confirmed_quantity_unit), bold: true, s: [`${trucks} truck${trucks === 1 ? '' : 's'} · ${percent ?? 0}%`] });
            } else {
                row.push({ t: '-', tone: 'muted' });
            }
        }
        return row;
    });
}

function dispatchFoot(ctx, dispatches, { hideBuyer = false, showLoaded = false } = {}) {
    const confirmed = dispatches.filter((d) => d.status === 'confirmed');
    if (!confirmed.length) return null;
    const foot = ['', 'Total agreed', '', ...(hideBuyer ? [] : ['']), '', '', totalsLabel(sumBy(confirmed, (d) => d.confirmed_quantity, (d) => d.confirmed_quantity_unit))];
    if (showLoaded) foot.push(totalsLabel(sumBy(confirmed.flatMap((d) => ctx.loadingsOf(d.uuid)), (t) => t.quantity, (t) => t.quantity_unit)));
    return foot.map((v, i) => (i >= foot.length - (showLoaded ? 2 : 1) ? { t: v, bold: true, tone: null } : v));
}

function loadingSection(ctx, trucks, { hideBuyer = false, heading = 'Truck loadings', sampleDetails = true } = {}) {
    const columns = [
        { header: '#', no: true, width: 20 }, { header: 'Truck', width: 80 },
        ...(hideBuyer ? [] : [{ header: 'Buyer' }]),
        { header: 'Sample' }, { header: 'Date', width: 58 }, { header: 'Driver' },
        { header: 'Quantity', align: 'right', width: 74 }, { header: 'Photos', align: 'right', width: 36 }, { header: 'Status', width: 46 },
    ];
    const rows = trucks.map((t, i) => {
        const d = ctx.dispatch(t.dispatch_uuid);
        const s = d ? ctx.sample(d.sample_uuid) : null;
        const l = s ? sampleLabel(ctx, s) : { number: '-', variety: '', mill: '' };
        return [
            String(i + 1),
            { t: t.truck_number, bold: true, s: [`${t.loading_number || 'New'} · ${d?.dispatch_number || 'New'}`] },
            ...(hideBuyer ? [] : [{ t: ctx.buyer(d?.buyer_uuid)?.name || '-', bold: true }]),
            { t: `${l.number} · ${l.variety}`, bold: true, s: sampleDetails ? [l.mill] : [] },
            fmtDate(t.loading_date),
            { t: t.driver_name || '-', s: [t.driver_phone] },
            { t: qty(t.quantity, t.quantity_unit) || '-', bold: true, s: [t.bag_count ? `${num(t.bag_count)} bags${t.packing_type ? ` of ${t.packing_type}` : ''}` : null] },
            String((t.photos_count || 0) + ctx.photosOf(t.uuid).length),
            { t: t.status === 'completed' ? 'Loaded' : 'Loading', bold: true, small: true, tone: t.status === 'completed' ? 'completed' : 'in_progress' },
        ];
    });
    const bagCount = trucks.reduce((n, t) => n + Number(t.bag_count || 0), 0);
    const pad = hideBuyer ? 4 : 5;
    const foot = trucks.length
        ? ['', 'Total loaded', ...Array(pad - 1).fill(''), { t: totalsLabel(sumBy(trucks, (t) => t.quantity, (t) => t.quantity_unit)), bold: true, s: bagCount ? [`${num(bagCount)} bags`] : [] }, '', '']
        : null;
    return { heading, columns, rows, foot, empty: 'No trucks recorded.' };
}

function base(ctx, title, filename) {
    return { title, filename, printedBy: readActiveUser()?.name, sections: [] };
}

export function recordsReport(ctx, type, q, rows, { from = '', to = '', dateLabel = 'Date' } = {}) {
    const spec = base(ctx, RECORD_TITLES[type], `records-${type}`);
    spec.criteria = [['List', RECORD_LABELS[type]], ...(q ? [['Search', q]] : []), [dateLabel, period(from, to)], deviceNote(ctx)];
    if (type === 'loadings') {
        const trucks = rows;
        spec.summary = [
            ['Trucks', num(trucks.length) || '0'],
            ['Loaded', totalsLabel(sumBy(trucks, (t) => t.quantity, (t) => t.quantity_unit))],
            ['Bags', num(trucks.reduce((n, t) => n + Number(t.bag_count || 0), 0)) || '0'],
            ['Orders', num(new Set(trucks.map((t) => t.dispatch_uuid)).size) || '0'],
        ];
        spec.sections.push({ ...loadingSection(ctx, trucks, { heading: RECORD_LABELS[type] }), empty: 'No records match.' });
        return spec;
    }
    const dispatches = rows;
    spec.summary = [
        ['Records', String(dispatches.length)],
        ['Awaiting response', String(dispatches.filter((d) => d.status === 'pending').length)],
        ['Confirmed', String(dispatches.filter((d) => d.status === 'confirmed').length)],
        ['Rejected', String(dispatches.filter((d) => d.status === 'rejected').length)],
        ['Agreed', totalsLabel(sumBy(dispatches.filter((d) => d.status === 'confirmed'), (d) => d.confirmed_quantity, (d) => d.confirmed_quantity_unit))],
    ];
    spec.sections.push({
        heading: RECORD_LABELS[type], columns: dispatchColumns(), rows: dispatchRows(ctx, dispatches),
        foot: dispatchFoot(ctx, dispatches), empty: 'No records match.',
    });
    return spec;
}

/** A photo kept on this phone as a small JPEG for the PDF. */
async function thumbnail(blob, width = 480) {
    try {
        const bitmap = await createImageBitmap(blob);
        const scale = Math.min(1, width / bitmap.width);
        const canvas = Object.assign(document.createElement('canvas'), { width: Math.round(bitmap.width * scale), height: Math.round(bitmap.height * scale) });
        canvas.getContext('2d').drawImage(bitmap, 0, 0, canvas.width, canvas.height);
        bitmap.close?.();
        return { data: canvas.toDataURL('image/jpeg', 0.72), w: canvas.width, h: canvas.height };
    } catch {
        return null;
    }
}

/** Buyer PDF: "samples" (samples sent and answers) or "loadings" (trucks with their photos). */
export async function buyerReport(ctx, buyer, part = 'samples') {
    const trucksPart = part === 'loadings';
    const spec = base(ctx, trucksPart ? 'Truck Loadings' : 'Samples Sent', `buyer-${buyer.name}-${trucksPart ? 'trucks' : 'samples'}`);
    const dispatches = ctx.dispatches.filter((d) => d.buyer_uuid === buyer.uuid).sort(newestFirst('dispatch_date'));
    const trucks = ctx.loadings.filter((t) => dispatches.some((d) => d.uuid === t.dispatch_uuid))
        .sort((a, b) => String(a.loading_date).localeCompare(String(b.loading_date)));
    const confirmed = dispatches.filter((d) => d.status === 'confirmed');
    const options = { hideBuyer: true, showLoaded: true, sampleDetails: false };

    spec.criteria = [deviceNote(ctx)];
    spec.party = { name: buyer.name, rows: [['City', buyer.city], ['Contact person', buyer.contact_person], ['Phone', buyer.phone], ['Address', buyer.address]] };

    if (!trucksPart) {
        spec.summary = [
            ['Samples sent', String(dispatches.length)],
            ['Awaiting', String(dispatches.filter((d) => d.status === 'pending').length)],
            ['Confirmed', String(confirmed.length)],
            ['Rejected', String(dispatches.filter((d) => d.status === 'rejected').length)],
            ['Agreed', totalsLabel(sumBy(confirmed, (d) => d.confirmed_quantity, (d) => d.confirmed_quantity_unit))],
            ['Loaded', totalsLabel(sumBy(trucks, (t) => t.quantity, (t) => t.quantity_unit))],
        ];
        spec.sections.push({
            heading: 'Samples sent', columns: dispatchColumns(options), rows: dispatchRows(ctx, dispatches, options),
            foot: dispatchFoot(ctx, dispatches, options), empty: 'No samples sent to this buyer on this device.',
        });
        return spec;
    }

    const photoCount = (t) => (t.photos_count || 0) + ctx.photosOf(t.uuid).length;
    spec.summary = [
        ['Trucks', String(trucks.length)],
        ['Loaded', totalsLabel(sumBy(trucks, (t) => t.quantity, (t) => t.quantity_unit))],
        ['Bags', num(trucks.reduce((n, t) => n + Number(t.bag_count || 0), 0)) || '0'],
        ['Orders', String(new Set(trucks.map((t) => t.dispatch_uuid)).size)],
        ['Photos', String(trucks.reduce((n, t) => n + photoCount(t), 0))],
    ];
    spec.sections.push({ ...loadingSection(ctx, trucks, { hideBuyer: true, sampleDetails: false }), empty: 'No trucks loaded for this buyer on this device.' });

    // Photos still on this phone go in the PDF; ones already uploaded are in the online PDF.
    const groups = [];
    for (const t of trucks) {
        const images = (await Promise.all(ctx.photosOf(t.uuid).map((p) => thumbnail(p.blob)))).filter(Boolean);
        const online = t.photos_count || 0;
        groups.push({
            title: `${t.truck_number} · ${t.loading_number || 'New'} · ${ctx.dispatch(t.dispatch_uuid)?.dispatch_number || 'New'} · ${fmtDate(t.loading_date)} · ${qty(t.quantity, t.quantity_unit) || ''}`,
            images,
            note: online ? `${online} photo${online === 1 ? '' : 's'} already uploaded: shown in the PDF exported online.` : (images.length ? '' : 'No photos.'),
        });
    }
    if (trucks.length) spec.sections.push({ kind: 'photos', heading: 'Truck photos', groups });
    return spec;
}

/** Mill PDF: "samples" (samples collected) or "responses" (what each buyer answered). */
export function millReport(ctx, mill, part = 'samples') {
    const responses = part === 'responses';
    const spec = base(ctx, responses ? 'Buyer Responses' : 'Mill Samples', `mill-${mill.name}-${responses ? 'responses' : 'samples'}`);
    const samples = ctx.samples.filter((s) => s.mill_uuid === mill.uuid).sort(newestFirst('collection_date'));
    const dispatches = samples.flatMap((s) => ctx.dispatchesOf(s.uuid)).sort(newestFirst('dispatch_date'));
    const confirmed = dispatches.filter((d) => d.status === 'confirmed');
    const sent = samples.filter((s) => ctx.dispatchesOf(s.uuid).length).length;

    spec.criteria = [deviceNote(ctx)];
    spec.party = { name: mill.name, rows: [['City', mill.city], ['Contact person', mill.contact_person], ['Phone', mill.phone], ['Address', mill.address]] };
    if (!responses) {
        spec.summary = [
            ['Samples', String(samples.length)],
            ['Varieties', String(new Set(samples.map((s) => s.variety_uuid)).size)],
            ['Sent to buyers', String(sent)],
            ['Not sent yet', String(samples.length - sent)],
        ];
        spec.sections.push(sampleSection(ctx, samples, { hideMill: true }));
        return spec;
    }
    spec.summary = [
        ['Responses', String(dispatches.length)],
        ['Awaiting', String(dispatches.filter((d) => d.status === 'pending').length)],
        ['Confirmed', String(confirmed.length)],
        ['Rejected', String(dispatches.filter((d) => d.status === 'rejected').length)],
        ['Agreed', totalsLabel(sumBy(confirmed, (d) => d.confirmed_quantity, (d) => d.confirmed_quantity_unit))],
    ];
    spec.sections.push({
        heading: 'Buyer responses', columns: dispatchColumns(), rows: dispatchRows(ctx, dispatches),
        foot: dispatchFoot(ctx, dispatches), empty: 'No samples from this mill have been sent to buyers on this device.',
    });
    return spec;
}

/** Ask which report to make (same choices as the online "Export PDF" popup). Resolves to the key or null. */
export function pickReport(title, options) {
    return new Promise((resolve) => {
        const el = document.createElement('div');
        el.className = 'modal fade';
        el.tabIndex = -1;
        el.innerHTML = `<div class="modal-dialog modal-dialog-centered"><div class="modal-content border-0 rounded-4">
            <div class="modal-header"><h5 class="modal-title fw-semibold"></h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body pt-2"><p class="text-muted small mb-2">What should the PDF show?</p><div class="list-group">
            ${options.map(([key, label, hint, icon]) => `<button type="button" class="list-group-item list-group-item-action d-flex gap-3 align-items-center py-3 text-start" data-key="${key}">
                <span class="stat-icon tone-navy flex-shrink-0"><i class="bi bi-${icon}"></i></span><span><span class="d-block fw-semibold">${label}</span><span class="small text-muted">${hint}</span></span></button>`).join('')}
            </div></div></div></div>`;
        el.querySelector('.modal-title').textContent = `Export PDF — ${title}`;
        document.body.appendChild(el);
        const modal = new Modal(el);
        let chosen = null;
        el.addEventListener('click', (e) => {
            const btn = e.target.closest('[data-key]');
            if (btn) { chosen = btn.dataset.key; modal.hide(); }
        });
        el.addEventListener('hidden.bs.modal', () => { el.remove(); resolve(chosen); });
        modal.show();
    });
}

function sampleSection(ctx, samples, { hideMill = false } = {}) {
    const columns = [
        { header: '#', no: true, width: 20 }, { header: 'Sample', width: 74 },
        ...(hideMill ? [] : [{ header: 'Mill' }]),
        { header: 'Variety', width: 58 }, { header: 'Collected', width: 60 }, { header: 'Quantity', width: 50 }, { header: 'Sent to' },
    ];
    const rows = samples.map((s, i) => {
        const l = sampleLabel(ctx, s);
        const sent = ctx.dispatchesOf(s.uuid);
        return [
            String(i + 1),
            { t: l.number, bold: true, s: [s.notes ? (s.notes.length > 60 ? `${s.notes.slice(0, 57)}...` : s.notes) : null] },
            ...(hideMill ? [] : [{ t: l.mill, bold: true, s: [l.city] }]),
            l.variety,
            fmtDate(s.collection_date),
            qty(s.quantity, s.quantity_unit) || '-',
            sent.length
                ? { t: sent.map((d) => `${ctx.buyer(d.buyer_uuid)?.name || '-'} - ${stage(d)[1]}`).join('\n') }
                : { t: 'Not sent', tone: 'muted' },
        ];
    });
    return { heading: 'Samples', columns, rows, empty: 'No samples on this device.' };
}

export function samplesReport(ctx, samples, { q, variety, fromNo, toNo } = {}) {
    const spec = base(ctx, 'Sample Register', 'samples');
    const sent = samples.filter((s) => ctx.dispatchesOf(s.uuid).length);
    spec.criteria = [
        ...(q ? [['Search', q]] : []),
        ...(variety ? [['Variety', ctx.variety(variety)?.name || '-']] : []),
        ['Sample no.', numberRangeLabel(cfg().prefixes?.sample || 'SMP', fromNo, toNo)],
        deviceNote(ctx),
    ];
    spec.summary = [
        ['Samples', String(samples.length)],
        ['Mills', String(new Set(samples.map((s) => s.mill_uuid)).size)],
        ['Varieties', String(new Set(samples.map((s) => s.variety_uuid)).size)],
        ['Sent to buyers', String(sent.length)],
        ['Not sent yet', String(samples.length - sent.length)],
    ];
    spec.sections.push(sampleSection(ctx, samples));
    return spec;
}

/** The Dispatches page (its tab, search and dates) as a Dispatch Register. */
export function dispatchesReport(ctx, dispatches, { status = '', q = '', fromNo = '', toNo = '' } = {}) {
    const list = { pending: 'Awaiting response', confirmed: 'Confirmed', rejected: 'Rejected' }[status] || 'All dispatches';
    const spec = recordsReport(ctx, 'dispatches', q, dispatches);
    spec.filename = 'dispatches';
    spec.criteria = [['List', list], ...(q ? [['Search', q]] : []), ['Dispatch no.', numberRangeLabel(cfg().prefixes?.dispatch || 'DSP', fromNo, toNo)], deviceNote(ctx)];
    spec.sections[0].heading = list;
    return spec;
}

/** Button handler: build the report, then the PDF (the library loads on first use). */
export async function runExport(button, makeSpec) {
    if (button.disabled) return;
    const original = button.innerHTML;
    button.disabled = true;
    button.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Preparing…';
    try {
        const { exportPdf } = await import('../pdf');
        await exportPdf(await makeSpec());
    } catch (error) {
        console.error(error);
        toast('Could not make the PDF on this device. Please try again.', 'danger');
    } finally {
        button.disabled = false;
        button.innerHTML = original;
    }
}

export const pdfButton = '<button type="button" class="btn btn-light" data-export-pdf><i class="bi bi-file-earmark-pdf"></i> Export PDF</button>';
