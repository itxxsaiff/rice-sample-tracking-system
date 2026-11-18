<x-layouts.app title="Rice Mills">
    <x-page-header title="Rice Mills" subtitle="Permanent mill profiles with their full sample history.">
        <x-slot:actions>
            <a href="{{ route('mills.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> Add mill</a>
        </x-slot:actions>
    </x-page-header>

    <div class="card">
        <form method="GET" class="filter-bar" data-auto-submit>
            <div class="row g-2">
                <div class="col-12 col-md-8">
                    <div class="search-input"><i class="bi bi-search"></i>
                        <input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="Search mill, city, contact or phone">
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <select name="city" class="form-select">
                        <option value="">All cities</option>
                        @foreach ($cities as $city)
                            <option value="{{ $city }}" @selected(request('city') === $city)>{{ $city }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </form>

        @if ($mills->isEmpty())
            <x-empty-state icon="buildings" title="{{ request()->hasAny(['q', 'city']) ? 'No mills match your search' : 'No rice mills yet' }}"
                           text="Add the mills you visit. Samples are recorded against these profiles.">
                <a href="{{ route('mills.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> Add mill</a>
            </x-empty-state>
        @else
            @include('mills._table', ['mills' => $mills])
            @if ($mills->hasPages())<div class="card-footer">{{ $mills->links() }}</div>@endif
        @endif
    </div>
</x-layouts.app>
