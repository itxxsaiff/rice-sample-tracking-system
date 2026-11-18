<x-layouts.app title="Add Sample" :back="route('samples.index')" action-bar>
    <x-page-header title="Add Sample" subtitle="Record a sample collected from a rice mill.">
        <x-slot:actions>
            <a href="{{ route('bundles.create') }}" class="btn btn-light"><i class="bi bi-boxes me-1"></i> Add several as a bundle</a>
        </x-slot:actions>
    </x-page-header>
    <form method="POST" action="{{ route('samples.store') }}" enctype="multipart/form-data" class="card" novalidate>
        @csrf
        @include('samples._form')
        <div class="action-bar">
            <button class="btn btn-light" name="after" value="another" data-busy-text="Saving…"><i class="bi bi-plus-lg"></i> Save &amp; add another</button>
            <button class="btn btn-primary" name="after" value="view"><i class="bi bi-check-lg"></i> Save sample</button>
        </div>
    </form>
</x-layouts.app>
