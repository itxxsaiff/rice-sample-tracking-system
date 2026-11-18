<x-layouts.app title="Dashboard">
    <div class="page-header keep-mobile">
        <div>
            <h1>Dashboard</h1>
            <p>{{ now()->format('l, j F Y') }}</p>
        </div>
        <div class="page-actions d-none d-md-flex">
            <a href="{{ route('dispatches.create') }}" class="btn btn-light"><i class="bi bi-send me-1"></i> Dispatch</a>
            <a href="{{ route('samples.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> Add sample</a>
        </div>
    </div>

    {{-- Quick actions (big touch targets, mostly for phones) --}}
    <div class="row g-2 g-md-3 mb-4 d-md-none">
        @foreach ([
            [route('samples.create'), 'Add sample', 'basket', 'navy'],
            [route('bundles.create'), 'New bundle', 'boxes', 'gold'],
            [route('dispatches.create'), 'Dispatch', 'send', 'blue'],
            [route('loading.index'), 'Loading', 'truck', 'teal'],
        ] as [$href, $label, $icon, $tone])
            <div class="col-3">
                <a href="{{ $href }}" class="card quick-action p-2"><span class="stat-icon tone-{{ $tone }}"><i class="bi bi-{{ $icon }}"></i></span><span class="small">{{ $label }}</span></a>
            </div>
        @endforeach
    </div>

    @if ($stats['samples'] === 0)
        <div class="empty-panel mb-4">
            <x-empty-state icon="box-seam" title="No samples registered yet" text="All counters on this dashboard come from your live records. Register your first rice sample from a mill to begin tracking.">
                <a href="{{ route('samples.create') }}" class="btn btn-primary"><i class="bi bi-plus-circle me-1"></i> Register first sample</a>
            </x-empty-state>
        </div>
    @endif

    <div class="small-caps mb-2">Needs attention</div>
    <div class="row g-3 mb-4">
        <div class="col-6 col-xl-3"><x-stat-card label="Awaiting buyer response" :value="$stats['pending']" icon="hourglass-split" tone="gold" :href="route('dispatches.index', ['status' => 'pending'])" /></div>
        <div class="col-6 col-xl-3"><x-stat-card label="Orders to load" :value="$stats['loading_pending']" icon="truck" tone="blue" :href="route('loading.index')" /></div>
        <div class="col-6 col-xl-3"><x-stat-card label="Confirmed dispatches" :value="$stats['confirmed']" icon="check-circle" tone="green" :href="route('dispatches.index', ['status' => 'confirmed'])" /></div>
        <div class="col-6 col-xl-3"><x-stat-card label="Completed loadings" :value="$stats['loading_completed']" icon="check2-all" tone="teal" :href="route('loading.index', ['tab' => 'completed'])" /></div>
    </div>

    <div class="small-caps mb-2">Records</div>
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-4 col-xl"><x-stat-card label="Samples" :value="$stats['samples']" icon="basket" tone="navy" :href="route('samples.index')" /></div>
        <div class="col-6 col-md-4 col-xl"><x-stat-card label="Dispatches" :value="$stats['dispatches']" icon="send" tone="blue" :href="route('dispatches.index')" /></div>
        <div class="col-6 col-md-4 col-xl"><x-stat-card label="Rejected" :value="$stats['rejected']" icon="x-circle" tone="red" :href="route('dispatches.index', ['status' => 'rejected'])" /></div>
        <div class="col-6 col-md-6 col-xl"><x-stat-card label="Rice mills" :value="$stats['mills']" icon="buildings" tone="slate" :href="route('mills.index')" /></div>
        <div class="col-12 col-md-6 col-xl"><x-stat-card label="Buyers" :value="$stats['buyers']" icon="people" tone="purple" :href="route('buyers.index')" /></div>
    </div>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card mb-4">
                <div class="card-header justify-content-between">
                    <h2><i class="bi bi-hourglass-split text-warning me-2"></i>Waiting longest for a response</h2>
                    <a href="{{ route('dispatches.index', ['status' => 'pending']) }}" class="small">View all</a>
                </div>
                @if ($awaitingResponse->isEmpty())
                    <div class="card-body text-muted small"><i class="bi bi-check-circle text-success"></i> No responses pending.</div>
                @else
                    <div class="list-group list-group-flush">
                        @foreach ($awaitingResponse as $d)
                            <div class="list-group-item d-flex justify-content-between align-items-center gap-2 py-3">
                                <a href="{{ route('dispatches.show', $d) }}" class="min-w-0 text-body">
                                    <span class="d-block fw-medium text-truncate">{{ $d->buyer->name }}</span>
                                    <span class="small text-muted"><span class="mono">{{ $d->sample->sample_number }}</span> {{ $d->sample->variety->name }} · {{ $d->dispatch_date->diffForHumans(['parts' => 1]) }}</span>
                                </a>
                                <a href="{{ route('dispatches.respond', $d) }}" class="btn btn-sm btn-soft text-nowrap"><i class="bi bi-reply"></i> Respond</a>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
            <div class="card">
                <div class="card-header justify-content-between">
                    <h2><i class="bi bi-truck text-primary me-2"></i>Confirmed orders to load</h2>
                    <a href="{{ route('loading.index') }}" class="small">View all</a>
                </div>
                @if ($awaitingLoading->isEmpty())
                    <div class="card-body text-muted small"><i class="bi bi-check-circle text-success"></i> Nothing waiting to be loaded.</div>
                @else
                    <div class="list-group list-group-flush">
                        @foreach ($awaitingLoading as $d)
                            <div class="list-group-item py-3">
                                <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
                                    <a href="{{ route('dispatches.show', $d) }}" class="min-w-0 text-body">
                                        <span class="d-block fw-medium text-truncate">{{ $d->buyer->name }}</span>
                                        <span class="small text-muted">{{ $d->confirmedQuantityLabel() }} · {{ $d->sample->variety->name }} · <span class="mono">{{ $d->dispatch_number }}</span></span>
                                    </a>
                                    <a href="{{ route('loadings.create', $d) }}" class="btn btn-sm btn-primary text-nowrap"><i class="bi bi-truck"></i> Load</a>
                                </div>
                                @include('loading._progress', ['dispatch' => $d])
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header"><h2><i class="bi bi-activity text-primary me-2"></i>Recent activity</h2></div>
                @if ($activities->isEmpty())
                    <x-empty-state icon="activity" title="Nothing yet" text="Start by adding a rice mill and recording your first sample.">
                        <a href="{{ route('samples.create') }}" class="btn btn-primary">Add sample</a>
                    </x-empty-state>
                @else
                    <div class="list-group list-group-flush">
                        @foreach ($activities as $activity)
                            @php [$icon, $tone] = $activity->icon(); $url = $activity->subjectUrl(); @endphp
                            @php
                                $inner = '<span class="stat-icon tone-'.e($tone).' flex-shrink-0" style="width:34px;height:34px;font-size:.95rem;border-radius:10px"><i class="bi bi-'.e($icon).'"></i></span>'
                                    .'<span class="min-w-0"><span class="d-block small">'.e($activity->description).'</span>'
                                    .'<span class="d-block text-muted" style="font-size:.75rem">'.e($activity->user->name).' · '.e($activity->created_at->diffForHumans()).'</span></span>';
                            @endphp
                            @if ($url)
                                <a href="{{ $url }}" class="list-group-item list-group-item-action d-flex gap-3 py-3">{!! $inner !!}</a>
                            @else
                                <div class="list-group-item d-flex gap-3 py-3">{!! $inner !!}</div>
                            @endif
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-layouts.app>
