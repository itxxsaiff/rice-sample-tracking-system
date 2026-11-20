@php
    $keys = array_keys($rows);
    $nextKey = $keys ? max(array_map('intval', $keys)) + 1 : 0;
@endphp
<x-layouts.app title="Add Sample" :back="route('samples.index')" action-bar>
    <x-page-header title="Add Sample" subtitle="Record samples collected from rice mills. Use “Add another sample” to save several at once — each one is a separate sample." />
    <form method="POST" action="{{ route('samples.store') }}" data-offline-op="sample.create" novalidate>
        @csrf
        @error('samples')<div class="alert alert-danger py-2">{{ $message }}</div>@enderror
        <div class="card mb-4" data-sample-rows-builder data-next-key="{{ $nextKey }}">
            <div class="card-body">
                <div data-sample-rows>
                    @foreach ($rows as $key => $row)
                        @include('samples._row', ['key' => $key, 'row' => $row])
                    @endforeach
                </div>
                <button type="button" class="btn btn-outline-primary w-100 mt-3 py-3" data-add-row style="border-style:dashed">
                    <i class="bi bi-plus-circle"></i> Add another sample
                </button>
            </div>
            <template data-sample-row-template>
                @include('samples._row', ['key' => '__KEY__', 'row' => []])
            </template>
        </div>
        @include('partials.quick-add-modals')
        <div class="action-bar action-bar-float">
            <a href="{{ route('samples.index') }}" class="btn btn-light">Cancel</a>
            <button class="btn btn-primary" data-busy-text="Saving…"><i class="bi bi-check-lg"></i> <span data-save-label>Save sample</span></button>
        </div>
    </form>
</x-layouts.app>
