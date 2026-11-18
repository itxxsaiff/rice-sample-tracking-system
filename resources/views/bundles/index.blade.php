<x-layouts.app title="Sample Bundles">
    <x-page-header title="Sample Bundles" subtitle="Groups of samples received together.">
        <x-slot:actions>
            <a href="{{ route('bundles.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> New bundle</a>
        </x-slot:actions>
    </x-page-header>

    <div class="card">
        <form method="GET" class="filter-bar" data-auto-submit>
            <div class="row g-2">
                <div class="col-12 col-md-6">
                    <div class="search-input"><i class="bi bi-search"></i>
                        <input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="Bundle no., sample no. or mill">
                    </div>
                </div>
                <div class="col-6 col-md-3"><input type="date" name="from" value="{{ request('from') }}" class="form-control" aria-label="From date"></div>
                <div class="col-6 col-md-3"><input type="date" name="to" value="{{ request('to') }}" class="form-control" aria-label="To date"></div>
            </div>
        </form>

        @if ($bundles->isEmpty())
            <x-empty-state icon="boxes" title="No bundles yet" text="Create a bundle when you receive several samples together.">
                <a href="{{ route('bundles.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> New bundle</a>
            </x-empty-state>
        @else
            <div class="table-responsive">
                <table class="table table-hover table-stack align-middle">
                    <thead><tr><th>Bundle</th><th>Date</th><th>Samples</th><th>Mills</th><th class="text-end"></th></tr></thead>
                    <tbody>
                    @foreach ($bundles as $bundle)
                        @php $mills = $bundle->samples->pluck('mill.name')->unique(); @endphp
                        <tr class="row-link" data-href="{{ route('bundles.show', $bundle) }}">
                            <td class="cell-primary"><a href="{{ route('bundles.show', $bundle) }}" class="cell-title mono">{{ $bundle->bundle_number }}</a>
                                @if ($bundle->notes)<div class="cell-sub text-truncate" style="max-width:320px">{{ $bundle->notes }}</div>@endif</td>
                            <td data-label="Date"><span>{{ $bundle->bundle_date->format('d M Y') }}</span></td>
                            <td data-label="Samples"><span class="fw-semibold">{{ $bundle->samples_count }}</span></td>
                            <td data-label="Mills"><span class="text-truncate d-inline-block" style="max-width:260px">{{ $mills->take(3)->join(', ') }}{{ $mills->count() > 3 ? ' +'.($mills->count() - 3) : '' }}</span></td>
                            <td class="text-end hide-mobile"><i class="bi bi-chevron-right text-muted"></i></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            @if ($bundles->hasPages())<div class="card-footer">{{ $bundles->links() }}</div>@endif
        @endif
    </div>
</x-layouts.app>
