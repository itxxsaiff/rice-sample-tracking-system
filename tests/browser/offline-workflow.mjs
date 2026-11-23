import puppeteer from 'puppeteer-core';
import { execFileSync } from 'child_process';
import { mkdtempSync } from 'fs';
import { tmpdir } from 'os';
import { join } from 'path';

/*
 * Browser test for the offline app. Requires the app running locally with an admin account.
 *   BASE_URL=http://127.0.0.1:8000 ADMIN_EMAIL=… ADMIN_PASSWORD=… CHROME_PATH=… node tests/browser/<file>.mjs
 * Uses the local database (creates test records) — never run against production.
 */
const base = process.env.BASE_URL || 'http://127.0.0.1:8000';
const ADMIN_EMAIL = process.env.ADMIN_EMAIL;
const ADMIN_PASSWORD = process.env.ADMIN_PASSWORD;
const CHROME = process.env.CHROME_PATH || '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
if (!ADMIN_EMAIL || !ADMIN_PASSWORD) { console.error('Set ADMIN_EMAIL and ADMIN_PASSWORD'); process.exit(2); }
const APP = process.cwd();
const SP = mkdtempSync(join(tmpdir(), 'rst-browser-')) + '/';
for (const [name, rgb] of [['truck', '70,110,160'], ['bags', '200,180,140'], ['plate', '90,90,90']]) {
    const file = `${SP}${name}.jpg`;
    execFileSync('php', ['-r', `$i=imagecreatetruecolor(1600,1200);imagefill($i,0,0,imagecolorallocate($i,${rgb}));imagejpeg($i,'${file}',85);`]);
}
const stamp = Date.now().toString().slice(-5);
const browser = await puppeteer.launch({ executablePath: CHROME, headless: 'new' });
const page = await browser.newPage();
await page.setViewport({ width: 440, height: 956, isMobile: true, hasTouch: true });
const errors = [];
page.on('pageerror', (e) => errors.push('PAGE ' + e.message));
page.on('console', (m) => { if (m.type() === 'error' && !/Failed to load resource|ERR_INTERNET_DISCONNECTED/.test(m.text())) errors.push('CONSOLE ' + m.text()); });
page.on('dialog', async (d) => { await d.accept(d.type() === 'prompt' ? `E2E-V${stamp}` : undefined); });
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const step = (s) => console.log('OK', s);
const shot = (n) => page.screenshot({ path: `${SP}off-${n}.png` });
const hashGo = async (hash) => { await page.evaluate((h) => { location.hash = h; }, hash); await sleep(700); };
const outbox = () => page.evaluate(async () => {
    const u = JSON.parse(localStorage.getItem('rst.activeUser'));
    const req = indexedDB.open(`rst-user-${u.id}`);
    const db = await new Promise((res) => { req.onsuccess = () => res(req.result); });
    const all = (s) => new Promise((res) => { const r = db.transaction(s).objectStore(s).getAll(); r.onsuccess = () => res(r.result); });
    const ops = await all('ops');
    db.close();
    return { count: ops.length, statuses: ops.map((o) => `${o.type}:${o.status}${o.error ? '(' + o.error + ')' : ''}`), photos: (await (async () => 0)()) };
});
const pickFirst = (scopeSel, text) => page.evaluate(async (sel, t) => {
    const scope = document.querySelector(sel);
    const input = scope.querySelector('[data-picker-input]');
    input.focus(); input.value = t; input.dispatchEvent(new Event('input', { bubbles: true }));
    await new Promise((r) => setTimeout(r, 150));
    scope.querySelector('.picker-option').dispatchEvent(new PointerEvent('pointerdown', { bubbles: true }));
}, scopeSel, text);
const fillModal = async (fields) => {
    await page.waitForSelector('.modal.show form', { timeout: 5000 });
    for (const [n, v] of Object.entries(fields)) await page.type(`.modal.show [name=${n}]`, v);
    await page.click('.modal.show button.btn-primary');
    await sleep(900);
};

// ---- online: sign in, let the app download its offline copy
await page.goto(base + '/login', { waitUntil: 'networkidle0' });
await page.type('input[name=email]', ADMIN_EMAIL); await page.type('input[name=password]', ADMIN_PASSWORD);
await Promise.all([page.waitForNavigation(), page.click('button[type=submit]')]);
await page.waitForFunction(() => !!navigator.serviceWorker?.controller, { timeout: 15000 }).catch(() => {});
await page.reload({ waitUntil: 'networkidle0' });
await sleep(2500);
step('online, service worker active, local copy downloaded');

// ---- OFFLINE
await page.setOfflineMode(true);
await page.goto(base + '/samples/create', { waitUntil: 'domcontentloaded' });
await sleep(1200);
console.log('   offline route:', await page.evaluate(() => location.hash), '| title:', await page.$eval('[data-page-title]', (e) => e.textContent));

// Scenarios 1 + 2: new mill (quick add) + several independent samples in one form (two mills)
await page.click('[data-local-quick-add="mill"]');
await fillModal({ name: `Offline Mill ${stamp}`, city: 'Narowal' });
const firstVariety = await page.$eval('.sample-row select[data-variety-select] option:nth-child(2)', (o) => o.value);
await page.select('.sample-row select[data-variety-select]', firstVariety);
const noExtraFields = await page.evaluate(() => !document.querySelector('[name*=photo], [name*=sample_number], [name*=quantity]'));
console.log('   no photo / sample no. / quantity fields:', noExtraFields);
await page.click('[data-add-row]'); await sleep(300);
await pickFirst('.sample-row:nth-child(2) [data-local-picker]', 'Madina');
await page.select('.sample-row:nth-child(2) select[data-variety-select]', firstVariety);
await page.click('.action-bar button.btn-primary'); await sleep(900);
console.log('   toast:', await page.$eval('.toast-stack', (e) => e.innerText.trim()).catch(() => ''));
if ((await page.evaluate(() => location.hash)) !== '#/samples') { console.log('SAMPLES NOT SAVED. errors:', errors, await page.$$eval('.invalid-feedback', (e) => e.map((x) => x.textContent).filter(Boolean))); await browser.close(); process.exit(1); }
const sampleUuid = await page.evaluate(async (name) => {
    const u = JSON.parse(localStorage.getItem('rst.activeUser'));
    const req = indexedDB.open(`rst-user-${u.id}`);
    const db = await new Promise((res) => { req.onsuccess = () => res(req.result); });
    const all = (st) => new Promise((res) => { const r = db.transaction(st).objectStore(st).getAll(); r.onsuccess = () => res(r.result); });
    const mill = (await all('mills')).find((m) => m.name === name);
    const sample = (await all('samples')).find((x) => x.mill_uuid === mill.uuid);
    db.close();
    return sample.uuid;
}, `Offline Mill ${stamp}`);
step('two offline samples saved from one form');
await shot('1-samples');

// Scenario 3: dispatch the offline sample to two buyers (one created offline)
await hashGo(`#/dispatches/new?sample=${sampleUuid}`);
await page.click('[data-new-buyer]');
await fillModal({ name: `Offline Buyer ${stamp}`, city: 'Karachi' });
const boxes = await page.$$('[data-group] [data-list="buyers"] input[type=checkbox]');
const checked = await page.$$eval('[data-group] [data-list="buyers"] input:checked', (i) => i.length);
await boxes[1].click(); await sleep(200);
console.log('   buyers ticked:', checked + 1, '| summary:', await page.$eval('[data-summary] span', (e) => e.textContent));
// "Add another dispatch" adds a second block; taking it away again leaves the first as it was.
await page.click('[data-add-group]'); await sleep(200);
const blocks = await page.$$eval('[data-group]', (g) => g.length);
await page.evaluate(() => [...document.querySelectorAll('[data-group]')].at(-1).querySelector('[data-remove-group]').click()); await sleep(200);
console.log('   dispatch blocks: added ->', blocks, '| after taking one away ->', await page.$$eval('[data-group]', (g) => g.length));
if (blocks !== 2) throw new Error('Add another dispatch did not add a block');
await page.click('.action-bar button.btn-primary'); await sleep(900);
step(`offline dispatches saved → ${await page.evaluate(() => location.hash)}`);

// Scenario 4: confirm one buyer, reject the other (response popup; rejection needs no reason)
await hashGo('#/dispatches?status=pending');
const pending = await page.evaluate(async (sampleId) => {
    const u = JSON.parse(localStorage.getItem('rst.activeUser'));
    const req = indexedDB.open(`rst-user-${u.id}`);
    const db = await new Promise((res) => { req.onsuccess = () => res(req.result); });
    const rows = await new Promise((res) => { const r = db.transaction('dispatches').objectStore('dispatches').getAll(); r.onsuccess = () => res(r.result); });
    db.close();
    return rows.filter((d) => d.sample_uuid === sampleId).map((d) => d.uuid);
}, sampleUuid);
await page.click(`[data-local-respond="${pending[0]}"]`);
await page.waitForSelector('.modal.show form', { timeout: 5000 }); await sleep(300);
await page.evaluate(() => document.querySelector('.modal.show input[value=confirmed]').closest('label').click()); await sleep(150);
console.log('   unit selects in popup:', await page.$$eval('.modal.show select', (s) => s.map((x) => x.name).join(',')));
await page.type('.modal.show input[name=confirmed_quantity]', '400'); await page.type('.modal.show input[name=rate]', '9300');
await page.select('.modal.show select[name=packing_type]', '50 kg');
await page.click('.modal.show button.btn-primary'); await sleep(1000);
const confirmedHash = `#/dispatches/${pending[0]}`;
await page.click(`[data-local-respond="${pending[1]}"]`);
await page.waitForSelector('.modal.show form', { timeout: 5000 }); await sleep(300);
await page.evaluate(() => document.querySelector('.modal.show input[value=rejected]').closest('label').click()); await sleep(150);
await page.click('.modal.show button.btn-primary'); await sleep(1000);
step('offline confirmation + rejection saved from the popup');

// Scenario 5: loading with two photos, last truck → completes the order
await hashGo(`${confirmedHash}/load`);
await page.type('input[name=truck_number]', 'off 4411');
console.log('   receiver / unit inputs:', await page.$$eval('[name=receiver_name], [name=quantity_unit]', (e) => e.length), '| quantity prefilled:', await page.$eval('input[name=quantity]', (e) => e.value));
await (await page.$('input[name=photos][data-image-target]')).uploadFile(SP + 'bags.jpg', SP + 'plate.jpg');
await sleep(1500);
await page.click('.action-bar button.btn-primary'); await sleep(1000); // full quantity → completes by itself
console.log('   order status offline:', await page.$eval('.status-badge', (e) => e.textContent.trim()));
await shot('5-order');

// Scenario 8: reload while offline — pending changes survive
let box = await outbox();
console.log('   outbox before reload:', box.count);
await page.reload({ waitUntil: 'domcontentloaded' }); await sleep(1500);
box = await outbox();
console.log('   outbox after reload :', box.count, '| page:', await page.$eval('[data-page-title]', (e) => e.textContent));
step('pending changes survive an offline reload');
await hashGo('#/');
await shot('8-home-offline');

// ---- back ONLINE: sync must run automatically
await page.setOfflineMode(false);
await page.evaluate(() => window.dispatchEvent(new Event('online')));
for (let i = 0; i < 40; i++) { await sleep(1000); box = await outbox(); if (box.count === 0) break; }
console.log('   outbox after reconnect:', box.count, box.statuses.join(' | '));
step(box.count === 0 ? 'everything synced automatically' : 'SYNC INCOMPLETE');
console.log(JSON.stringify({ stamp }));
console.log(errors.length ? 'JS ERRORS:\n' + errors.join('\n') : 'No JS errors');
await browser.close();
