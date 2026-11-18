<x-layouts.app title="Dispatches">
    <x-page-header title="Sample Dispatches" subtitle="Samples sent to buyers and their responses. Each buyer's answer is tracked separately.">
        <x-slot:actions>
            <a href="{{ route('dispatches.create') }}" class="btn btn-primary"><i class="bi bi-send me-1"></i> Dispatch samples</a>
        </x-slot:actions>
    </x-page-header>

    <div class="card">
        <nav class="nav-tabs-pills" aria-label="Filter by status">
            @php $total = $counts->sum(); @endphp
            <a href="{{ request()->fullUrlWithQuery(['status' => null, 'page' => null]) }}" @class(['active' => empty($filters['status'])])>All<span class="count">{{ $total }}</span></a>
            @foreach (\App\Enums\DispatchStatus::cases() as $status)
                <a href="{{ request()->fullUrlWithQuery(['status' => $status->value, 'page' => null]) }}" @class(['active' => ($filters['status'] ?? null) === $status->value])>
                    {{ $status === \App\Enums\DispatchStatus::Pending ? 'Pending' : $status->label() }}<span class="count">{{ $counts[$status->value] ?? 0 }}</span>
                </a>
            @endforeach
        </nav>
        <form method="GET" class="filter-bar" data-auto-submit>
            @if (! empty($filters['status']))<input type="hidden" name="status" value="{{ $filters['status'] }}">@endif
            <div class="row g-2">
                <div class="col-12 col-lg-4">
                    <div class="search-input"><i class="bi bi-search"></i>
                        <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control" placeholder="Dispatch no., sample no., buyer, mill">
                    </div>
                </div>
                <div class="col-6 col-lg-2">
                    <select name="buyer_id" class="form-select" aria-label="Buyer">
                        <option value="">All buyers</option>
                        @foreach ($buyers as $id => $name)<option value="{{ $id }}" @selected(($filters['buyer_id'] ?? '') == $id)>{{ $name }}</option>@endforeach
                    </select>
                </div>
                <div class="col-6 col-lg-2">
                    <select name="variety_id" class="form-select" aria-label="Variety">
                        <option value="">All varieties</option>
                        @foreach ($varieties as $id => $name)<option value="{{ $id }}" @selected(($filters['variety_id'] ?? '') == $id)>{{ $name }}</option>@endforeach
                    </select>
                </div>
                <div class="col-6 col-lg-2"><input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="form-control" aria-label="From date"></div>
                <div class="col-6 col-lg-2"><input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="form-control" aria-label="To date"></div>
                @if ($filterMill)
                    <input type="hidden" name="mill_id" value="{{ $filterMill->id }}">
                    <div class="col-12"><span class="badge text-bg-light border p-2">Mill: {{ $filterMill->name }} <a href="{{ request()->fullUrlWithoutQuery(['mill_id', 'page']) }}" aria-label="Clear mill filter"><i class="bi bi-x"></i></a></span></div>
                @endif
            </div>
        </form>

        @if ($dispatches->isEmpty())
            <x-empty-state icon="send" :title="array_filter($filters) ? 'No dispatches match' : 'No dispatches yet'" text="Send samples to one or more buyers and record each buyer's response.">
                <a href="{{ route('dispatches.create') }}" class="btn btn-primary"><i class="bi bi-send me-1"></i> Dispatch samples</a>
            </x-empty-state>
        @else
            @include('dispatches._table')
            @if ($dispatches->hasPages())<div class="card-footer">{{ $dispatches->links() }}</div>@endif
        @endif
    </div>
</x-layouts.app>
