<x-layouts.app title="Record Loading" :back="route('dispatches.show', $dispatch)" action-bar>
    <x-page-header title="Record Truck Loading" :subtitle="$dispatch->buyer->name.' · '.$dispatch->dispatch_number" />
    <form method="POST" action="{{ route('loadings.store', $dispatch) }}" enctype="multipart/form-data" class="card" novalidate>
        @csrf
        @include('loading._form')
        <div class="form-section">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="completeOrder" data-complete-toggle>
                <label class="form-check-label" for="completeOrder">This is the last truck — mark the whole order as <strong>completed</strong></label>
            </div>
        </div>
        <div class="action-bar">
            <button class="btn btn-light" name="action" value="save" data-busy-text="Uploading…"><i class="bi bi-hourglass-split"></i> Save, still loading</button>
            <button class="btn btn-primary" name="action" value="loaded" data-loaded-button data-busy-text="Uploading…"><i class="bi bi-check-lg"></i> <span>Save &amp; mark loaded</span></button>
        </div>
    </form>
</x-layouts.app>
