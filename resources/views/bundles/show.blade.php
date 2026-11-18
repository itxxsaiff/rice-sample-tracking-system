<x-layouts.app :title="$bundle->bundle_number" :back="route('bundles.index')">
    @php $byMill = $bundle->samples->groupBy('mill_id'); @endphp
    <div class="card mb-4">
        <div class="card-body">
            <div class="d-flex flex-wrap gap-3 justify-content-between align-items-start">
                <div class="d-flex gap-3 align-items-center">
                    <span class="stat-icon tone-gold"><i class="bi bi-boxes"></i></span>
                    <div>
                        <div class="small-caps">Bundle</div>
                        <h1 class="h4 fw-bold mb-0 mono">{{ $bundle->bundle_number }}</h1>
                    </div>
                </div>
                <div class="page-actions">
                    @if (Route::has('dispatches.create') && $bundle->samples->isNotEmpty())
                        <a href="{{ route('dispatches.create', ['sample_ids' => $bundle->samples->pluck('id')->all()]) }}" class="btn btn-primary"><i class="bi bi-send"></i> Dispatch samples</a>
                    @endif
                    <a href="{{ route('bundles.samples.create', $bundle) }}" class="btn btn-soft"><i class="bi bi-plus-lg"></i> Add samples</a>
                    <a href="{{ route('bundles.edit', $bundle) }}" class="btn btn-light"><i class="bi bi-pencil"></i> Edit</a>
                    @can('delete', $bundle)
                        <form method="POST" action="{{ route('bundles.destroy', $bundle) }}" data-confirm="This bundle is empty and will be deleted." data-confirm-title="Delete bundle?" data-confirm-button="Delete">
                            @csrf @method('DELETE')<button class="btn btn-outline-danger w-100"><i class="bi bi-trash"></i></button>
                        </form>
                    @endcan
                </div>
            </div>
            <hr>
            <dl class="detail-list">
                <div><dt>Date</dt><dd>{{ $bundle->bundle_date->format('d M Y') }}</dd></div>
                <div><dt>Samples</dt><dd>{{ $bundle->samples->count() }}</dd></div>
                <div><dt>Mills</dt><dd>{{ $byMill->count() }}</dd></div>
                <div><dt>Created by</dt><dd>{{ $bundle->creator?->name ?? '—' }}</dd></div>
                @if ($bundle->notes)<div style="grid-column: 1 / -1"><dt>Notes</dt><dd class="fw-normal">{!! nl2br(e($bundle->notes)) !!}</dd></div>@endif
            </dl>
            @if ($byMill->count() > 1)
                <div class="d-flex flex-wrap gap-2 mt-3">
                    @foreach ($byMill as $samples)
                        <a href="{{ route('mills.show', $samples->first()->mill) }}" class="badge text-bg-light border p-2 fw-medium">{{ $samples->first()->mill->name }} · {{ $samples->count() }}</a>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
    <div class="card">
        <div class="card-header"><h2>Samples</h2></div>
        @if ($bundle->samples->isEmpty())
            <x-empty-state icon="basket" title="This bundle is empty">
                <a href="{{ route('bundles.samples.create', $bundle) }}" class="btn btn-primary">Add samples</a>
            </x-empty-state>
        @else
            @include('samples._table', ['samples' => $bundle->samples->each->setRelation('bundle', $bundle)])
        @endif
    </div>
</x-layouts.app>
