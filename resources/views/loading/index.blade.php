<x-layouts.app title="Loading">
    <x-page-header title="Loading" subtitle="Confirmed orders waiting to be loaded, being loaded, and completed." />

    <div class="card">
        <nav class="nav-tabs-pills" aria-label="Loading status">
            @foreach (['pending' => 'Pending', 'in_progress' => 'In progress', 'completed' => 'Completed'] as $key => $label)
                <a href="{{ route('loading.index', ['tab' => $key] + array_filter($filters)) }}" @class(['active' => $tab === $key])>{{ $label }}<span class="count">{{ $counts[$key] ?? 0 }}</span></a>
            @endforeach
        </nav>
        <form method="GET" class="filter-bar" data-auto-submit>
            <input type="hidden" name="tab" value="{{ $tab }}">
            <div class="row g-2">
                <div class="col-12 col-lg-5">
                    <div class="search-input"><i class="bi bi-search"></i>
                        <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control" placeholder="Dispatch no., sample no., buyer, mill">
                    </div>
                </div>
                <div class="col-12 col-lg-3">
                    <select name="buyer_id" class="form-select" aria-label="Buyer">
                        <option value="">All buyers</option>
                        @foreach ($buyers as $id => $name)<option value="{{ $id }}" @selected(($filters['buyer_id'] ?? '') == $id)>{{ $name }}</option>@endforeach
                    </select>
                </div>
                <div class="col-6 col-lg-2"><input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="form-control" aria-label="Confirmed from"></div>
                <div class="col-6 col-lg-2"><input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="form-control" aria-label="Confirmed to"></div>
            </div>
        </form>

        @if ($orders->isEmpty())
            <x-empty-state icon="truck" :title="match ($tab) { 'pending' => 'No orders waiting for loading', 'in_progress' => 'No orders being loaded', default => 'No completed orders yet' }"
                           text="When a buyer confirms a sample, the order appears here automatically." />
        @else
            <div class="list-group list-group-flush">
                @foreach ($orders as $order)
                    <div class="list-group-item py-3 px-3 px-md-4">
                        <div class="row g-3 align-items-center">
                            <div class="col-md-5">
                                <div class="d-flex justify-content-between align-items-start gap-2">
                                    <div class="min-w-0">
                                        <a href="{{ route('dispatches.show', $order) }}" class="fw-semibold">{{ $order->buyer->name }}</a>
                                        <div class="small text-muted text-truncate"><span class="mono">{{ $order->dispatch_number }}</span> · {{ $order->sample->sample_number }} {{ $order->sample->variety->name }} · {{ $order->sample->mill->name }}</div>
                                    </div>
                                    <span class="d-md-none"><x-status-badge :status="$order->stageKey()" /></span>
                                </div>
                                <div class="small mt-1"><span class="fw-medium">{{ $order->confirmedQuantityLabel() }}</span> · {{ $order->rateLabel() }} · {{ $order->packing_type }}</div>
                            </div>
                            <div class="col-md-4">
                                @include('loading._progress', ['dispatch' => $order])
                                <div class="small text-muted mt-1">{{ $order->loadings->count() }} truck(s) · confirmed {{ $order->confirmation_date->format('d M Y') }}</div>
                            </div>
                            <div class="col-md-3 text-md-end d-grid d-md-block">
                                @if ($order->acceptsLoadings())
                                    <a href="{{ route('loadings.create', $order) }}" class="btn btn-primary"><i class="bi bi-truck"></i> {{ $order->loadings->isEmpty() ? 'Record loading' : 'Add truck' }}</a>
                                @else
                                    <a href="{{ route('dispatches.show', $order) }}" class="btn btn-light">View order</a>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
            @if ($orders->hasPages())<div class="card-footer">{{ $orders->links() }}</div>@endif
        @endif
    </div>
</x-layouts.app>
