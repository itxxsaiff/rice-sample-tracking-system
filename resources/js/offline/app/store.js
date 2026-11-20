import { RECORD_TABLES, getMeta, openDb } from '../db';
import { readActiveUser } from '../session';
import { byName } from './util';

/**
 * Read model for the offline screens: the device's copy of each table,
 * indexed by uuid, plus the joins the screens need.
 */
export async function loadContext() {
    const user = readActiveUser();
    const db = openDb(user.id);
    const ctx = { user, db };

    const rows = await Promise.all(RECORD_TABLES.map((t) => db[t].toArray()));
    RECORD_TABLES.forEach((t, i) => {
        ctx[t] = rows[i];
        ctx[`${t}By`] = new Map(rows[i].map((r) => [r.uuid, r]));
    });
    ctx.ops = await db.ops.orderBy('seq').toArray();
    ctx.photos = await db.photos.toArray();
    ctx.lastSync = await getMeta(db, 'lastSync');
    ctx.historyDays = await getMeta(db, 'historyDays');
    ctx.mills.sort(byName);
    ctx.buyers.sort(byName);
    ctx.varieties.sort(byName);

    ctx.mill = (uuid) => ctx.millsBy.get(uuid);
    ctx.buyer = (uuid) => ctx.buyersBy.get(uuid);
    ctx.variety = (uuid) => ctx.varietiesBy.get(uuid);
    ctx.sample = (uuid) => ctx.samplesBy.get(uuid);
    ctx.dispatch = (uuid) => ctx.dispatchesBy.get(uuid);
    ctx.loading = (uuid) => ctx.loadingsBy.get(uuid);

    ctx.dispatchesOf = (sampleUuid) => ctx.dispatches.filter((d) => d.sample_uuid === sampleUuid);
    ctx.loadingsOf = (dispatchUuid) => ctx.loadings.filter((l) => l.dispatch_uuid === dispatchUuid)
        .sort((a, b) => String(a.loading_date).localeCompare(String(b.loading_date)));
    ctx.photosOf = (targetUuid) => ctx.photos.filter((p) => p.target_uuid === targetUuid);

    ctx.millItems = () => ctx.mills.map((m) => ({ uuid: m.uuid, label: m.name, sub: m.city }));
    ctx.buyerItems = () => ctx.buyers.map((b) => ({ uuid: b.uuid, label: b.name, sub: b.city }));
    ctx.varietyOptions = (include = null) => Object.fromEntries(
        ctx.varieties.filter((v) => v.is_active || v.uuid === include).map((v) => [v.uuid, v.name]),
    );

    return ctx;
}

export function sampleLabel(ctx, s) {
    const mill = ctx.mill(s.mill_uuid);
    return {
        number: s.sample_number || 'New sample',
        variety: ctx.variety(s.variety_uuid)?.name || '—',
        mill: mill?.name || '—',
        city: mill?.city || '',
    };
}

/** Loaded totals per unit and percent of the confirmed quantity (mirrors SampleDispatch). */
export function loadingProgress(ctx, d) {
    const totals = {};
    for (const l of ctx.loadingsOf(d.uuid)) totals[l.quantity_unit] = (totals[l.quantity_unit] || 0) + Number(l.quantity || 0);
    const confirmed = Number(d.confirmed_quantity || 0);
    const same = totals[d.confirmed_quantity_unit];
    const percent = confirmed > 0 && same != null ? Math.min(100, Math.round((same / confirmed) * 100)) : (Object.keys(totals).length ? null : 0);
    const remaining = confirmed > 0 ? Math.max(0, Math.round((confirmed - (same || 0)) * 100) / 100) : null;
    return { totals, percent, remaining };
}

export function isActiveDispatch(d) {
    return d.status === 'pending' || d.status === 'confirmed';
}
