<x-layouts.app title="Buyers">
    <x-page-header title="Buyers" subtitle="Permanent buyer profiles with every dispatch and order.">
        <x-slot:actions>
            <a href="{{ route('buyers.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> Add buyer</a>
        </x-slot:actions>
    </x-page-header>

    <div class="card">
        <form method="GET" class="filter-bar" data-auto-submit>
            <div class="row g-2">
                <div class="col-12 col-md-8">
                    <div class="search-input"><i class="bi bi-search"></i>
                        <input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="Search buyer, city, contact or phone">
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

        @if ($buyers->isEmpty())
            <x-empty-state icon="person-vcard" title="{{ request()->hasAny(['q', 'city']) ? 'No buyers match your search' : 'No buyers yet' }}"
                           text="Add the buyers you send samples to.">
                <a href="{{ route('buyers.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> Add buyer</a>
            </x-empty-state>
        @else
            @include('buyers._table', ['buyers' => $buyers])
            @if ($buyers->hasPages())<div class="card-footer">{{ $buyers->links() }}</div>@endif
        @endif
    </div>
</x-layouts.app>
