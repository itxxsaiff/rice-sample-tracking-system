@php
    $user = auth()->user();
    $sections = [
        '' => [
            ['dashboard', 'Dashboard', 'grid-1x2', 'dashboard'],
        ],
        'Samples' => [
            ['samples.index', 'Samples', 'basket', 'samples.*'],
            ['bundles.index', 'Bundles', 'boxes', 'bundles.*'],
        ],
        'Orders' => [
            ['dispatches.index', 'Dispatches', 'send', 'dispatches.*'],
            ['loading.index', 'Loading', 'truck', 'loading.*|loadings.*'],
            ['records.index', 'Records', 'journal-text', 'records.*'],
        ],
        'Directory' => [
            ['mills.index', 'Rice Mills', 'buildings', 'mills.*'],
            ['buyers.index', 'Buyers', 'people', 'buyers.*'],
            ['varieties.index', 'Rice Varieties', 'tags', 'varieties.*'],
        ],
        'Settings' => [
            ['users.index', 'Manage Users', 'person-gear', 'users.*', 'admin'],
            ['profile.edit', 'My Account', 'person-circle', 'profile.*'],
        ],
    ];
@endphp
@foreach ($sections as $heading => $links)
    @php
        $links = array_filter($links, fn ($l) => Route::has($l[0]) && (($l[4] ?? null) !== 'admin' || $user?->isAdmin()));
    @endphp
    @if ($links)
        @if ($heading)<div class="sidebar-heading">{{ $heading }}</div>@endif
        @foreach ($links as [$route, $label, $icon, $pattern])
            <a href="{{ route($route) }}" @class(['sidebar-link', 'active' => request()->routeIs(...explode('|', $pattern))])>
                <i class="bi bi-{{ $icon }}"></i><span>{{ $label }}</span>
            </a>
        @endforeach
    @endif
@endforeach
