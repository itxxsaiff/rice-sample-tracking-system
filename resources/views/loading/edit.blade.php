<x-layouts.app :title="'Edit '.$loading->loading_number" :back="route('loadings.show', $loading)" action-bar>
    <x-page-header :title="'Edit truck loading '.$loading->loading_number" />
    <form method="POST" action="{{ route('loadings.update', $loading) }}" enctype="multipart/form-data" class="card" novalidate>
        @csrf @method('PUT')
        @include('loading._form')
        <div class="action-bar">
            <a href="{{ route('loadings.show', $loading) }}" class="btn btn-light">Cancel</a>
            <button class="btn btn-primary" data-busy-text="Saving…"><i class="bi bi-check-lg"></i> Save changes</button>
        </div>
    </form>
</x-layouts.app>
