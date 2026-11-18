<x-layouts.app :title="'Add to '.$bundle->bundle_number" :back="route('bundles.show', $bundle)" action-bar>
    <x-page-header :title="'Add samples to '.$bundle->bundle_number" :subtitle="'Bundle dated '.$bundle->bundle_date->format('d M Y')" />
    <form method="POST" action="{{ route('bundles.samples.store', $bundle) }}" enctype="multipart/form-data" novalidate>
        @csrf
        @if ($errors->any())
            <div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-1"></i> Please fix the highlighted fields. Any photos need to be selected again.</div>
        @endif
        @include('bundles._builder', ['defaultDate' => $bundle->bundle_date->format('Y-m-d')])
        <div class="action-bar action-bar-float">
            <a href="{{ route('bundles.show', $bundle) }}" class="btn btn-light">Cancel</a>
            <button class="btn btn-primary" data-busy-text="Saving…"><i class="bi bi-check-lg"></i> <span>Add <span data-bundle-count>0</span> sample(s)</span></button>
        </div>
    </form>
</x-layouts.app>
