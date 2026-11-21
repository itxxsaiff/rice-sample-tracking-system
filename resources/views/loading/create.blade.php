<x-layouts.app title="Record Loading" :back="route('dispatches.show', $dispatch)" action-bar>
    <x-page-header title="Record Truck Loading" :subtitle="$dispatch->buyer->name.' · '.$dispatch->dispatch_number" />
    <form method="POST" action="{{ route('loadings.store', $dispatch) }}" data-offline-op="loading.create" data-offline-target="{{ $dispatch->uuid }}" enctype="multipart/form-data" class="card" novalidate>
        @csrf
        <input type="hidden" name="return_to" value="{{ old('return_to', $returnTo) }}">
        @include('loading._form')
        <div class="action-bar">
            <a href="{{ old('return_to', $returnTo) }}" class="btn btn-light">Cancel</a>
            <button class="btn btn-primary" name="action" value="loaded" data-busy-text="Uploading…"><i class="bi bi-check-lg"></i> Save truck</button>
        </div>
    </form>
</x-layouts.app>
