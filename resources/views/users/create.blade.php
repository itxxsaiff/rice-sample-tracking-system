<x-layouts.app title="Add User" :back="route('users.index')" action-bar>
    <x-page-header title="Add User" subtitle="The new user signs in on the same website with these credentials." />
    <form method="POST" action="{{ route('users.store') }}" class="card" novalidate>
        @csrf
        @include('users._form')
        <div class="action-bar">
            <a href="{{ route('users.index') }}" class="btn btn-light">Cancel</a>
            <button class="btn btn-primary"><i class="bi bi-check-lg"></i> Create account</button>
        </div>
    </form>
</x-layouts.app>
