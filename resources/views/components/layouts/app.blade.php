@props(['title' => null, 'back' => null, 'actionBar' => false])
<!DOCTYPE html>
<html lang="en">
<head>
    @include('partials.head', ['title' => $title])
</head>
<body @class(['has-action-bar' => $actionBar])>
    <aside class="app-sidebar">
        <a href="{{ route('dashboard') }}" class="sidebar-brand">
            <x-brand-mark />
            <span class="brand-text">Rice Samples<small>Tracking &amp; Dispatch</small></span>
        </a>
        <nav class="sidebar-nav">@include('partials.nav-links')</nav>
        <div class="sidebar-footer d-flex align-items-center gap-2">
            <span class="avatar">{{ auth()->user()->initials() }}</span>
            <div class="flex-grow-1 min-w-0">
                <div class="text-white fw-semibold small text-truncate">{{ auth()->user()->name }}</div>
                <div class="small text-truncate" style="color:#8fb19f">{{ auth()->user()->role->label() }}</div>
            </div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="btn btn-sm btn-icon text-white-50" title="Sign out" aria-label="Sign out"><i class="bi bi-box-arrow-right"></i></button>
            </form>
        </div>
    </aside>

    <div class="app-main">
        <header class="app-topbar">
            @if ($back)
                <a href="{{ $back }}" class="btn btn-light btn-icon d-lg-none" aria-label="Back"><i class="bi bi-chevron-left"></i></a>
            @else
                <button class="btn btn-light btn-icon d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileNav" aria-label="Open menu"><i class="bi bi-list fs-5"></i></button>
            @endif
            <h1 class="topbar-title flex-grow-1"><span class="d-lg-none">{{ $title ?? config('app.name') }}</span></h1>
            <form method="GET" action="{{ route('search') }}" class="d-none d-lg-block search-input" role="search" style="width:340px">
                <i class="bi bi-search"></i>
                <input type="search" name="q" value="{{ request()->routeIs('search') ? request('q') : '' }}" class="form-control" placeholder="Search samples, buyers, mills, trucks…" aria-label="Search all records">
            </form>
            <a href="{{ route('search') }}" class="btn btn-light btn-icon d-lg-none" aria-label="Search"><i class="bi bi-search"></i></a>
            @if (Route::has('samples.create'))
                <a href="{{ route('samples.create') }}" class="btn btn-primary d-none d-lg-inline-flex"><i class="bi bi-plus-lg me-1"></i> Add Sample</a>
            @endif
            <div class="dropdown">
                <button class="btn p-0 border-0" data-bs-toggle="dropdown" aria-label="Account menu"><span class="avatar">{{ auth()->user()->initials() }}</span></button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                    <li class="px-3 py-2"><div class="fw-semibold">{{ auth()->user()->name }}</div><div class="small text-muted">{{ auth()->user()->email }}</div></li>
                    <li><hr class="dropdown-divider"></li>
                    @if (Route::has('profile.edit'))
                        <li><a class="dropdown-item" href="{{ route('profile.edit') }}"><i class="bi bi-person-circle me-2"></i>My Account</a></li>
                    @endif
                    <li>
                        <form method="POST" action="{{ route('logout') }}">@csrf<button class="dropdown-item text-danger"><i class="bi bi-box-arrow-right me-2"></i>Sign out</button></form>
                    </li>
                </ul>
            </div>
        </header>

        <main class="app-content mx-auto">
            {{ $slot }}
        </main>
    </div>

    {{-- Mobile: bottom navigation --}}
    <nav class="bottom-nav" aria-label="Primary">
        <a href="{{ route('dashboard') }}" @class(['active' => request()->routeIs('dashboard')])><i class="bi bi-grid-1x2{{ request()->routeIs('dashboard') ? '-fill' : '' }}"></i>Home</a>
        @if (Route::has('samples.index'))
            <a href="{{ route('samples.index') }}" @class(['active' => request()->routeIs('samples.*', 'bundles.*')])><i class="bi bi-basket{{ request()->routeIs('samples.*', 'bundles.*') ? '-fill' : '' }}"></i>Samples</a>
        @endif
        <div class="fab-wrap flex-fill d-flex justify-content-center">
            <button type="button" class="fab" data-bs-toggle="offcanvas" data-bs-target="#quickActions" aria-label="Quick actions"><i class="bi bi-plus-lg"></i></button>
        </div>
        @if (Route::has('dispatches.index'))
            <a href="{{ route('dispatches.index') }}" @class(['active' => request()->routeIs('dispatches.*')])><i class="bi bi-send{{ request()->routeIs('dispatches.*') ? '-fill' : '' }}"></i>Dispatch</a>
        @endif
        @if (Route::has('loading.index'))
            <a href="{{ route('loading.index') }}" @class(['active' => request()->routeIs('loading.*', 'loadings.*')])><i class="bi bi-truck"></i>Loading</a>
        @else
            <a href="#" data-bs-toggle="offcanvas" data-bs-target="#mobileNav"><i class="bi bi-three-dots"></i>More</a>
        @endif
    </nav>

    {{-- Mobile: full navigation drawer --}}
    <div class="offcanvas offcanvas-start offcanvas-nav" tabindex="-1" id="mobileNav" style="width: 290px">
        <div class="offcanvas-header border-bottom">
            <div class="d-flex align-items-center gap-2">
                <x-brand-mark />
                <div><div class="fw-bold">Rice Samples</div><div class="small text-muted">{{ auth()->user()->name }} · {{ auth()->user()->role->label() }}</div></div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body p-2">
            @include('partials.nav-links')
            <form method="POST" action="{{ route('logout') }}" class="mt-3 px-2">
                @csrf
                <button class="btn btn-light w-100 text-danger"><i class="bi bi-box-arrow-right me-1"></i> Sign out</button>
            </form>
        </div>
    </div>

    {{-- Mobile: quick action sheet --}}
    <div class="offcanvas offcanvas-bottom action-sheet" tabindex="-1" id="quickActions">
        <div class="offcanvas-header pb-2">
            <h5 class="offcanvas-title fw-semibold">Quick actions</h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="list-group list-group-flush pb-2">
            @foreach ([
                ['samples.create', 'Add sample', 'Record a sample collected at a mill', 'basket', 'green'],
                ['bundles.create', 'New bundle', 'Add several samples together', 'boxes', 'gold'],
                ['dispatches.create', 'Dispatch samples', 'Send samples to buyers', 'send', 'blue'],
                ['loading.index', 'Record loading', 'Trucks, quantities and photos', 'truck', 'teal'],
                ['mills.create', 'Add rice mill', 'New mill profile', 'buildings', 'slate'],
                ['buyers.create', 'Add buyer', 'New buyer profile', 'people', 'purple'],
            ] as [$route, $label, $hint, $icon, $tone])
                @if (Route::has($route))
                    <a href="{{ route($route) }}" class="list-group-item list-group-item-action">
                        <span class="stat-icon tone-{{ $tone }}"><i class="bi bi-{{ $icon }}"></i></span>
                        <span><span class="d-block">{{ $label }}</span><span class="small text-muted fw-normal">{{ $hint }}</span></span>
                    </a>
                @endif
            @endforeach
        </div>
    </div>

    {{-- Shared confirmation dialog for forms with data-confirm --}}
    <div class="modal fade" id="confirmModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 rounded-4">
                <div class="modal-body p-4">
                    <h5 class="fw-semibold mb-2" data-confirm-title>Are you sure?</h5>
                    <p class="text-muted mb-0" data-confirm-message></p>
                </div>
                <div class="modal-footer border-0 pt-0 px-4 pb-4">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" data-confirm-ok>Confirm</button>
                </div>
            </div>
        </div>
    </div>

    @include('partials.flash')
    @stack('modals')
</body>
</html>
