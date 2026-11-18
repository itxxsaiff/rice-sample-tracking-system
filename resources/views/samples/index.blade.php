<x-layouts.app title="Samples">
    <x-page-header title="Samples" subtitle="Every sample collected, linked to its mill and bundle.">
        <x-slot:actions>
            <a href="{{ route('bundles.create') }}" class="btn btn-light"><i class="bi bi-boxes me-1"></i> New bundle</a>
            <a href="{{ route('samples.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> Add sample</a>
        </x-slot:actions>
    </x-page-header>

    <div class="card">
        <form method="GET" class="filter-bar" data-auto-submit>
            <div class="row g-2">
                <div class="col-12 col-lg-12 col-xl-4">
                    <div class="search-input"><i class="bi bi-search"></i>
                        <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control" placeholder="Sample no., mill, variety, bundle">
                    </div>
                </div>
                <div class="col-6 col-lg-2">
                    <select name="variety_id" class="form-select" aria-label="Variety">
                        <option value="">All varieties</option>
                        @foreach ($varieties as $id => $name)<option value="{{ $id }}" @selected(($filters['variety_id'] ?? '') == $id)>{{ $name }}</option>@endforeach
                    </select>
                </div>
                <div class="col-6 col-lg-2">
                    <select name="city" class="form-select" aria-label="City">
                        <option value="">All cities</option>
                        @foreach ($cities as $city)<option value="{{ $city }}" @selected(($filters['city'] ?? '') === $city)>{{ $city }}</option>@endforeach
                    </select>
                </div>
                <div class="col-6 col-lg-2"><input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="form-control" aria-label="From date" title="From"></div>
                <div class="col-6 col-lg-2"><input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="form-control" aria-label="To date" title="To"></div>
                <div class="col-12 col-lg-2">
                    <select name="sent" class="form-select" aria-label="Dispatch status">
                        <option value="">Sent &amp; not sent</option>
                        <option value="no" @selected(($filters['sent'] ?? '') === 'no')>Not sent yet</option>
                        <option value="yes" @selected(($filters['sent'] ?? '') === 'yes')>Sent to buyers</option>
                    </select>
                </div>
                @if ($filterMill)
                    <input type="hidden" name="mill_id" value="{{ $filterMill->id }}">
                    <div class="col-12"><span class="badge text-bg-light border p-2">Mill: {{ $filterMill->name }} <a href="{{ request()->fullUrlWithoutQuery(['mill_id', 'page']) }}" class="ms-1" aria-label="Clear mill filter"><i class="bi bi-x"></i></a></span></div>
                @endif
            </div>
            @if (array_filter($filters))
                <div class="mt-2 small"><span class="text-muted">{{ $samples->total() }} result(s)</span> · <a href="{{ route('samples.index') }}">Clear filters</a></div>
            @endif
        </form>

        @if ($samples->isEmpty())
            <x-empty-state icon="basket" :title="array_filter($filters) ? 'No samples match these filters' : 'No samples yet'" text="Record samples as you collect them at mills.">
                <a href="{{ route('samples.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> Add sample</a>
            </x-empty-state>
        @else
            @include('samples._table', ['samples' => $samples])
            @if ($samples->hasPages())<div class="card-footer">{{ $samples->links() }}</div>@endif
        @endif
    </div>
</x-layouts.app>
