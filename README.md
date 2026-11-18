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
| **Samples** | Auto-numbered (`SMP-0001`), linked to a mill and variety, optional photo from the camera |
| **Bundles** | Several samples received together (`BND-0001`); each sample keeps its own mill |
| **Dispatches** | Send any samples to any buyers in one step: one independent record (`DSP-0001`) per sample–buyer pair |
| **Buyer response** | Confirm (quantity, unit, rate, packing, date) or reject (reason, date); validated transitions |
| **Loading** | Confirmed orders only; multiple trucks per order (`LD-0001`) with quantities, bags, driver, responsible person and multiple photos; complete the order when done |
| **Records** | Filterable history of dispatches, confirmations, rejections, truck loadings and completed orders |
| **Search** | One search box across samples, dispatches, mills, buyers, bundles and trucks |
| **Users** | Admin creates employee logins, resets passwords, deactivates accounts |

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
| Samples, bundles, mills, buyers, varieties, dispatches, responses, loading | ✅ | ✅ |
| Manage users (Settings → Manage Users) | ✅ | ❌ (403) |
| Delete records that have no history (e.g. an unused mill or a pending dispatch) | ✅ | ❌ |
| Edit loaded trucks / reopen completed orders | ✅ | ❌ |

Records with history (dispatched samples, mills with samples, buyers with dispatches, loaded trucks) cannot be deleted by anyone.

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

Tests use an in-memory SQLite database and fake file storage, so they never touch your MySQL data. They cover authentication, role restrictions, user management, mill/sample/bundle linkage, multi-buyer dispatch, independent responses, the loading workflow, photo uploads, history pages, security headers and N+1 query limits.

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

### Backups

Back up both:
- the MySQL database (`mysqldump rice_tracking`)
- `storage/app/private` (sample and loading photos)

---

## Using it on an iPhone

1. Open the site in **Safari** and sign in (tick *Keep me signed in*).
2. Tap **Share → Add to Home Screen**. The app opens full-screen like a native app.
3. The bottom bar gives one-tap access to Home, Samples, Dispatch and Loading. The **+** button opens quick actions.
4. **Take photo** opens the camera directly. **Choose photos** picks several images from the library.
5. At a mill, use **Save & add another** to record several samples quickly, or **New bundle** to enter a whole bundle on one screen.

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
| `sample_bundles` | Groups of samples received together |
| `samples` | Each sample → one mill, one variety, optional bundle |
| `sample_dispatches` | Each sample → buyer dispatch with response and loading status |
| `loadings` | Trucks loaded against a confirmed dispatch |
| `loading_photos` | Photos per truck |
| `activities` | Audit trail (who did what, when) |
| `reference_sequences` | Counters for reference numbers |

Foreign keys use `RESTRICT` on delete so historical records can never be orphaned. Multi-record operations (bundles, multi-buyer dispatches, loadings with photos) run inside database transactions.

## Project structure

```
app/
  Enums/              DispatchStatus, LoadingStatus, TruckLoadingStatus, UserRole
  Http/Controllers/   One controller per module (+ Settings/UserController)
  Http/Requests/      Form Request validation per module
  Policies/           Authorization rules per model
  Services/           SampleService, DispatchService, LoadingService (workflow + transactions)
  Support/            ReferenceNumber, PhotoStorage, Cities
resources/
  views/              Blade views, components (form fields, picker, photo input, badges)
  css/app.css         Design system on top of Bootstrap 5
  js/modules/         Picker, bundle builder, photo compression, quick-add dialogs, UI helpers
```
