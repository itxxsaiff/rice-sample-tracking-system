@props(['name', 'options', 'value' => null, 'label' => 'Rice variety', 'required' => true])
@php
    $key = str_replace(['[', ']'], ['.', ''], $name);
    $current = (string) old($key, $value);
    $id = 'f_'.str_replace('.', '_', $key);
@endphp
<div {{ $attributes->merge(['class' => 'mb-3 quick-scope']) }}>
    <div class="d-flex justify-content-between align-items-end gap-2">
        <label for="{{ $id }}" @class(['form-label', 'required' => $required])>{{ $label }}</label>
        <button type="button" class="btn btn-link btn-sm p-0 mb-1 text-decoration-none" data-quick-add="#quickVarietyModal"><i class="bi bi-plus-circle"></i> New variety</button>
    </div>
    <select name="{{ $name }}" id="{{ $id }}" data-variety-select @class(['form-select', 'is-invalid' => $errors->has($key)]) @required($required)>
        <option value="">Select variety</option>
        @foreach ($options as $optValue => $optLabel)
            <option value="{{ $optValue }}" @selected($current === (string) $optValue)>{{ $optLabel }}</option>
        @endforeach
    </select>
    @error($key)<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>
