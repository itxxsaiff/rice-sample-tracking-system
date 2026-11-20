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
const tinker = (code) => execFileSync('php', ['artisan', 'tinker', `--execute=${code}`], { cwd: APP }).toString().trim();
const browser = await puppeteer.launch({ executablePath: CHROME, headless: 'new' });
const page = await browser.newPage();
await page.setViewport({ width: 440, height: 956, isMobile: true, hasTouch: true });
const errors = [];
page.on('pageerror', (e) => errors.push('PAGE ' + e.message));
page.on('console', (m) => { if (m.type() === 'error' && !/Failed to load resource|ERR_INTERNET_DISCONNECTED|net::ERR/.test(m.text())) errors.push('CONSOLE ' + m.text()); });
const dialogs = [];
page.on('dialog', async (d) => { dialogs.push(d.message().split('\n')[0]); await d.accept(); });
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const step = (ok, s) => console.log(ok ? 'OK  ' : 'FAIL', s);
const outbox = () => page.evaluate(async () => {
    const u = JSON.parse(localStorage.getItem('rst.activeUser') || 'null');
    if (!u) return { count: -1, statuses: [] };
    const req = indexedDB.open(`rst-user-${u.id}`);
    const db = await new Promise((res) => { req.onsuccess = () => res(req.result); });
    if (!db.objectStoreNames.contains('ops')) { db.close(); return { count: 0, statuses: [] }; }
    const ops = await new Promise((res) => { const r = db.transaction('ops').objectStore('ops').getAll(); r.onsuccess = () => res(r.result); });
    db.close();
    return { count: ops.length, statuses: ops.map((o) => `${o.type}:${o.status}`) };
});
const waitSynced = async (secs = 40) => { let b; for (let i = 0; i < secs; i++) { await sleep(1000); b = await outbox(); if (b.count === 0) break; } return b; };
const login = async (email, pass) => {
    await page.goto(base + '/login', { waitUntil: 'networkidle0' });
    await page.type('input[name=email]', email); await page.type('input[name=password]', pass);
    await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle0' }), page.click('button[type=submit]')]);
};
const goOnline = async () => { await page.setOfflineMode(false); await page.evaluate(() => window.dispatchEvent(new Event('online'))); };

tinker(`App\\Models\\User::where("email","field.e2e@example.com")->exists() || tap(new App\\Models\\User(["name"=>"Field Employee","email"=>"field.e2e@example.com","password"=>"field-pass-1"]))->save();`);

await login(ADMIN_EMAIL, ADMIN_PASSWORD);
await page.waitForFunction(() => !!navigator.serviceWorker?.controller, { timeout: 15000 }).catch(() => {});
await page.reload({ waitUntil: 'networkidle0' }); await sleep(2500);

// A. Online save still works normally (capture must not interfere).
await page.goto(base + '/mills/create', { waitUntil: 'networkidle0' });
await page.type('input[name=name]', `Online Mill ${stamp}`); await page.type('input[name=city]', 'Lahore');
await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle0' }), page.click('.action-bar .btn-primary')]);
step(/\/mills\/\d+$/.test(new URL(page.url()).pathname), `A online form saves normally → ${new URL(page.url()).pathname}`);

// B. Connection drops while a form is open on a normal page.
await page.goto(base + '/buyers/create', { waitUntil: 'networkidle0' });
await page.type('input[name=name]', `Captured Buyer ${stamp}`); await page.type('input[name=city]', 'Multan');
await page.setOfflineMode(true);
await page.click('.action-bar .btn-primary');
await page.waitForFunction(() => location.hash.startsWith('#/buyers/'), { timeout: 15000 }).catch(() => {});
await sleep(1200);
step(location => true, `B offline save on online page → ${await page.evaluate(() => location.pathname + location.hash)} | dialog: "${dialogs.at(-1)}"`);
console.log('     page shows:', await page.$eval('#offline-root h1', (e) => e.textContent).catch(() => 'n/a'), '| toast:', await page.$eval('.toast-stack', (e) => e.innerText.trim()).catch(() => ''));
await goOnline();
let box = await waitSynced();
const captured = tinker(`echo App\\Models\\Buyer::where("name","Captured Buyer ${stamp}")->count();`);
step(box.count === 0 && captured === '1', `B captured buyer synced once (count in DB: ${captured})`);

// 6. Interrupted sync: queue 4 samples offline, drop the connection after the first ones are sent.
await page.goto(base + '/', { waitUntil: 'networkidle0' }); await sleep(1500);
await page.setOfflineMode(true);
await page.goto(base + '/samples/create', { waitUntil: 'domcontentloaded' }); await sleep(1200);
// Four rows in one "Add Sample" form → four independent sample operations.
for (let i = 1; i <= 4; i++) {
    if (i > 1) { await page.click('[data-add-row]'); await sleep(300); }
    const row = `.sample-row:nth-child(${i})`;
    await page.evaluate((r) => { const el = document.querySelector(`${r} [data-local-picker] [data-picker-input]`); el.focus(); el.value = 'Madina'; el.dispatchEvent(new Event('input', { bubbles: true })); }, row);
    await sleep(200);
    await page.evaluate((r) => document.querySelector(`${r} .picker-option`).dispatchEvent(new PointerEvent('pointerdown', { bubbles: true })), row);
    await page.select(`${row} select[data-variety-select]`, await page.$eval(`${row} select[data-variety-select] option:nth-child(2)`, (o) => o.value));
    await page.type(`${row} textarea[name$="[notes]"]`, `interrupt-${stamp}-${i}`);
}
await page.click('.action-bar button.btn-primary'); await sleep(900);
console.log('     queued:', (await outbox()).count);
const client = await page.createCDPSession();
await client.send('Network.emulateNetworkConditions', { offline: false, latency: 1200, downloadThroughput: 200000, uploadThroughput: 200000 });
await page.setOfflineMode(false); await page.evaluate(() => window.dispatchEvent(new Event('online')));
for (let i = 0; i < 60; i++) { await sleep(250); const b = await outbox(); if (b.count > 0 && b.count < 4) break; }
await page.setOfflineMode(true);
box = await outbox();
console.log('     connection cut with', box.count, 'left:', box.statuses.join(', '));
await sleep(3000);
await client.send('Network.emulateNetworkConditions', { offline: false, latency: 0, downloadThroughput: -1, uploadThroughput: -1 });
await goOnline();
box = await waitSynced();
const made = tinker(`echo App\\Models\\Sample::where("notes","like","interrupt-${stamp}-%")->count();`);
step(box.count === 0 && made === '4', `6 resumed after interruption: ${made} samples in DB (expected 4, no duplicates)`);

// 7. Conflict: edit a mill offline while someone else edits it online.
const millUuid = tinker(`echo App\\Models\\Mill::where("name","Online Mill ${stamp}")->value("uuid");`);
await page.goto(base + '/', { waitUntil: 'networkidle0' }); await sleep(2500);
await page.setOfflineMode(true);
await page.goto(base + '/mills/create', { waitUntil: 'domcontentloaded' }); await sleep(800);
await page.evaluate((u) => { location.hash = `#/mills/${u}/edit`; }, millUuid); await sleep(800);
await page.$eval('input[name=phone]', (e) => { e.value = ''; }); await page.type('input[name=phone]', '0300-MINE');
await page.click('.action-bar .btn-primary'); await sleep(800);
tinker(`$m=App\\Models\\Mill::where("uuid","${millUuid}")->first(); $m->update(["phone"=>"0300-OTHER"]);`);
await goOnline();
for (let i = 0; i < 30; i++) { await sleep(1000); const b = await outbox(); if (b.statuses.some((s) => s.endsWith(':conflict'))) break; }
box = await outbox();
const pill = await page.$eval('[data-sync-indicator] button', (e) => e.getAttribute('aria-label')).catch(() => '');
step(box.statuses.some((s) => s === 'mill.update:conflict'), `7 conflict detected, not applied (server phone: ${tinker(`echo App\\Models\\Mill::where("uuid","${millUuid}")->value("phone");`)}) | pill: ${pill}`);
await page.click('[data-sync-indicator] button'); await sleep(700);
await page.screenshot({ path: SP + 'sync-conflict.png' });
await page.click('[data-apply-mine]');
box = await waitSynced();
step(box.count === 0, `7 'Use my change' applied → server phone: ${tinker(`echo App\\Models\\Mill::where("uuid","${millUuid}")->value("phone");`)}`);
await page.keyboard.press('Escape'); await sleep(500);

// 9. Account switching: user A has an unsynced change, signs out, user B signs in.
await page.goto(base + '/', { waitUntil: 'networkidle0' }); await sleep(1500);
await page.setOfflineMode(true);
await page.goto(base + '/mills/create', { waitUntil: 'domcontentloaded' }); await sleep(800);
await page.type('input[name=name]', `Account A Mill ${stamp}`); await page.type('input[name=city]', 'Okara');
await page.click('.action-bar .btn-primary'); await sleep(800);
await page.setOfflineMode(false);
// Block the sync endpoint so A's change stays pending while A signs out.
await page.setRequestInterception(true);
const blocker = (r) => (r.url().includes('/sync/operations') ? r.abort() : r.continue());
page.on('request', blocker);
await page.goto(base + '/', { waitUntil: 'networkidle0' }); await sleep(1500);
await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle0' }), page.evaluate(() => document.querySelector('form[action$="/logout"] button').click())]);
step(dialogs.at(-1)?.includes('not reached the server'), `9 sign-out warned: "${dialogs.at(-1)}"`);
page.off('request', blocker);
await page.setRequestInterception(false);
await login('field.e2e@example.com', 'field-pass-1'); await sleep(4000);
const leaked = tinker(`echo App\\Models\\Mill::where("name","Account A Mill ${stamp}")->count();`);
step(leaked === '0', `9 A's pending change was NOT synced under user B (in DB: ${leaked})`);
await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle0' }), page.evaluate(() => document.querySelector('form[action$="/logout"] button').click())]);
await login(ADMIN_EMAIL, ADMIN_PASSWORD);
box = await waitSynced();
const byA = tinker(`$m=App\\Models\\Mill::where("name","Account A Mill ${stamp}")->first(); echo $m ? "created_by=".$m->created_by : "missing";`);
const adminId = tinker(`echo App\\Models\\User::where("email","${ADMIN_EMAIL}")->value("id");`);
step(box.count === 0 && byA === `created_by=${adminId}`, `9 A's change synced after A signed back in (${byA})`);

// 9b. Session expiry with pending changes.
await page.setOfflineMode(true);
await page.goto(base + '/mills/create', { waitUntil: 'domcontentloaded' }); await sleep(800);
await page.type('input[name=name]', `Expiry Mill ${stamp}`); await page.type('input[name=city]', 'Sahiwal');
await page.click('.action-bar .btn-primary'); await sleep(800);
tinker('DB::table("sessions")->delete();');
const cookies = await page.cookies(); await page.deleteCookie(...cookies.filter((c) => /remember_web/.test(c.name)));
await goOnline(); await sleep(5000);
box = await outbox();
const lockedUser = await page.evaluate(() => JSON.parse(localStorage.getItem('rst.activeUser'))?.locked);
step(box.count === 1 && lockedUser === true, `9b session expired: change kept (${box.count} pending), device locked until sign-in`);
await page.goto(base + '/samples', { waitUntil: 'networkidle0' });
step(new URL(page.url()).pathname === '/login', '9b server sends the user to sign in');
await login(ADMIN_EMAIL, ADMIN_PASSWORD);
box = await waitSynced();
step(box.count === 0 && tinker(`echo App\\Models\\Mill::where("name","Expiry Mill ${stamp}")->count();`) === '1', '9b after signing in again the kept change synced');

console.log(errors.length ? 'JS ERRORS:\n' + errors.join('\n') : 'No JS errors');
await browser.close();
