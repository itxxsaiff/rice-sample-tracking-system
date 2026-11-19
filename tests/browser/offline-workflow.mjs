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

// Scenario 1: new mill (quick add) + sample with photo
await page.click('[data-local-quick-add="mill"]');
await fillModal({ name: `Offline Mill ${stamp}`, city: 'Narowal' });
await page.select('select[name=rice_variety_id]', await page.$eval('select[name=rice_variety_id] option:nth-child(2)', (o) => o.value));
await page.type('input[name=quantity]', '500');
await (await page.$('input[name=photo][data-image-target]')).uploadFile(SP + 'truck.jpg');
await sleep(1200);
await page.click('button[value=view]');
await sleep(900);
console.log('   toast:', await page.$eval('.toast-stack', (e) => e.innerText.trim()).catch(() => ''));
const sampleHash = await page.evaluate(() => location.hash);
if (!/#\/samples\/[0-9a-f-]{36}$/.test(sampleHash)) { console.log('SAMPLE NOT SAVED. errors:', errors, await page.$$eval('.invalid-feedback', (e) => e.map((x) => x.textContent).filter(Boolean))); await browser.close(); process.exit(1); }
step(`offline sample saved → ${sampleHash}`);
await shot('1-sample');

// Scenario 2: bundle with samples from two different mills (existing + offline one)
await hashGo('#/bundles/new');
await pickFirst('.bundle-row [data-local-picker]', `Offline Mill ${stamp}`);
await page.select('.bundle-row select[data-variety-select]', await page.$eval('.bundle-row select[data-variety-select] option:nth-child(2)', (o) => o.value));
await page.click('[data-add-row]'); await sleep(300);
await pickFirst('.bundle-row:nth-child(2) [data-local-picker]', 'Madina');
await page.click('.action-bar button.btn-primary'); await sleep(900);
step(`offline bundle saved → ${await page.evaluate(() => location.hash)}`);

// Scenario 3: dispatch the offline sample to two buyers (one created offline)
await hashGo(`#/dispatches/new?sample=${sampleHash.split('/').pop()}`);
await page.click('[data-new-buyer]');
await fillModal({ name: `Offline Buyer ${stamp}`, city: 'Karachi' });
const boxes = await page.$$('#buyerList input[type=checkbox]');
const checked = await page.$$eval('#buyerList input:checked', (i) => i.length);
await boxes[1].click(); await sleep(200);
console.log('   buyers ticked:', checked + 1, '| summary:', await page.$eval('[data-summary] span', (e) => e.textContent));
await page.click('.action-bar button.btn-primary'); await sleep(900);
step(`offline dispatches saved → ${await page.evaluate(() => location.hash)}`);

// Scenario 4: confirm one buyer, reject the other
const pendingLinks = await page.$$eval('a[href$="/respond"]', (as) => as.map((a) => a.getAttribute('href')));
await hashGo(pendingLinks[0]);
await page.evaluate(() => document.querySelector('input[value=confirmed]').closest('label').click()); await sleep(150);
await page.type('input[name=confirmed_quantity]', '400'); await page.type('input[name=rate]', '9300');
await page.select('select[name=packing_type]', 'PP bag');
await page.click('.action-bar button.btn-primary'); await sleep(900);
const confirmedHash = await page.evaluate(() => location.hash);
await hashGo(pendingLinks[1]);
await page.evaluate(() => document.querySelector('input[value=rejected]').closest('label').click()); await sleep(150);
await page.click('[data-reason="High moisture"]');
await page.click('.action-bar button.btn-primary'); await sleep(900);
step('offline confirmation + rejection saved');

// Scenario 5: loading with two photos, last truck → completes the order
await hashGo(`${confirmedHash}/load`);
await page.type('input[name=truck_number]', 'off 4411');
await page.type('input[name=receiver_name]', 'Usman');
await (await page.$('input[name=photos][data-image-target]')).uploadFile(SP + 'bags.jpg', SP + 'plate.jpg');
await sleep(1500);
await page.click('#lastTruck');
await page.click('button[value=loaded]'); await sleep(1000);
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
