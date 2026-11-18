@php
    $keys = array_keys($oldRows);
    $nextKey = $keys ? max(array_map('intval', $keys)) + 1 : 0;
@endphp
<div class="card mb-4" data-bundle-builder data-next-key="{{ $nextKey }}">
    <div class="card-header justify-content-between">
        <h2><i class="bi bi-basket text-primary me-2"></i>Samples in this bundle</h2>
        <span class="badge rounded-pill text-bg-light border fs-6" data-bundle-count>0</span>
    </div>
    <div class="card-body">
        <p class="small text-muted mt-n1">Each sample can come from a different mill. Use <strong>Add another sample</strong> for every sample in the bundle — the mill and variety are copied from the previous row to save time.</p>
        @error('samples')<div class="alert alert-danger py-2">{{ $message }}</div>@enderror
        <div data-bundle-rows>
            @foreach ($oldRows as $key => $row)
                @include('bundles._row', ['key' => $key, 'row' => $row])
            @endforeach
        </div>
        <button type="button" class="btn btn-outline-primary w-100 mt-3 py-3" data-add-row style="border-style:dashed">
            <i class="bi bi-plus-circle"></i> Add another sample
        </button>
    </div>
    <template data-bundle-template>
        @include('bundles._row', ['key' => '__KEY__', 'row' => []])
    </template>
</div>
@include('partials.quick-add-modals')
