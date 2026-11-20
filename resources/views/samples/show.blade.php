<x-layouts.app :title="$sample->sample_number" :back="route('samples.index')">
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-body">
                    <div class="d-flex flex-wrap gap-3 justify-content-between align-items-start">
                        <div class="d-flex gap-3 align-items-center min-w-0">
                            <span class="stat-icon tone-navy"><i class="bi bi-basket"></i></span>
                            <div class="min-w-0">
                                <div class="small-caps">Sample</div>
                                <h1 class="h4 fw-bold mb-0 mono">{{ $sample->sample_number }}</h1>
                            </div>
                        </div>
                        <div class="page-actions">
                            <a href="{{ route('dispatches.create', ['sample_ids' => [$sample->id]]) }}" class="btn btn-primary"><i class="bi bi-send"></i> Dispatch</a>
                            <a href="{{ route('samples.edit', $sample) }}" class="btn btn-light"><i class="bi bi-pencil"></i> Edit</a>
                            @can('delete', $sample)
                                <form method="POST" action="{{ route('samples.destroy', $sample) }}" data-confirm="Sample {{ $sample->sample_number }} has not been dispatched and will be permanently deleted." data-confirm-title="Delete sample?" data-confirm-button="Delete">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-outline-danger w-100" aria-label="Delete"><i class="bi bi-trash"></i></button>
                                </form>
                            @endcan
                        </div>
                    </div>
                    <hr>
                    <dl class="detail-list">
                        <div><dt>Rice mill</dt><dd><a href="{{ route('mills.show', $sample->mill) }}">{{ $sample->mill->name }}</a></dd></div>
                        <div><dt>City</dt><dd>{{ $sample->city() }}</dd></div>
                        <div><dt>Variety</dt><dd>{{ $sample->variety->name }}</dd></div>
                        <div><dt>Collected</dt><dd>{{ $sample->collection_date->format('d M Y') }}</dd></div>
                        <div><dt>Quantity</dt><dd>{{ $sample->quantityLabel() ?? '—' }}</dd></div>
                        <div><dt>Recorded by</dt><dd>{{ $sample->creator?->name ?? '—' }}</dd></div>
                        @if ($sample->notes)<div style="grid-column: 1 / -1"><dt>Notes</dt><dd class="fw-normal">{!! nl2br(e($sample->notes)) !!}</dd></div>@endif
                    </dl>
                </div>
            </div>
            <div class="card mb-4">
                <div class="card-header justify-content-between">
                    <h2>Dispatched to buyers <span class="badge rounded-pill text-bg-light border ms-1">{{ $sample->dispatches->count() }}</span></h2>
                    <a href="{{ route('dispatches.create', ['sample_ids' => [$sample->id]]) }}" class="btn btn-sm btn-soft text-nowrap"><i class="bi bi-send"></i> Send to buyer</a>
                </div>
                @if ($sample->dispatches->isEmpty())
                    <x-empty-state icon="send" title="Not sent to any buyer yet" text="One sample can be sent to several buyers; each response is tracked separately." />
                @else
                    @include('dispatches._table', ['dispatches' => $sample->dispatches->each->setRelation('sample', $sample), 'hideSample' => true])
                @endif
            </div>
            @stack('sample-sections')
        </div>
        <div class="col-lg-4">
            @if ($sample->photo_path)
                <div class="card mb-4 overflow-hidden">
                    <a href="{{ route('samples.photo', $sample) }}" target="_blank" rel="noopener"><img src="{{ route('samples.photo', $sample) }}" alt="Sample {{ $sample->sample_number }}" class="w-100" style="max-height:360px;object-fit:cover"></a>
                </div>
            @endif
            <div class="card">
                <div class="card-header"><h2>History</h2></div>
                <div class="card-body">@include('partials.activity-timeline', ['activities' => $sample->activities])</div>
            </div>
        </div>
    </div>
</x-layouts.app>
