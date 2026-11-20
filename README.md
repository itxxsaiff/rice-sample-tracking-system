# Rice Sample Tracking System

A mobile-first Laravel application for a rice trading business: collect rice samples at mills, send them to buyers, record each buyer's confirmation or rejection, and track loading of confirmed orders truck by truck. All records are kept as permanent history.

It is **not** an ERP or accounting system. There are no payments, ledgers or invoices.

---

## Features

| Area | What it does |
| --- | --- |
| **Dashboard** | Responses awaiting buyers, orders to load, confirmed/rejected/completed counts, record totals, "waiting longest" lists, recent activity |
| **Rice mills** | Permanent profiles (unique per name + city) with the complete sample history of each mill |
| **Buyers** | Permanent profiles with every dispatch, response, agreed quantity/rate, packing and loading |
| **Rice varieties** | Editable list (1121, 1509, Super Basmati, … any variety); deactivate instead of delete |
| **Samples** | Auto-numbered from `SMP-0255` (`SAMPLE_NUMBER_START`), always 2 kg, linked to a mill and variety; **Add another sample** saves several independent samples from one form |
| **Dispatches** | Send any samples to any buyers in one step: one independent record (`DSP-0001`) per sample–buyer pair |
| **Buyer response** | Recorded in a popup from the dispatch list, dashboard or dispatch page: confirm (quantity in maund, rate per maund, bag size 5–100 kg, date) or reject (one tap, no reason); validated transitions |
| **Loading** | Confirmed orders only; multiple trucks per order (`LD-0001`) with quantities, bags, driver, responsible person and multiple photos; complete the order when done |
| **Records** | Filterable history of dispatches, confirmations, rejections, truck loadings and completed orders |
| **Search** | One search box across samples, dispatches, mills, buyers and trucks |
| **Users** | Admin creates employee logins, resets passwords, deactivates accounts |
| **Offline mode** | Installable app that keeps working without internet and syncs automatically when the connection returns (see below) |

### Workflow

```
Sample ──dispatch──▶ Dispatch (one per buyer)
                        │
              ┌─────────┴─────────┐
          Confirmed            Rejected  (kept in history, never loaded)
              │
      Loading: pending ─▶ in progress (truck 1, truck 2, …) ─▶ completed
```

- A sample can be sent to many buyers. Each buyer's response is independent.
- A sample cannot be re-sent to the same buyer while a dispatch to that buyer is still pending or confirmed. After a rejection it can be sent again.
- A response can be **reopened** (the previous answer stays in the activity log) until a truck has been recorded.
- An order can be completed only when every truck on it is marked *loaded*.

### Roles

| | Admin | Employee |
| --- | :-: | :-: |
| Samples, mills, buyers, varieties, dispatches, responses, loading | ✅ | ✅ |
| Manage users (Settings → Manage Users) | ✅ | ❌ (403) |
| Delete records that have no history (e.g. an unused mill or a pending dispatch) | ✅ | ❌ |
| Edit loaded trucks / reopen completed orders | ✅ | ❌ |

Records with history (dispatched samples, mills with samples, buyers with dispatches, loaded trucks) cannot be deleted by anyone.

---

## Offline mode (PWA)

The app is an installable Progressive Web App. Online it behaves exactly as before. When the server can't be reached (rice mills with no signal), the **same URLs open an offline version** that works from a copy of the data stored on the device. Changes made offline are queued and **sync automatically** when the connection returns.

### What works offline

| Area | Offline |
| --- | --- |
| Dashboard | Same sections as online, counted from the data on the device |
| Rice mills, buyers, varieties | View, search, profile with history, add, edit (mills/buyers), add variety |
| Samples | View, add one or several samples from one form |
| Dispatches | Send samples to one or more buyers (one record per buyer) |
| Buyer responses | Confirm or reject a pending dispatch in the response popup |
| Loading | Record trucks with photos, mark trucks loaded, complete the order |
| Records | Same tabs as online, for the data on the device |
| Online only | Global search, Manage Users, My Account, deleting, editing an existing response, editing loaded trucks (their menu items stay visible and explain this) |

Records not yet on the server carry a **Not synced** badge. Saving always says *"Saved on this device…"*; a separate *"N changes synced with the server"* message appears only when the server has accepted them. The device keeps the last `OFFLINE_HISTORY_DAYS` days (default **10**, counted back from today) of samples and dispatches, plus all open work (responses still awaited, orders still being loaded) whatever its age. Mills, buyers and varieties are always kept in full.

If the connection drops while a form is open on a normal online page, **Save** offers to keep the entry on the device instead of losing it.

### How it works

```
 Phone (Safari / Home Screen app)                          Laravel + MySQL
 ┌──────────────────────────────────┐                ┌──────────────────────────────┐
 │ Service worker  /sw.js           │  GET /sync/status   (reachability + session)   │
 │  - caches CSS/JS/fonts/icons     │ ─────────────────▶ │                            │
 │  - serves offline shell when     │  GET /sync/snapshot (data for offline use)     │
 │    the server can't be reached   │ ◀───────────────── │  OfflineSnapshotService    │
 │ IndexedDB (one DB per account)   │                    │                            │
 │  - local copy of records         │  POST /sync/operations (one queued change)     │
 │  - outbox of queued changes      │ ─────────────────▶ │  SyncOperationService:     │
 │  - photo blobs until uploaded    │ ◀───────────────── │   same Form Requests,      │
 └──────────────────────────────────┘   applied / rejected │   policies and services    │
                                        conflict / …    │   as the online forms      │
                                                        └──────────────────────────────┘
```

- **Stable ids.** Every record has a `uuid` (existing rows were backfilled), so a mill created offline can be used by an offline sample and an offline dispatch; the server resolves them in order.
- **Same rules.** Each queued change is validated with the same Form Request (including `authorize()`), and executed by the same service, as the online form.
- **Exactly once.** The operation uuid is recorded in `sync_operations` in the same database transaction as the change. A retry after a lost response returns the stored result; creates are also idempotent by record uuid. Interrupted syncs resume without duplicates.
- **Conflicts.** Mills, buyers, dispatches and loadings have a `version`. An offline edit made against an old version is **not** applied; the Sync panel shows *server now* vs *your change* with **Keep server version** / **Use my change**.
- **When sync runs.** On opening the app, returning to it, reconnecting, every minute while changes wait, and **Sync now**. iOS cannot sync while the app is closed, so nothing relies on Background Sync.
- **Accounts.** Each user has a separate local database. Changes only sync under the account that made them. Signing out with unsynced changes warns first and keeps them for that user; with none, the local copy is deleted. If the server reports the session has ended, offline data is hidden until the user signs in again (changes are kept). A device that hasn't reached the server for `OFFLINE_MAX_DAYS` (default 30) stops showing offline data.
- **Caching.** Only public files are cached (build assets, icons, and an offline shell with no user data). Signed-in pages, `/sync/*`, lookups and photos are never cached. A new deployment produces a new service-worker version automatically; the worker never touches the IndexedDB outbox.

### iPhone notes

1. Open the site in Safari and tick **Keep me signed in** when signing in.
2. **Share → Add to Home Screen.** Use the app from the Home Screen icon: iOS keeps Home Screen app data safely, while plain Safari tabs may clear website data after 7 days without use.
3. Open the app once while online before going to an area without signal, so the latest data is on the phone.
4. Photos are compressed on the phone (about 0.3–0.8 MB each) and stay on the phone until the server confirms they are stored.
5. Tap the cloud icon in the top bar to see what is waiting, retry, or resolve a conflict.

### Testing

```bash
php artisan test                      # includes sync API tests (tests/Feature/Sync)
BASE_URL=http://127.0.0.1:8000 ADMIN_EMAIL=… ADMIN_PASSWORD=… npm run test:browser
```

The browser tests (local database only, never production) cut the network in Chrome and run: offline samples (several from one form)/dispatch/response popup/loading with photos, offline reload, automatic sync, interrupted sync, conflicting edits, account switching and session expiry. Real-device checks still needed on an iPhone: camera capture, Add to Home Screen, and reopening the installed app in airplane mode.

---

## Requirements

- PHP 8.3+ with `pdo_mysql`, `mbstring`, `fileinfo`, `gd`, `openssl`
- Composer 2
- MySQL 8+
- Node.js 20+ and npm (to build front-end assets)

## Installation

```bash
git clone https://github.com/itxxsaiff/rice-sample-tracking-system.git
cd rice-sample-tracking-system

composer install
cp .env.example .env
php artisan key:generate
```

Edit `.env` with your database credentials:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=rice_tracking
DB_USERNAME=root
DB_PASSWORD=your-password
```

Then:

```bash
php artisan migrate --seed      # creates tables and the default rice varieties
php artisan app:create-admin    # prompts for name, email and password of the first admin
npm install && npm run build    # compiles CSS/JS
php artisan serve               # http://localhost:8000
```

The admin then creates employee accounts in **Settings → Manage Users**. Everyone signs in at the same URL.

> No default credentials exist. The first admin is always created with `php artisan app:create-admin`.

## Running tests

```bash
php artisan test
```

Tests use an in-memory SQLite database and fake file storage, so they never touch your MySQL data. They cover authentication, role restrictions, user management, mill/sample linkage, multi-sample form, multi-buyer dispatch, independent responses, the loading workflow, photo uploads, history pages, security headers and N+1 query limits.

---

## Production deployment

1. `.env`: `APP_ENV=production`, `APP_DEBUG=false`, a real `APP_URL` with **https**, and production DB credentials.
2. `composer install --no-dev --optimize-autoloader`
3. `npm ci && npm run build`
4. `php artisan migrate --force && php artisan db:seed --force` (varieties only; safe to re-run)
5. `php artisan app:create-admin`
6. `php artisan config:cache && php artisan route:cache && php artisan view:cache`
7. The web server user must be able to write to `storage/` and `bootstrap/cache/`.
8. Serve over **HTTPS**. Safari camera access and secure cookies depend on it.

### Photo uploads

Photos are resized in the browser (max 2000 px JPEG) before upload. This keeps iPhone uploads fast on mobile data and strips GPS/EXIF data. Even so, allow reasonable limits in `php.ini`:

```ini
upload_max_filesize = 16M
post_max_size = 128M
max_file_uploads = 20
```

Photos are stored on the **private** disk (`storage/app/private`) and are served only to signed-in users. Back up this folder together with the database.

### Updating a live installation (backup → deploy → rollback)

The offline release adds columns (`uuid`, `version`) and one table (`sync_operations`). The migrations are additive; nothing is dropped or rewritten.

```bash
cd /path/to/sales-tracking
php artisan down                                            # optional maintenance page

# 1. Backups (keep them outside the web folder)
mysqldump -h HOST -u USER -p DBNAME > ~/backup-$(date +%F).sql
tar -czf ~/photos-$(date +%F).tgz storage/app/private
cp .env ~/sales-tracking.env.backup

# 2. Replace the code — KEEP .env and storage/ (uploaded photos live there)
find . -mindepth 1 -maxdepth 1 ! -name '.env' ! -name 'storage' ! -name 'release.zip' -exec rm -rf {} +
unzip -oq release.zip && rm release.zip
chmod -R 775 storage bootstrap/cache

# 3. Migrate and cache
php artisan migrate --force
php artisan optimize:clear && php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan up
```

Check: `/sw.js` returns JavaScript, `/offline-shell` returns 200, `/sync/status` returns JSON.

**Rollback:** `php artisan migrate:rollback --step=2` removes only the offline columns/table, then restore the previous code. To restore everything instead: `mysql -u USER -p DBNAME < ~/backup-YYYY-MM-DD.sql`. Devices with unsynced offline changes keep them; they sync once the offline release is back.

### Backups

Back up both:
- the MySQL database (`mysqldump rice_tracking`)
- `storage/app/private` (sample and loading photos)

---

## Using it on an iPhone

1. Open the site in **Safari** and sign in (tick *Keep me signed in*).
2. Tap **Share → Add to Home Screen**. The app opens full-screen like a native app.
3. The bottom bar gives one-tap access to Home, Samples, Dispatch and Loading. The **+** button opens quick actions.
4. When recording a truck, **Take photo** opens the camera directly. **Choose photos** picks several images from the library.
5. At a mill, tap **Add another sample** to record several samples on one screen; each is saved as its own sample.

---

## Configuration

Business vocabulary lives in [`config/rice.php`](config/rice.php):

- sample units (g, kg, packets…), order units (maund, kg, ton, bags) and rate units
- packing types (PP bag, jute bag, …)
- city suggestions
- reference number prefixes (`SMP`, `BND`, `DSP`, `LD`)
- photo size and count limits

Stored values are plain strings, so old records stay readable if an option is later removed.

## Data model

| Table | Purpose |
| --- | --- |
| `users` | Accounts with `role` (admin/employee) and `is_active` |
| `mills`, `buyers` | Permanent profiles |
| `rice_varieties` | Variety list (active/inactive) |
| `sample_bundles` | No longer used (bundles were removed); kept so no existing data is deleted |
| `samples` | Each sample → one mill, one variety (`sample_bundle_id` no longer used) |
| `sample_dispatches` | Each sample → buyer dispatch with response and loading status |
| `loadings` | Trucks loaded against a confirmed dispatch |
| `loading_photos` | Photos per truck |
| `activities` | Audit trail (who did what, when) |
| `reference_sequences` | Counters for reference numbers |
| `sync_operations` | Offline changes already applied (exactly-once sync) |

Foreign keys use `RESTRICT` on delete so historical records can never be orphaned. Multi-record operations (several samples from one form, multi-buyer dispatches, loadings with photos) run inside database transactions.

## Project structure

```
app/
  Enums/              DispatchStatus, LoadingStatus, TruckLoadingStatus, UserRole
  Http/Controllers/   One controller per module (+ Settings/UserController)
  Http/Requests/      Form Request validation per module
  Policies/           Authorization rules per model
  Services/           Sample/Dispatch/Loading/Directory services, OfflineSnapshotService
  Support/            ReferenceNumber, PhotoStorage, Cities
  Sync/               SyncOperationService (offline changes), SyncPresenter, conflict types
resources/
  views/              Blade views, components (form fields, picker, photo input, badges)
  css/app.css         Design system on top of Bootstrap 5
  js/modules/         Picker, sample rows, response popup, photo compression, quick-add dialogs, UI helpers
  js/offline/         IndexedDB, outbox, sync engine, status panel, form capture
  js/offline/app/     Offline app screens (served by the service worker)
  pwa/sw.js           Service worker template (served at /sw.js)
```
