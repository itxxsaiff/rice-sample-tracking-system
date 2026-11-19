<x-layouts.app :title="'Edit '.$buyer->name" :back="route('buyers.show', $buyer)" action-bar>
    <x-page-header :title="'Edit '.$buyer->name" />
    <form method="POST" action="{{ route('buyers.update', $buyer) }}" data-offline-op="buyer.update" data-offline-target="{{ $buyer->uuid }}" data-offline-version="{{ $buyer->version }}" class="card" novalidate>
        @csrf @method('PUT')
        @include('buyers._form')
        <div class="action-bar">
            <a href="{{ route('buyers.show', $buyer) }}" class="btn btn-light">Cancel</a>
            <button class="btn btn-primary"><i class="bi bi-check-lg"></i> Save changes</button>
        </div>
    </form>
</x-layouts.app>
