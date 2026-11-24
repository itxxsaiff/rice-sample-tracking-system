import logoUrl from '../../pdf/logo.jpg';

/**
 * PDF reports made on the device (offline), laid out like the server's
 * PDFs (resources/views/pdf/*): letterhead with the logo, what the list
 * covers, a row of figures, register tables and "Page X of Y".
 *
 * A cell is either text or { t, s: [sub lines], bold, tone }:
 * the main line is drawn dark (or in the tone's colour), sub lines small and grey.
 */

const COMPANY = 'Ukasha Pacific International (SMC-Pvt) Ltd.';
const NAVY = [15, 42, 74];
const INK = [31, 41, 51];
const DARK = [17, 24, 39];
const GREY = [122, 130, 141];
const LIGHT = [154, 161, 171];
const RULE = [213, 218, 225];
const HAIR = [227, 231, 236];
const GOLD = [184, 137, 76];
const ZEBRA = [247, 249, 251];
export const TONES = {
    pending: [180, 83, 9], confirmed: [15, 118, 110], in_progress: [29, 78, 216],
    completed: [21, 128, 61], rejected: [185, 28, 28], muted: LIGHT,
};

const M = { left: 32, right: 32, top: 26, bottom: 48 };

// Standard PDF fonts cover Latin-1 only; keep the few typographic marks they lack readable.
const safe = (v) => String(v ?? '').replace(/[–—]/g, '-').replace(/[‘’]/g, "'").replace(/[“”]/g, '"').replace(/…/g, '...').replace(/→/g, '->');

let logoBytes = null;
async function logo() {
    if (!logoBytes) {
        try { logoBytes = new Uint8Array(await (await fetch(logoUrl)).arrayBuffer()); } catch { logoBytes = false; }
    }
    return logoBytes || null;
}

function letterhead(doc, title, printedBy) {
    const W = doc.internal.pageSize.getWidth();
    let x = M.left;
    if (doc.__logo) {
        doc.addImage(doc.__logo, 'JPEG', x, M.top, 57, 40.5);
        x += 66;
    }
    doc.setFont('helvetica', 'bold').setFontSize(12).setTextColor(...NAVY);
    doc.text(COMPANY, x, M.top + 19);
    doc.setFont('helvetica', 'normal').setFontSize(7).setTextColor(...GREY);
    doc.text('RICE SAMPLES  ·  TRACKING & DISPATCH', x, M.top + 30, { charSpace: 1 });

    doc.setFont('helvetica', 'bold').setFontSize(14).setTextColor(...NAVY);
    doc.text(safe(title), W - M.right, M.top + 19, { align: 'right' });
    const now = new Date();
    const printed = `Printed ${now.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })}, ${now.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' })}${printedBy ? ` · ${printedBy}` : ''} · offline copy`;
    doc.setFont('helvetica', 'normal').setFontSize(7.4).setTextColor(...GREY);
    doc.text(safe(printed), W - M.right, M.top + 30, { align: 'right' });

    const y = M.top + 50;
    doc.setDrawColor(...NAVY).setLineWidth(1.5).line(M.left, y, W - M.right, y);
    doc.setDrawColor(...GOLD).setLineWidth(0.6).line(M.left, y + 2.6, W - M.right, y + 2.6);
    return y + 16;
}

/** "Label: value | Label: value" with bold values, wrapping when needed. */
function criteria(doc, items, y) {
    const W = doc.internal.pageSize.getWidth();
    let x = M.left;
    doc.setFontSize(7.8);
    items.forEach(([label, value], i) => {
        const parts = [[`${label}: `, 'normal', [75, 85, 99]], [safe(value), 'bold', INK]];
        if (i < items.length - 1) parts.push(['   |   ', 'normal', [195, 200, 207]]);
        const width = parts.reduce((w, [t, f]) => w + doc.setFont('helvetica', f).getTextWidth(t), 0);
        if (x + width > W - M.right && x > M.left) { x = M.left; y += 11; }
        for (const [t, f, c] of parts) {
            doc.setFont('helvetica', f).setTextColor(...c).text(t, x, y);
            x += doc.getTextWidth(t);
        }
    });
    return y + 14;
}

function party(doc, { name, rows }, y) {
    const W = doc.internal.pageSize.getWidth();
    const col = (W - M.left - M.right) / 2;
    doc.setFont('helvetica', 'bold').setFontSize(12).setTextColor(...NAVY).text(safe(name), M.left, y + 4);
    y += 20;
    doc.setFontSize(8.2);
    for (let i = 0; i < rows.length; i += 2) {
        let lines = 1;
        rows.slice(i, i + 2).forEach(([k, v], j) => {
            const x = M.left + j * col;
            doc.setFont('helvetica', 'normal').setTextColor(...GREY).text(safe(k), x, y);
            const wrapped = doc.splitTextToSize(safe(v || '-'), col - 84);
            doc.setTextColor(...INK).text(wrapped, x + 74, y);
            lines = Math.max(lines, wrapped.length);
        });
        y += 11 * lines + 3;
    }
    return y + 6;
}

function summary(doc, items, y) {
    const W = doc.internal.pageSize.getWidth();
    const w = (W - M.left - M.right) / items.length;
    const h = 36;
    doc.setDrawColor(...RULE).setLineWidth(0.6);
    doc.line(M.left, y, W - M.right, y).line(M.left, y + h, W - M.right, y + h);
    items.forEach(([k, v], i) => {
        const x = M.left + i * w;
        if (i) doc.setDrawColor(...HAIR).line(x, y, x, y + h);
        const pad = i ? 8 : 0;
        doc.setFont('helvetica', 'normal').setFontSize(6.8).setTextColor(...GREY).text(safe(k).toUpperCase(), x + pad, y + 12, { charSpace: 0.6 });
        doc.setFont('helvetica', 'bold').setFontSize(12).setTextColor(...NAVY).text(safe(v), x + pad, y + 28);
    });
    return y + h + 18;
}

function heading(doc, text, count, y) {
    doc.setFont('helvetica', 'bold').setFontSize(8.6).setTextColor(...NAVY);
    const label = safe(text).toUpperCase();
    doc.text(label, M.left, y, { charSpace: 1 });
    const width = doc.getTextWidth(label) + label.length * 1;
    if (count != null) doc.setFont('helvetica', 'normal').setTextColor(...GREY).text(`(${count})`, M.left + width + 4, y);
    return y + 6;
}

function ensureSpace(doc, y, needed) {
    if (y + needed > doc.internal.pageSize.getHeight() - M.bottom) {
        doc.addPage();
        return M.top + 10;
    }
    return y;
}

function table(doc, autoTable, section, y) {
    const cellText = (c) => (c && typeof c === 'object' ? [c.t, ...(c.s || [])].filter((l) => l != null && l !== '').map(safe) : [safe(c)]);
    const custom = new Map();

    autoTable(doc, {
        startY: y,
        margin: { left: M.left, right: M.right, top: M.top + 10, bottom: M.bottom },
        theme: 'plain',
        head: [section.columns.map((c) => c.header.toUpperCase())],
        body: section.rows,
        foot: section.foot ? [section.foot] : undefined,
        showFoot: 'lastPage',
        styles: { font: 'helvetica', fontSize: 8.4, textColor: INK, cellPadding: { top: 5, bottom: 5, left: 4.5, right: 4.5 }, valign: 'top', overflow: 'linebreak' },
        headStyles: { fontSize: 6.6, fontStyle: 'bold', textColor: [75, 85, 99], lineColor: NAVY, lineWidth: { bottom: 0.9 } },
        bodyStyles: { lineColor: HAIR, lineWidth: { bottom: 0.45 } },
        alternateRowStyles: { fillColor: ZEBRA },
        footStyles: { fontStyle: 'bold', textColor: NAVY, lineColor: NAVY, lineWidth: { top: 0.9 } },
        columnStyles: Object.fromEntries(section.columns.map((c, i) => [i, {
            halign: c.align || 'left', ...(c.width ? { cellWidth: c.width } : {}), ...(c.no ? { textColor: LIGHT, fontSize: 7 } : {}),
        }])),
        didParseCell(data) {
            data.cell.styles.halign = section.columns[data.column.index]?.align || 'left';
            if (data.section === 'head') return;
            const raw = data.cell.raw;
            data.cell.text = cellText(raw);
            if (raw && typeof raw === 'object' && raw.bold) data.cell.styles.fontStyle = 'bold';
        },
        willDrawCell(data) {
            if (data.section === 'head') return;
            const raw = data.cell.raw;
            if (raw && typeof raw === 'object') {
                custom.set(data.cell, raw);
                data.cell.text = [];
            }
        },
        didDrawCell(data) {
            const raw = custom.get(data.cell);
            if (!raw) return;
            const cell = data.cell;
            const width = cell.width - 9;
            const right = cell.styles.halign === 'right';
            const x = right ? cell.x + cell.width - 4.5 : cell.x + 4.5;
            let ty = cell.y + 5 + 8.4 * 0.85;
            doc.setFont('helvetica', raw.bold ? 'bold' : 'normal').setFontSize(raw.small ? 7.4 : 8.4)
                .setTextColor(...(raw.tone ? TONES[raw.tone] : (raw.bold ? DARK : INK)));
            for (const line of doc.splitTextToSize(safe(raw.t), width)) {
                doc.text(line, x, ty, { align: right ? 'right' : 'left' });
                ty += 9.6;
            }
            doc.setFont('helvetica', 'normal').setFontSize(7.2).setTextColor(...GREY);
            for (const sub of (raw.s || []).filter(Boolean)) {
                for (const line of doc.splitTextToSize(safe(sub), width)) {
                    doc.text(line, x, ty, { align: right ? 'right' : 'left' });
                    ty += 8.6;
                }
            }
        },
    });
    return doc.lastAutoTable.finalY + 18;
}

/** Truck photos: a line per truck, then its photos four to a row. */
function photos(doc, section, y) {
    const W = doc.internal.pageSize.getWidth();
    const gap = 6;
    const cell = (W - M.left - M.right - gap * 3) / 4;
    y = ensureSpace(doc, y, 60);
    y = heading(doc, section.heading, null, y) + 8;
    for (const group of section.groups) {
        y = ensureSpace(doc, y, 40);
        doc.setFont('helvetica', 'normal').setFontSize(8).setTextColor(75, 85, 99).text(safe(group.title), M.left, y);
        doc.setDrawColor(...HAIR).setLineWidth(0.6).line(M.left, y + 4, W - M.right, y + 4);
        y += 12;
        for (let i = 0; i < group.images.length; i += 4) {
            const row = group.images.slice(i, i + 4);
            const rowHeight = Math.max(...row.map((img) => cell * (img.h / img.w)));
            y = ensureSpace(doc, y, rowHeight + gap);
            row.forEach((img, j) => {
                const h = cell * (img.h / img.w);
                doc.addImage(img.data, 'JPEG', M.left + j * (cell + gap), y, cell, h);
                doc.setDrawColor(...HAIR).rect(M.left + j * (cell + gap), y, cell, h);
            });
            y += rowHeight + gap;
        }
        if (group.note) {
            doc.setFont('helvetica', 'normal').setFontSize(7.4).setTextColor(...GREY).text(safe(group.note), M.left, y + 4);
            y += 12;
        }
        y += 8;
    }
    return y + 6;
}

function footer(doc, title) {
    const pages = doc.getNumberOfPages();
    const W = doc.internal.pageSize.getWidth();
    const H = doc.internal.pageSize.getHeight();
    for (let i = 1; i <= pages; i++) {
        doc.setPage(i);
        doc.setFont('helvetica', 'normal').setFontSize(7.5).setTextColor(115, 122, 135);
        doc.text(safe(`${COMPANY}  ·  ${title}`), M.left, H - 26);
        doc.text(`Page ${i} of ${pages}`, W - M.right, H - 26, { align: 'right' });
    }
}

/** Build the PDF and hand it to the user (share sheet on phones, download elsewhere). */
export async function exportPdf(spec) {
    const [{ jsPDF }, { autoTable }] = await Promise.all([import('jspdf'), import('jspdf-autotable')]);
    const doc = new jsPDF({ unit: 'pt', format: 'a4', compress: true });
    doc.setProperties({ title: spec.title, author: COMPANY });
    doc.__logo = await logo();

    let y = letterhead(doc, spec.title, spec.printedBy);
    if (spec.criteria?.length) y = criteria(doc, spec.criteria, y);
    if (spec.party) y = party(doc, spec.party, y);
    if (spec.summary?.length) y = summary(doc, spec.summary, y);

    for (const section of spec.sections) {
        if (section.kind === 'photos') { y = photos(doc, section, y); continue; }
        if (section.skipWhenEmpty && !section.rows.length) continue;
        y = ensureSpace(doc, y, 60);
        y = heading(doc, section.heading, section.count ?? section.rows.length, y);
        if (section.note) {
            doc.setFont('helvetica', 'normal').setFontSize(7.4).setTextColor(...GREY).text(safe(section.note), M.left, y + 6);
            y += 12;
        }
        if (!section.rows.length) {
            y += 6;
            doc.setDrawColor(...HAIR).setLineWidth(0.6).line(M.left, y, doc.internal.pageSize.getWidth() - M.right, y);
            doc.setFont('helvetica', 'normal').setFontSize(8.4).setTextColor(...GREY)
                .text(safe(section.empty || 'Nothing to show.'), doc.internal.pageSize.getWidth() / 2, y + 22, { align: 'center' });
            doc.line(M.left, y + 36, doc.internal.pageSize.getWidth() - M.right, y + 36);
            y += 54;
            continue;
        }
        y = table(doc, autoTable, section, y + 2);
    }

    y = ensureSpace(doc, y, 20);
    doc.setFont('helvetica', 'normal').setFontSize(7).setTextColor(...LIGHT)
        .text('-  END OF REPORT  -', doc.internal.pageSize.getWidth() / 2, y, { align: 'center', charSpace: 1 });
    footer(doc, spec.title);

    const date = new Date().toISOString().slice(0, 10);
    const name = `${spec.filename}-${date}.pdf`.toLowerCase().replace(/[^a-z0-9.]+/g, '-');
    const blob = doc.output('blob');

    // iPhone/Android: the share sheet lets the user save to Files or send on WhatsApp.
    const file = typeof File === 'function' ? new File([blob], name, { type: 'application/pdf' }) : null;
    if (file && navigator.canShare?.({ files: [file] }) && /iPhone|iPad|Android/i.test(navigator.userAgent)) {
        try {
            await navigator.share({ files: [file], title: spec.title });
            return;
        } catch (error) {
            if (error?.name === 'AbortError') return; // closed the share sheet
        }
    }
    const url = URL.createObjectURL(blob);
    const a = Object.assign(document.createElement('a'), { href: url, download: name });
    document.body.appendChild(a);
    a.click();
    a.remove();
    setTimeout(() => URL.revokeObjectURL(url), 60_000);
}
