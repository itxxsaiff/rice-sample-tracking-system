<x-layouts.app :title="'Edit '.$bundle->bundle_number" :back="route('bundles.show', $bundle)" action-bar>
    <x-page-header :title="'Edit bundle '.$bundle->bundle_number" />
    <form method="POST" action="{{ route('bundles.update', $bundle) }}" class="card" novalidate>
        @csrf @method('PUT')
        <div class="form-section">
            <div class="row">
                <x-form.input class="col-6 mb-3" name="bundle_number" label="Bundle no." :value="$bundle->bundle_number" required autocapitalize="characters" />
                <x-form.input class="col-6 mb-3" name="bundle_date" type="date" label="Date" :value="$bundle->bundle_date" required max="{{ now()->format('Y-m-d') }}" />
                <x-form.textarea class="col-12 mb-0" name="notes" label="Notes" :value="$bundle->notes" />
            </div>
        </div>
        <div class="action-bar">
            <a href="{{ route('bundles.show', $bundle) }}" class="btn btn-light">Cancel</a>
            <button class="btn btn-primary"><i class="bi bi-check-lg"></i> Save changes</button>
        </div>
    </form>
</x-layouts.app>
