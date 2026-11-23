<x-layouts.app title="Dispatch Samples" :back="route('dispatches.index')" action-bar>
    <x-page-header title="Dispatch Samples" subtitle="Send samples to buyers. Use “Add another dispatch” to send different samples to different buyers in one go — every sample–buyer pair is recorded separately." />

    @php
        $keys = array_keys($groups);
        $nextKey = $keys ? max(array_map('intval', $keys)) + 1 : 0;
    @endphp
    <form method="POST" action="{{ route('dispatches.store') }}" data-offline-op="dispatch.create" novalidate>
        @csrf
        @error('dispatches')<div class="alert alert-danger py-2">{{ $message }}</div>@enderror
        @if ($buyers->isEmpty())
            <div class="alert alert-warning">No buyers yet. Add one with <strong>New buyer</strong>.</div>
        @endif
        <div class="card mb-4" data-dispatch-builder data-next-key="{{ $nextKey }}">
            <div class="card-body">
                <div data-dispatch-groups>
                    @foreach ($groups as $key => $group)
                        @include('dispatches._group', ['key' => $key, 'group' => $group])
                    @endforeach
                </div>
                <button type="button" class="btn btn-outline-primary w-100 mt-3 py-3" data-add-group style="border-style:dashed">
                    <i class="bi bi-plus-circle"></i> Add another dispatch
                </button>
                <p class="form-text mb-0 mt-2">Tip: open a sample and tap <strong>Dispatch</strong> to pre-fill the samples.</p>
            </div>
            <template data-dispatch-group-template>
                @include('dispatches._group', ['key' => '__KEY__', 'group' => []])
            </template>
        </div>
        <div class="action-bar action-bar-float">
            <a href="{{ route('dispatches.index') }}" class="btn btn-light">Cancel</a>
            <button class="btn btn-primary" data-busy-text="Sending…"><i class="bi bi-send"></i> <span data-save-label>Record dispatch</span></button>
        </div>
    </form>

    @push('modals')
        <div class="modal fade" id="quickBuyerModal" tabindex="-1" aria-labelledby="quickBuyerTitle" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-fullscreen-sm-down">
                <form class="modal-content" method="POST" action="{{ route('buyers.store') }}" data-quick-form="buyer" novalidate>
                    <div class="modal-header"><h5 class="modal-title fw-semibold" id="quickBuyerTitle">New buyer</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                    <div class="modal-body">
                        <div class="mb-3"><label class="form-label required" for="qb_name">Buyer / company name</label><input class="form-control" id="qb_name" name="name" required autocapitalize="words"><div class="invalid-feedback"></div></div>
                        <div class="mb-3"><label class="form-label required" for="qb_city">City</label><input class="form-control" id="qb_city" name="city" list="citySuggestions" required autocapitalize="words" autocomplete="off"><div class="invalid-feedback"></div></div>
                        <div class="mb-3"><label class="form-label" for="qb_phone">Contact number</label><input class="form-control" id="qb_phone" name="phone" type="tel" inputmode="tel"><div class="invalid-feedback"></div></div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Add buyer</button></div>
                </form>
            </div>
        </div>
        @include('partials.city-datalist')
    @endpush
</x-layouts.app>
