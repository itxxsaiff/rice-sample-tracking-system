<x-layouts.app title="New Bundle" :back="route('bundles.index')" action-bar>
    <x-page-header title="New Sample Bundle" subtitle="Record several samples received together. Each one stays linked to its own mill." />
    <form method="POST" action="{{ route('bundles.store') }}" data-offline-op="bundle.create" enctype="multipart/form-data" novalidate>
        @csrf
        @if ($errors->any())
            <div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-1"></i> Please fix the highlighted fields. Any photos need to be selected again.</div>
        @endif
        <div class="card mb-4">
            <div class="card-header"><h2><i class="bi bi-boxes text-primary me-2"></i>Bundle details</h2></div>
            <div class="card-body pb-0">
                <div class="row">
                    <x-form.input class="col-6 col-md-4 mb-3" name="bundle_number" label="Bundle no." :value="null" :placeholder="$nextNumber.' (auto)'" autocapitalize="characters" />
                    <x-form.input class="col-6 col-md-4 mb-3" name="bundle_date" type="date" label="Date" :value="$bundle->bundle_date" required max="{{ now()->format('Y-m-d') }}" data-bundle-date />
                    <x-form.input class="col-12 col-md-4 mb-3" name="notes" label="Notes" placeholder="Optional" />
                </div>
            </div>
        </div>
        @include('bundles._builder', ['defaultDate' => old('bundle_date', $bundle->bundle_date->format('Y-m-d'))])
        <div class="action-bar action-bar-float">
            <a href="{{ route('bundles.index') }}" class="btn btn-light">Cancel</a>
            <button class="btn btn-primary" data-busy-text="Saving bundle…"><i class="bi bi-check-lg"></i> <span>Save bundle (<span data-bundle-count>0</span>)</span></button>
        </div>
    </form>
</x-layouts.app>
