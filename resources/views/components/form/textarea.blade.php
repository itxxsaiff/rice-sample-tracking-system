@props(['name', 'label' => null, 'value' => null, 'required' => false, 'hint' => null, 'rows' => 3])
@php
    $key = str_replace(['[', ']'], ['.', ''], $name);
    $id = $attributes->get('id', 'f_'.str_replace('.', '_', $key));
@endphp
<div {{ $attributes->only('class')->merge(['class' => 'mb-3']) }}>
    @if ($label)<label for="{{ $id }}" @class(['form-label', 'required' => $required])>{{ $label }}</label>@endif
    <textarea name="{{ $name }}" id="{{ $id }}" rows="{{ $rows }}" {{ $attributes->except(['class', 'id'])->class(['form-control', 'is-invalid' => $errors->has($key)]) }} @required($required)>{{ old($key, $value) }}</textarea>
    @error($key)<div class="invalid-feedback">{{ $message }}</div>@enderror
    @if ($hint)<div class="form-text">{{ $hint }}</div>@endif
</div>
