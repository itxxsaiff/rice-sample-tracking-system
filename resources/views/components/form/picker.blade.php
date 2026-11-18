@props(['name', 'label' => null, 'source', 'value' => null, 'display' => null, 'required' => false, 'placeholder' => 'Search…', 'quickAdd' => null, 'quickLabel' => 'Add new', 'hint' => null])
@php
    $key = str_replace(['[', ']'], ['.', ''], $name);
    $id = 'f_'.str_replace('.', '_', $key);
    $current = old($key, $value);
@endphp
<div {{ $attributes->merge(['class' => 'mb-3 quick-scope']) }}>
    <div class="d-flex justify-content-between align-items-end gap-2">
        @if ($label)<label for="{{ $id }}" @class(['form-label', 'required' => $required])>{{ $label }}</label>@endif
        @if ($quickAdd)
            <button type="button" class="btn btn-link btn-sm p-0 mb-1 text-decoration-none" data-quick-add="{{ $quickAdd }}"><i class="bi bi-plus-circle"></i> {{ $quickLabel }}</button>
        @endif
    </div>
    <div class="picker" data-picker data-source="{{ $source }}" data-name="{{ $name }}">
        <div class="search-input">
            <i class="bi bi-search"></i>
            <input type="text" id="{{ $id }}" data-picker-input value="{{ $current ? $display : '' }}" placeholder="{{ $placeholder }}"
                   @class(['form-control', 'is-invalid' => $errors->has($key)]) autocomplete="off" autocorrect="off" spellcheck="false" enterkeyhint="search">
        </div>
        <input type="hidden" name="{{ $name }}" value="{{ $current }}">
        <div class="picker-menu" role="listbox"></div>
    </div>
    @error($key)<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    @if ($hint)<div class="form-text">{{ $hint }}</div>@endif
</div>
