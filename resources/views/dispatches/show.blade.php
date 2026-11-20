<x-layouts.app :title="$dispatch->dispatch_number" :back="route('dispatches.index')">
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-body">
                    <div class="d-flex flex-wrap gap-3 justify-content-between align-items-start">
                        <div class="d-flex gap-3 align-items-center">
                            <span class="stat-icon tone-blue"><i class="bi bi-send"></i></span>
                            <div>
                                <div class="small-caps">Dispatch</div>
                                <h1 class="h4 fw-bold mb-1 mono">{{ $dispatch->dispatch_number }}</h1>
                                <x-status-badge :status="$dispatch->stageKey()">{{ $dispatch->stageLabel() }}</x-status-badge>
                            </div>
                        </div>
                        <div class="page-actions">
                            @if ($dispatch->isPending())
                                <button type="button" class="btn btn-primary" data-respond='@json($dispatch->respondData())' data-respond-auto><i class="bi bi-reply"></i> Record response</button>
                            @else
                                <button type="button" class="btn btn-light" data-respond='@json($dispatch->respondData())' data-respond-auto><i class="bi bi-pencil"></i> Edit response</button>
                            @endif
                            @can('reopen', $dispatch)
                                <form method="POST" action="{{ route('dispatches.reopen', $dispatch) }}" data-confirm="The current response will be cleared (it stays in the history log) and the dispatch will await the buyer again." data-confirm-title="Reopen response?" data-confirm-button="Reopen" data-confirm-variant="primary">
                                    @csrf @method('PATCH')
                                    <button class="btn btn-light w-100"><i class="bi bi-arrow-counterclockwise"></i> Reopen</button>
                                </form>
                            @endcan
                            @can('delete', $dispatch)
                                <form method="POST" action="{{ route('dispatches.destroy', $dispatch) }}" data-confirm="This pending dispatch will be permanently deleted." data-confirm-title="Delete dispatch?" data-confirm-button="Delete">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-outline-danger w-100" aria-label="Delete"><i class="bi bi-trash"></i></button>
                                </form>
                            @endcan
                        </div>
                    </div>
                    <hr>
                    @include('dispatches._sample-summary')
                    @if ($dispatch->notes)<div class="small mt-3"><span class="text-muted">Notes:</span> {{ $dispatch->notes }}</div>@endif
                </div>
            </div>

            @if ($dispatch->isConfirmed())
                <div class="card mb-4">
                    <div class="card-header"><h2><i class="bi bi-clipboard-check text-success me-2"></i>Confirmed order</h2></div>
                    <div class="card-body">
                        <dl class="detail-list">
                            <div><dt>Quantity</dt><dd>{{ $dispatch->confirmedQuantityLabel() }}</dd></div>
                            <div><dt>Agreed rate</dt><dd>{{ $dispatch->rateLabel() }}</dd></div>
                            <div><dt>Packing</dt><dd>{{ $dispatch->packing_type }}</dd></div>
                            <div><dt>Confirmed on</dt><dd>{{ $dispatch->confirmation_date->format('d M Y') }}</dd></div>
                            @if ($dispatch->packing_details)<div style="grid-column: 1 / -1"><dt>Packing details</dt><dd class="fw-normal">{!! nl2br(e($dispatch->packing_details)) !!}</dd></div>@endif
                            <div><dt>Recorded by</dt><dd>{{ $dispatch->responder?->name ?? '—' }}</dd></div>
                        </dl>
                    </div>
                </div>
            @elseif ($dispatch->isRejected())
                <div class="card mb-4">
                    <div class="card-header"><h2><i class="bi bi-x-octagon text-danger me-2"></i>Rejected</h2></div>
                    <div class="card-body">
                        <dl class="detail-list">
                            <div><dt>Rejected on</dt><dd>{{ $dispatch->rejection_date->format('d M Y') }}</dd></div>
                            <div><dt>Recorded by</dt><dd>{{ $dispatch->responder?->name ?? '—' }}</dd></div>
                        </dl>
                        <p class="small text-muted mb-0 mt-3"><i class="bi bi-info-circle"></i> Rejected dispatches stay in the buyer's history and do not enter loading.</p>
                    </div>
                </div>
            @else
                <div class="card mb-4">
                    <x-empty-state icon="hourglass-split" title="Awaiting {{ $dispatch->buyer->name }}'s response" text="Once the buyer inspects the sample, record whether they confirmed or rejected it.">
                        <button type="button" class="btn btn-primary" data-respond='@json($dispatch->respondData())'><i class="bi bi-reply"></i> Record response</button>
                    </x-empty-state>
                </div>
            @endif

            @if ($dispatch->isConfirmed())
                <div class="card mb-4">
                    <div class="card-header justify-content-between flex-wrap">
                        <h2><i class="bi bi-truck text-primary me-2"></i>Loading <x-status-badge :status="$dispatch->stageKey() === 'confirmed' ? 'loading_pending' : $dispatch->stageKey()" class="ms-1">{{ $dispatch->loading_status->label() }}</x-status-badge></h2>
                        <div class="d-flex gap-2 flex-wrap">
                            @can('recordLoading', $dispatch)
                                <a href="{{ route('loadings.create', $dispatch) }}" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i> {{ $dispatch->loadings->isEmpty() ? 'Record loading' : 'Add truck' }}</a>
                            @endcan
                            @can('completeLoading', $dispatch)
                                <form method="POST" action="{{ route('dispatches.complete-loading', $dispatch) }}" data-confirm="Mark order {{ $dispatch->dispatch_number }} as fully loaded and completed? All trucks must be marked loaded." data-confirm-title="Complete order?" data-confirm-button="Complete order" data-confirm-variant="primary">
                                    @csrf @method('PATCH')
                                    <button class="btn btn-sm btn-soft"><i class="bi bi-check2-all"></i> Complete order</button>
                                </form>
                            @endcan
                            @can('reopenLoading', $dispatch)
                                <form method="POST" action="{{ route('dispatches.reopen-loading', $dispatch) }}" data-confirm="The order will move back to 'loading in progress' so more trucks can be added." data-confirm-title="Reopen order?" data-confirm-button="Reopen" data-confirm-variant="primary">
                                    @csrf @method('PATCH')
                                    <button class="btn btn-sm btn-light"><i class="bi bi-arrow-counterclockwise"></i> Reopen order</button>
                                </form>
                            @endcan
                        </div>
                    </div>
                    <div class="card-body">
                        @include('loading._progress')
                        @if ($dispatch->loading_completed_at)<div class="small text-muted mt-2"><i class="bi bi-check2-all"></i> Completed {{ $dispatch->loading_completed_at->format('d M Y, H:i') }}</div>@endif
                    </div>
                    @if ($dispatch->loadings->isEmpty())
                        <div class="card-body pt-0"><p class="text-muted small mb-0">No trucks recorded yet. Tap <strong>Record loading</strong> when the rice is loaded.</p></div>
                    @else
                        <div class="list-group list-group-flush">
                            @foreach ($dispatch->loadings as $loading)
                                <a href="{{ route('loadings.show', $loading) }}" class="list-group-item list-group-item-action py-3 d-flex justify-content-between align-items-center gap-3">
                                    <span class="d-flex align-items-center gap-3 min-w-0">
                                        <span class="stat-icon tone-teal" style="width:40px;height:40px;font-size:1.1rem"><i class="bi bi-truck"></i></span>
                                        <span class="min-w-0">
                                            <span class="d-block fw-semibold mono">{{ $loading->truck_number }}</span>
                                            <span class="small text-muted">{{ $loading->loading_date->format('d M Y') }} · {{ $loading->quantityLabel() }}{{ $loading->bag_count ? ' · '.number_format($loading->bag_count).' bags' : '' }} · <i class="bi bi-camera"></i> {{ $loading->photos_count }}</span>
                                        </span>
                                    </span>
                                    <x-status-badge :status="$loading->isCompleted() ? 'completed' : 'in_progress'">{{ $loading->status->label() }}</x-status-badge>
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endif
        </div>
        <div class="col-lg-4">
            @if ($siblings->isNotEmpty())
                <div class="card mb-4">
                    <div class="card-header"><h2>Same sample, other buyers</h2></div>
                    <div class="list-group list-group-flush">
                        @foreach ($siblings as $sibling)
                            <a href="{{ route('dispatches.show', $sibling) }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center gap-2 py-3">
                                <span class="min-w-0"><span class="d-block fw-medium text-truncate">{{ $sibling->buyer->name }}</span><span class="small text-muted mono">{{ $sibling->dispatch_number }}</span></span>
                                <x-status-badge :status="$sibling->stageKey()" />
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif
            <div class="card">
                <div class="card-header"><h2>History</h2></div>
                <div class="card-body">@include('partials.activity-timeline', ['activities' => $dispatch->activities])</div>
            </div>
        </div>
    </div>
</x-layouts.app>
