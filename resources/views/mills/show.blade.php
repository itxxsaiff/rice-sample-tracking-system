<x-layouts.app :title="$mill->name" :back="route('mills.index')">
    <div class="card mb-4">
        <div class="card-body">
            <div class="d-flex flex-wrap gap-3 align-items-start justify-content-between">
                <div class="d-flex gap-3 align-items-center min-w-0">
                    <span class="stat-icon tone-green"><i class="bi bi-buildings"></i></span>
                    <div class="min-w-0">
                        <h1 class="h4 fw-bold mb-0">{{ $mill->name }}</h1>
                        <div class="text-muted"><i class="bi bi-geo-alt"></i> {{ $mill->city }}</div>
                    </div>
                </div>
                <div class="page-actions">
                    <a href="{{ route('mills.edit', $mill) }}" class="btn btn-light"><i class="bi bi-pencil"></i> Edit</a>
                    @can('delete', $mill)
                        <form method="POST" action="{{ route('mills.destroy', $mill) }}" data-confirm="This mill has no samples yet, so it can be removed permanently." data-confirm-title="Delete {{ $mill->name }}?" data-confirm-button="Delete">
                            @csrf @method('DELETE')
                            <button class="btn btn-outline-danger w-100"><i class="bi bi-trash"></i> Delete</button>
                        </form>
                    @endcan
                </div>
            </div>
            <hr>
            <dl class="detail-list">
                <div><dt>Contact person</dt><dd>{{ $mill->contact_person ?: '—' }}</dd></div>
                <div><dt>Phone</dt><dd>@if ($mill->phone)<a href="tel:{{ $mill->phone }}"><i class="bi bi-telephone"></i> {{ $mill->phone }}</a>@else — @endif</dd></div>
                <div><dt>Address</dt><dd>{{ $mill->address ?: '—' }}</dd></div>
                <div><dt>Added</dt><dd>{{ $mill->created_at->format('d M Y') }}</dd></div>
                @if ($mill->notes)<div style="grid-column: 1 / -1"><dt>Notes</dt><dd class="fw-normal">{!! nl2br(e($mill->notes)) !!}</dd></div>@endif
            </dl>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-4"><x-stat-card label="Samples" :value="$stats['samples']" icon="basket" /></div>
        <div class="col-4"><x-stat-card label="Varieties" :value="$stats['varieties']" icon="tags" tone="gold" /></div>
        <div class="col-4"><x-stat-card label="Last collected" :value="$stats['last_collected'] ? \Illuminate\Support\Carbon::parse($stats['last_collected'])->format('d M') : '—'" icon="calendar-event" tone="blue" /></div>
    </div>

    @stack('mill-sections')

    <div class="card">
        <div class="card-header flex-wrap">
            <h2 class="flex-grow-1">Sample history</h2>
            <div class="d-flex gap-2">
                @if ($varieties->count() > 1)
                    <form method="GET" data-auto-submit>
                        <select name="variety_id" class="form-select form-select-sm" aria-label="Filter by variety">
                            <option value="">All varieties</option>
                            @foreach ($varieties as $id => $name)<option value="{{ $id }}" @selected(request('variety_id') == $id)>{{ $name }}</option>@endforeach
                        </select>
                    </form>
                @endif
                <a href="{{ route('samples.create', ['mill_id' => $mill->id]) }}" class="btn btn-sm btn-primary text-nowrap"><i class="bi bi-plus-lg"></i> Add sample</a>
            </div>
        </div>
        @if ($samples->isEmpty())
            <x-empty-state icon="basket" title="No samples from this mill yet" text="Samples you record for {{ $mill->name }} will appear here automatically." />
        @else
            @include('samples._table', ['samples' => $samples, 'hideMill' => true])
            @if ($samples->hasPages())<div class="card-footer">{{ $samples->links() }}</div>@endif
        @endif
    </div>
</x-layouts.app>
