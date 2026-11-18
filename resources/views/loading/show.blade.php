@php $dispatch = $loading->dispatch; @endphp
<x-layouts.app :title="$loading->loading_number.' · '.$loading->truck_number" :back="route('dispatches.show', $dispatch)">
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-body">
                    <div class="d-flex flex-wrap gap-3 justify-content-between align-items-start">
                        <div class="d-flex gap-3 align-items-center">
                            <span class="stat-icon tone-teal"><i class="bi bi-truck"></i></span>
                            <div>
                                <div class="small-caps">Truck loading · <span class="mono">{{ $loading->loading_number }}</span></div>
                                <h1 class="h4 fw-bold mb-1 mono">{{ $loading->truck_number }}</h1>
                                <x-status-badge :status="$loading->isCompleted() ? 'completed' : 'in_progress'">{{ $loading->status->label() }}</x-status-badge>
                            </div>
                        </div>
                        <div class="page-actions">
                            @can('markLoaded', $loading)
                                <form method="POST" action="{{ route('loadings.loaded', $loading) }}" data-confirm="Truck {{ $loading->truck_number }} will be marked as fully loaded." data-confirm-title="Mark as loaded?" data-confirm-button="Mark loaded" data-confirm-variant="primary">
                                    @csrf @method('PATCH')
                                    <button class="btn btn-primary w-100"><i class="bi bi-check2-all"></i> Mark loaded</button>
                                </form>
                            @endcan
                            @can('update', $loading)
                                <a href="{{ route('loadings.edit', $loading) }}" class="btn btn-light"><i class="bi bi-pencil"></i> Edit</a>
                            @endcan
                            @can('delete', $loading)
                                <form method="POST" action="{{ route('loadings.destroy', $loading) }}" data-confirm="This truck record and its photos will be permanently removed." data-confirm-title="Delete truck loading?" data-confirm-button="Delete">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-outline-danger w-100" aria-label="Delete"><i class="bi bi-trash"></i></button>
                                </form>
                            @endcan
                        </div>
                    </div>
                    <hr>
                    @include('dispatches._sample-summary')
                    <hr>
                    <dl class="detail-list">
                        <div><dt>Loading date</dt><dd>{{ $loading->loading_date->format('d M Y') }}</dd></div>
                        <div><dt>Quantity</dt><dd>{{ $loading->quantityLabel() }}</dd></div>
                        <div><dt>Bags</dt><dd>{{ $loading->bag_count !== null ? number_format($loading->bag_count) : '—' }}</dd></div>
                        <div><dt>Packing</dt><dd>{{ $loading->packing_type ?: '—' }}</dd></div>
                        <div><dt>Responsible person</dt><dd>{{ $loading->receiver_name ?: '—' }}</dd></div>
                        <div><dt>Driver</dt><dd>{{ $loading->driver_name ?: '—' }} @if ($loading->driver_phone)<a href="tel:{{ $loading->driver_phone }}" class="d-block small"><i class="bi bi-telephone"></i> {{ $loading->driver_phone }}</a>@endif</dd></div>
                        <div><dt>Recorded by</dt><dd>{{ $loading->creator?->name ?? '—' }}</dd></div>
                        @if ($loading->completed_at)<div><dt>Loaded</dt><dd>{{ $loading->completed_at->format('d M Y, H:i') }}</dd></div>@endif
                        @if ($loading->packing_details)<div style="grid-column: 1 / -1"><dt>Packing details</dt><dd class="fw-normal">{!! nl2br(e($loading->packing_details)) !!}</dd></div>@endif
                        @if ($loading->notes)<div style="grid-column: 1 / -1"><dt>Notes</dt><dd class="fw-normal">{!! nl2br(e($loading->notes)) !!}</dd></div>@endif
                    </dl>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header justify-content-between">
                    <h2><i class="bi bi-images text-primary me-2"></i>Photos <span class="badge rounded-pill text-bg-light border ms-1">{{ $loading->photos->count() }}</span></h2>
                </div>
                <div class="card-body">
                    @if ($loading->photos->isNotEmpty())
                        <div class="photo-grid mb-3" data-gallery>
                            @foreach ($loading->photos as $photo)
                                <div class="photo-tile">
                                    <a href="{{ route('loading-photos.show', $photo) }}" data-viewer-item data-caption="{{ $loading->truck_number }} · {{ $photo->created_at->format('d M Y H:i') }}">
                                        <img src="{{ route('loading-photos.show', $photo) }}" alt="Loading photo {{ $loop->iteration }}" loading="lazy">
                                    </a>
                                    @can('deletePhoto', $loading)
                                        <form method="POST" action="{{ route('loading-photos.destroy', $photo) }}" data-confirm="This photo will be permanently deleted." data-confirm-title="Delete photo?" data-confirm-button="Delete">
                                            @csrf @method('DELETE')
                                            <button class="photo-remove" aria-label="Delete photo"><i class="bi bi-trash"></i></button>
                                        </form>
                                    @endcan
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-muted small">No photos yet.</p>
                    @endif

                    @can('addPhotos', $loading)
                        <form method="POST" action="{{ route('loadings.photos.store', $loading) }}" enctype="multipart/form-data" novalidate>
                            @csrf
                            <x-form.photo-input name="photos" :multiple="true" label="Add photos" class="mb-2" />
                            <button class="btn btn-primary w-100" data-busy-text="Uploading…"><i class="bi bi-cloud-arrow-up"></i> Upload photos</button>
                        </form>
                    @endcan
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-header"><h2>Order progress</h2></div>
                <div class="card-body">
                    @include('loading._progress')
                    <a href="{{ route('dispatches.show', $dispatch) }}" class="btn btn-light w-100 mt-3">View order {{ $dispatch->dispatch_number }}</a>
                </div>
            </div>
            <div class="card">
                <div class="card-header"><h2>History</h2></div>
                <div class="card-body">@include('partials.activity-timeline', ['activities' => $loading->activities])</div>
            </div>
        </div>
    </div>
    @include('partials.photo-viewer')
</x-layouts.app>
