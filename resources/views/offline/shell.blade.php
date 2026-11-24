{{--
    Offline app shell. Served by the service worker whenever the server can't
    be reached. It is cached anonymously, so it must never contain user data:
    the offline app fills the page from the device's local database.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0f2a4a">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="Rice Samples">
    <meta name="robots" content="noindex">
    <title>Rice Sample Tracking</title>
    <link rel="icon" href="/favicon-32.png" type="image/png" sizes="32x32">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    <link rel="manifest" href="/site.webmanifest">
    @fonts
    <script>
        // Business vocabulary only (config/rice.php) — no user data.
        window.RST_CONFIG = @json($config);
    </script>
    @vite(['resources/css/app.css', 'resources/js/offline.js'])
</head>
<body data-offline-app>
    <aside class="app-sidebar">
        <a href="#/" class="sidebar-brand">
            <img src="/images/logo-dark.png" alt="Ukasha Pacific International" class="brand-logo" style="height:38px;width:auto">
            <span class="brand-text">Rice Samples<small>Tracking &amp; Dispatch</small></span>
        </a>
        <nav class="sidebar-nav" data-offline-nav></nav>
        <div class="sidebar-footer d-flex align-items-center gap-2">
            <span class="avatar" data-user-initials>··</span>
            <div class="flex-grow-1 min-w-0">
                <div class="text-white fw-semibold small text-truncate" data-user-name></div>
                <div class="small text-truncate" style="color:var(--sidebar-muted)">Working on this device</div>
            </div>
        </div>
    </aside>

    <div class="app-main">
        <header class="app-topbar">
            <button class="btn btn-light btn-icon d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileNav" aria-label="Open menu"><i class="bi bi-list fs-5"></i></button>
            <h1 class="topbar-title flex-grow-1"><span class="d-lg-none" data-page-title>Rice Samples</span></h1>
            <div data-sync-indicator></div>
        </header>
        <main class="app-content mx-auto" id="offline-root">
            <div class="text-center text-muted py-5"><span class="spinner-border spinner-border-sm me-2"></span>Opening…</div>
        </main>
    </div>

    <nav class="bottom-nav" aria-label="Primary">
        <a href="#/"><i class="bi bi-grid-1x2"></i>Home</a>
        <a href="#/samples"><i class="bi bi-basket"></i>Samples</a>
        <div class="fab-wrap flex-fill d-flex justify-content-center">
            <button type="button" class="fab" data-bs-toggle="offcanvas" data-bs-target="#quickActions" aria-label="Quick actions"><i class="bi bi-plus-lg"></i></button>
        </div>
        <a href="#/dispatches"><i class="bi bi-send"></i>Dispatch</a>
        <a href="#/loading"><i class="bi bi-truck"></i>Loading</a>
    </nav>

    {{-- Mobile: full navigation drawer (same menu as online, filled by the offline app) --}}
    <div class="offcanvas offcanvas-start offcanvas-nav" tabindex="-1" id="mobileNav" style="width: 290px">
        <div class="offcanvas-header border-bottom">
            <div class="d-flex align-items-center gap-2">
                <img src="/images/logo-dark.png" alt="Ukasha Pacific International" class="brand-logo" style="height:40px;width:auto">
                <div><div class="fw-bold">Rice Samples</div><div class="small text-muted"><span data-user-name></span> · Working offline</div></div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body p-2"><nav data-offline-nav></nav></div>
    </div>

    <div class="offcanvas offcanvas-bottom action-sheet" tabindex="-1" id="quickActions">
        <div class="offcanvas-header pb-2"><h5 class="offcanvas-title fw-semibold">Quick actions</h5><button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button></div>
        <div class="list-group list-group-flush pb-2">
            <a href="#/samples/new" data-bs-dismiss="offcanvas" class="list-group-item list-group-item-action"><span class="stat-icon tone-navy"><i class="bi bi-basket"></i></span><span>Add sample</span></a>
            <a href="#/dispatches/new" data-bs-dismiss="offcanvas" class="list-group-item list-group-item-action"><span class="stat-icon tone-blue"><i class="bi bi-send"></i></span><span>Dispatch samples</span></a>
            <a href="#/loading" data-bs-dismiss="offcanvas" class="list-group-item list-group-item-action"><span class="stat-icon tone-teal"><i class="bi bi-truck"></i></span><span>Record loading</span></a>
            <a href="#/mills/new" data-bs-dismiss="offcanvas" class="list-group-item list-group-item-action"><span class="stat-icon tone-slate"><i class="bi bi-buildings"></i></span><span>Add rice mill</span></a>
            <a href="#/buyers/new" data-bs-dismiss="offcanvas" class="list-group-item list-group-item-action"><span class="stat-icon tone-purple"><i class="bi bi-people"></i></span><span>Add buyer</span></a>
        </div>
    </div>

    <div class="toast-stack" aria-live="polite"></div>
</body>
</html>
