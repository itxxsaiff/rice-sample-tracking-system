<x-layouts.app :title="'Edit '.$mill->name" :back="route('mills.show', $mill)" action-bar>
    <x-page-header :title="'Edit '.$mill->name" />
    <form method="POST" action="{{ route('mills.update', $mill) }}" class="card" novalidate>
        @csrf @method('PUT')
        @include('mills._form')
        <div class="action-bar">
            <a href="{{ route('mills.show', $mill) }}" class="btn btn-light">Cancel</a>
            <button class="btn btn-primary"><i class="bi bi-check-lg"></i> Save changes</button>
        </div>
    </form>
</x-layouts.app>
