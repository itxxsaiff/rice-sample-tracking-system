@props(['name', 'label' => null, 'options' => [], 'value' => null, 'required' => false, 'placeholder' => null, 'hint' => null])
@php
    $key = str_replace(['[', ']'], ['.', ''], $name);
    $id = $attributes->get('id', 'f_'.str_replace('.', '_', $key));
    $current = (string) old($key, $value instanceof \BackedEnum ? $value->value : $value);
@endphp
<div {{ $attributes->only('class')->merge(['class' => 'mb-3']) }}>
    @if ($label)<label for="{{ $id }}" @class(['form-label', 'required' => $required])>{{ $label }}</label>@endif
    <select name="{{ $name }}" id="{{ $id }}" {{ $attributes->except(['class', 'id'])->class(['form-select', 'is-invalid' => $errors->has($key)]) }} @required($required)>
        @if ($placeholder !== null)<option value="">{{ $placeholder }}</option>@endif
        @foreach ($options as $optValue => $optLabel)
            <option value="{{ $optValue }}" @selected($current === (string) $optValue)>{{ $optLabel }}</option>
        @endforeach
        {{ $slot }}
    </select>
    @error($key)<div class="invalid-feedback">{{ $message }}</div>@enderror
    @if ($hint)<div class="form-text">{{ $hint }}</div>@endif
</div>
