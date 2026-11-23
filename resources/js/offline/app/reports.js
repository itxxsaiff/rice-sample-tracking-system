import { readActiveUser } from '../session';
import { toast } from '../../modules/ui';
import { loadingProgress, sampleLabel } from './store';
import { fmtDate, newestFirst, num, qty, rate, stage } from './util';

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

function dispatchRows(ctx, dispatches, { hideBuyer = false, showLoaded = false } = {}) {
    return dispatches.map((d, i) => {
        const s = ctx.sample(d.sample_uuid);
        const l = s ? sampleLabel(ctx, s) : { number: '-', variety: '', mill: '', city: '' };
        const buyer = ctx.buyer(d.buyer_uuid);
        const [key, label] = stage(d);
        const confirmed = d.status === 'confirmed';
        const row = [
            String(i + 1),
            { t: d.dispatch_number || 'New', bold: true, s: d.dispatch_number ? [] : ['not synced yet'] },
            { t: `${l.number} · ${l.variety}`, bold: true, s: [`${l.mill}${l.city ? `, ${l.city}` : ''}`] },
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

function loadingSection(ctx, trucks, { hideBuyer = false, heading = 'Truck loadings' } = {}) {
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
            { t: `${l.number} · ${l.variety}`, bold: true, s: [l.mill] },
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

export function buyerReport(ctx, buyer) {
    const spec = base(ctx, 'Buyer Statement', `buyer-${buyer.name}`);
    const dispatches = ctx.dispatches.filter((d) => d.buyer_uuid === buyer.uuid).sort(newestFirst('dispatch_date'));
    const trucks = ctx.loadings.filter((t) => dispatches.some((d) => d.uuid === t.dispatch_uuid))
        .sort((a, b) => String(a.loading_date).localeCompare(String(b.loading_date)));
    const confirmed = dispatches.filter((d) => d.status === 'confirmed');
    const options = { hideBuyer: true, showLoaded: true };

    spec.criteria = [deviceNote(ctx)];
    spec.party = { name: buyer.name, rows: [['City', buyer.city], ['Contact person', buyer.contact_person], ['Phone', buyer.phone], ['Address', buyer.address]] };
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
    spec.sections.push({ ...loadingSection(ctx, trucks, { hideBuyer: true }), skipWhenEmpty: true });
    return spec;
}

export function millReport(ctx, mill) {
    const spec = base(ctx, 'Mill Statement', `mill-${mill.name}`);
    const samples = ctx.samples.filter((s) => s.mill_uuid === mill.uuid).sort(newestFirst('collection_date'));
    const dispatches = samples.flatMap((s) => ctx.dispatchesOf(s.uuid)).sort(newestFirst('dispatch_date'));
    const confirmed = dispatches.filter((d) => d.status === 'confirmed');

    spec.criteria = [deviceNote(ctx)];
    spec.party = { name: mill.name, rows: [['City', mill.city], ['Contact person', mill.contact_person], ['Phone', mill.phone], ['Address', mill.address]] };
    spec.summary = [
        ['Samples', String(samples.length)],
        ['Varieties', String(new Set(samples.map((s) => s.variety_uuid)).size)],
        ['Sent to buyers', String(dispatches.length)],
        ['Confirmed', String(confirmed.length)],
        ['Agreed', totalsLabel(sumBy(confirmed, (d) => d.confirmed_quantity, (d) => d.confirmed_quantity_unit))],
    ];
    spec.sections.push(sampleSection(ctx, samples, { hideMill: true }));
    spec.sections.push({
        heading: 'Buyer responses', columns: dispatchColumns(), rows: dispatchRows(ctx, dispatches),
        foot: dispatchFoot(ctx, dispatches), skipWhenEmpty: true,
    });
    return spec;
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

export function samplesReport(ctx, samples, { q, variety, from, to } = {}) {
    const spec = base(ctx, 'Sample Register', 'samples');
    const sent = samples.filter((s) => ctx.dispatchesOf(s.uuid).length);
    spec.criteria = [
        ...(q ? [['Search', q]] : []),
        ...(variety ? [['Variety', ctx.variety(variety)?.name || '-']] : []),
        ['Collected', period(from, to)],
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
export function dispatchesReport(ctx, dispatches, { status = '', q = '', from = '', to = '' } = {}) {
    const list = { pending: 'Awaiting response', confirmed: 'Confirmed', rejected: 'Rejected' }[status] || 'All dispatches';
    const spec = recordsReport(ctx, 'dispatches', q, dispatches, { from, to, dateLabel: 'Dispatch date' });
    spec.filename = 'dispatches';
    spec.criteria[0] = ['List', list];
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
        await exportPdf(makeSpec());
    } catch (error) {
        console.error(error);
        toast('Could not make the PDF on this device. Please try again.', 'danger');
    } finally {
        button.disabled = false;
        button.innerHTML = original;
    }
}

export const pdfButton = '<button type="button" class="btn btn-light" data-export-pdf><i class="bi bi-file-earmark-pdf"></i> Export PDF</button>';
