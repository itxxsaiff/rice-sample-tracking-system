<x-layouts.app title="Search">
    <form method="GET" action="{{ route('search') }}" class="mb-4" role="search">
        <div class="search-input">
            <i class="bi bi-search"></i>
            <input type="search" name="q" value="{{ $q }}" class="form-control form-control-lg" placeholder="Sample no., buyer, mill, truck no., dispatch no.…" autofocus aria-label="Search all records" enterkeyhint="search">
        </div>
    </form>

    @if (mb_strlen($q) < 2)
        <x-empty-state icon="search" title="Search all records" text="Type at least 2 characters — e.g. SMP-0012, a buyer or mill name, a city, a variety or a truck number." />
    @elseif (empty($results))
        <div class="card"><x-empty-state icon="search" title="No results for “{{ $q }}”" text="Try a shorter or different term." /></div>
    @else
        <div class="row g-4">
            @foreach ($results as $group => $items)
                <div class="col-lg-6">
                    <div class="card h-100">
                        <div class="card-header"><h2>{{ $group }} <span class="badge rounded-pill text-bg-light border ms-1">{{ $items->count() }}</span></h2></div>
                        <div class="list-group list-group-flush">
                            @foreach ($items as [$url, $title, $sub, $icon])
                                <a href="{{ $url }}" class="list-group-item list-group-item-action d-flex gap-3 align-items-center py-3">
                                    <span class="stat-icon tone-green flex-shrink-0" style="width:36px;height:36px;font-size:1rem"><i class="bi bi-{{ $icon }}"></i></span>
                                    <span class="min-w-0"><span class="d-block fw-medium text-truncate">{{ $title }}</span><span class="small text-muted">{{ $sub }}</span></span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</x-layouts.app>
