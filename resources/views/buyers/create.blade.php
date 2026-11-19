<x-layouts.app title="Add Buyer" :back="route('buyers.index')" action-bar>
    <x-page-header title="Add Buyer" subtitle="Each buyer has one permanent profile holding every dispatch, confirmation and loading." />
    <form method="POST" action="{{ route('buyers.store') }}" data-offline-op="buyer.create" class="card" novalidate>
        @csrf
        @include('buyers._form')
        <div class="action-bar">
            <a href="{{ route('buyers.index') }}" class="btn btn-light">Cancel</a>
            <button class="btn btn-primary"><i class="bi bi-check-lg"></i> Save buyer</button>
        </div>
    </form>
</x-layouts.app>
