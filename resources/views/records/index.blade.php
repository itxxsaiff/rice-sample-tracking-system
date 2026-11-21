<x-layouts.app title="Records">
    <x-page-header title="Records & History" subtitle="Permanent history of every dispatch, buyer response and loading. Nothing is removed when an order is completed.">
        <x-slot:actions>
            <a href="{{ route('records.pdf', ['type' => $type] + array_filter($filters)) }}" class="btn btn-primary" data-pdf-export><i class="bi bi-file-earmark-pdf"></i> Export PDF</a>
        </x-slot:actions>
    </x-page-header>

    <div class="card">
        <nav class="nav-tabs-pills" aria-label="Record type">
            @foreach (\App\Http\Controllers\RecordsController::TYPES as $key => $label)
                <a href="{{ route('records.index', ['type' => $key] + array_filter($filters)) }}" @class(['active' => $type === $key])>{{ $label }}</a>
            @endforeach
        </nav>
        <form method="GET" class="filter-bar" data-auto-submit>
            <input type="hidden" name="type" value="{{ $type }}">
            <div class="row g-2">
                <div class="col-12 col-xl-3">
                    <div class="search-input"><i class="bi bi-search"></i>
                        <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control" placeholder="{{ $type === 'loadings' ? 'Truck no., driver, dispatch, buyer' : 'Dispatch no., sample no., buyer, mill' }}">
                    </div>
                </div>
                <div class="col-6 col-md-4 col-xl-2">
                    <select name="mill_id" class="form-select" aria-label="Mill"><option value="">All mills</option>
                        @foreach ($mills as $id => $name)<option value="{{ $id }}" @selected(($filters['mill_id'] ?? '') == $id)>{{ $name }}</option>@endforeach
                    </select>
                </div>
                <div class="col-6 col-md-4 col-xl-2">
                    <select name="buyer_id" class="form-select" aria-label="Buyer"><option value="">All buyers</option>
                        @foreach ($buyers as $id => $name)<option value="{{ $id }}" @selected(($filters['buyer_id'] ?? '') == $id)>{{ $name }}</option>@endforeach
                    </select>
                </div>
                <div class="col-12 col-md-4 col-xl-1">
                    <select name="variety_id" class="form-select" aria-label="Variety"><option value="">Variety</option>
                        @foreach ($varieties as $id => $name)<option value="{{ $id }}" @selected(($filters['variety_id'] ?? '') == $id)>{{ $name }}</option>@endforeach
                    </select>
                </div>
                <div class="col-6 col-xl-2"><input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="form-control" aria-label="{{ $dateLabel }} from" title="{{ $dateLabel }} from"></div>
                <div class="col-6 col-xl-2"><input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="form-control" aria-label="{{ $dateLabel }} to" title="{{ $dateLabel }} to"></div>
            </div>
            <div class="mt-2 small text-muted">
                {{ number_format($records->total()) }} record(s) · dates filter by <strong>{{ strtolower($dateLabel) }}</strong>
                @if (array_filter($filters)) · <a href="{{ route('records.index', ['type' => $type]) }}">Clear filters</a>@endif
            </div>
        </form>

        @if ($records->isEmpty())
            <x-empty-state icon="journal-text" title="No records found" text="Adjust the filters or date range." />
        @elseif ($type === 'loadings')
            <div class="table-responsive">
                <table class="table table-hover table-stack align-middle">
                    <thead><tr><th>Truck</th><th>Buyer</th><th>Sample</th><th>Date</th><th>Quantity</th><th>Photos</th><th>Status</th></tr></thead>
                    <tbody>
                    @foreach ($records as $loading)
                        <tr class="row-link" data-href="{{ route('loadings.show', $loading) }}">
                            <td class="cell-primary"><a href="{{ route('loadings.show', $loading) }}" class="cell-title mono">{{ $loading->truck_number }}</a><div class="cell-sub mono">{{ $loading->loading_number }} · {{ $loading->dispatch->dispatch_number }}</div></td>
                            <td data-label="Buyer"><span>{{ $loading->dispatch->buyer->name }}</span></td>
                            <td data-label="Sample"><span>{{ $loading->dispatch->sample->sample_number }} · {{ $loading->dispatch->sample->variety->name }}<span class="cell-sub d-block">{{ $loading->dispatch->sample->mill->name }}</span></span></td>
                            <td data-label="Date"><span>{{ $loading->loading_date->format('d M Y') }}</span></td>
                            <td data-label="Quantity"><span class="fw-semibold">{{ $loading->quantityLabel() }}@if ($loading->bag_count)<span class="cell-sub d-block fw-normal">{{ number_format($loading->bag_count) }} bags</span>@endif</span></td>
                            <td data-label="Photos"><span><i class="bi bi-camera"></i> {{ $loading->photos_count }}</span></td>
                            <td data-label="Status"><span><x-status-badge :status="$loading->isCompleted() ? 'completed' : 'in_progress'">{{ $loading->status->label() }}</x-status-badge></span></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @else
            @include('dispatches._table', ['dispatches' => $records])
        @endif
        @if ($records->hasPages())<div class="card-footer">{{ $records->links() }}</div>@endif
    </div>
</x-layouts.app>
