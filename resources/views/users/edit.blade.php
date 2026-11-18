<x-layouts.app :title="'Edit '.$user->name" :back="route('users.index')" action-bar>
    <x-page-header :title="'Edit '.$user->name" :subtitle="$user->is_active ? 'Active account' : 'This account is deactivated'" />
    <form method="POST" action="{{ route('users.update', $user) }}" class="card" novalidate>
        @csrf @method('PUT')
        @include('users._form')
        <div class="action-bar">
            <a href="{{ route('users.index') }}" class="btn btn-light">Cancel</a>
            <button class="btn btn-primary"><i class="bi bi-check-lg"></i> Save changes</button>
        </div>
    </form>
</x-layouts.app>
