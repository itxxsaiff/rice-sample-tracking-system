<x-layouts.app title="Rice Varieties">
    <x-page-header title="Rice Varieties" subtitle="The list used when recording samples. Add any variety you deal in." />

    <div class="row g-4">
        <div class="col-lg-4 order-lg-2">
            <div class="card">
                <div class="card-header"><h2><i class="bi bi-plus-circle text-primary me-2"></i>Add variety</h2></div>
                <form method="POST" action="{{ route('varieties.store') }}" class="card-body" novalidate>
                    @csrf
                    <x-form.input name="name" label="Variety name" required placeholder="e.g. 1121, 1509, Super Basmati" />
                    <x-form.input name="description" label="Description" placeholder="Optional" />
                    <button class="btn btn-primary w-100"><i class="bi bi-plus-lg"></i> Add variety</button>
                </form>
            </div>
        </div>
        <div class="col-lg-8 order-lg-1">
            <div class="card">
                <form method="GET" class="filter-bar">
                    <div class="search-input"><i class="bi bi-search"></i>
                        <input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="Search varieties">
                    </div>
                </form>
                @if ($varieties->isEmpty())
                    <x-empty-state icon="tags" title="No varieties found" text="Add the rice varieties you trade." />
                @else
                    <div class="table-responsive">
                        <table class="table table-hover table-stack align-middle">
                            <thead><tr><th>Variety</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
                            <tbody>
                            @foreach ($varieties as $variety)
                                <tr>
                                    <td class="cell-primary">
                                        <div class="cell-title">{{ $variety->name }}</div>
                                        @if ($variety->description)<div class="cell-sub">{{ $variety->description }}</div>@endif
                                    </td>
                                    <td data-label="Status"><span><x-status-badge :status="$variety->is_active ? 'active' : 'inactive'" /></span></td>
                                    <td class="cell-actions text-end text-nowrap">
                                        <a href="{{ route('varieties.edit', $variety) }}" class="btn btn-sm btn-light"><i class="bi bi-pencil"></i> Edit</a>
                                        @can('delete', $variety)
                                            <form method="POST" action="{{ route('varieties.destroy', $variety) }}" class="d-inline" data-confirm="{{ $variety->name }} is not used by any sample and will be removed." data-confirm-title="Delete variety?" data-confirm-button="Delete">
                                                @csrf @method('DELETE')
                                                <button class="btn btn-sm btn-outline-danger" aria-label="Delete"><i class="bi bi-trash"></i></button>
                                            </form>
                                        @endcan
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-layouts.app>
