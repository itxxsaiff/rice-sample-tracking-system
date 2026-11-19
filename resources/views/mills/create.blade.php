<x-layouts.app title="Add Rice Mill" :back="route('mills.index')" action-bar>
    <x-page-header title="Add Rice Mill" subtitle="Each mill has one permanent profile. All its samples are kept under it." />
    <form method="POST" action="{{ route('mills.store') }}" data-offline-op="mill.create" class="card" novalidate>
        @csrf
        @include('mills._form')
        <div class="action-bar">
            <a href="{{ route('mills.index') }}" class="btn btn-light">Cancel</a>
            <button class="btn btn-primary"><i class="bi bi-check-lg"></i> Save mill</button>
        </div>
    </form>
</x-layouts.app>
