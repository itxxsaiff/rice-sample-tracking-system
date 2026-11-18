@props(['name', 'label' => null, 'type' => 'text', 'value' => null, 'required' => false, 'hint' => null, 'prefix' => null, 'suffix' => null])
@php
    $key = str_replace(['[', ']'], ['.', ''], $name);
    $id = $attributes->get('id', 'f_'.str_replace('.', '_', $key));
    $current = $type === 'password' ? null : old($key, $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : $value);
@endphp
<div {{ $attributes->only('class')->merge(['class' => 'mb-3']) }}>
    @if ($label)<label for="{{ $id }}" @class(['form-label', 'required' => $required])>{{ $label }}</label>@endif
    @if ($prefix || $suffix)<div class="input-group has-validation">@endif
        @if ($prefix)<span class="input-group-text">{{ $prefix }}</span>@endif
        <input type="{{ $type }}" name="{{ $name }}" id="{{ $id }}" value="{{ $current }}"
               {{ $attributes->except(['class', 'id'])->class(['form-control', 'is-invalid' => $errors->has($key)]) }}
               @required($required)>
        @if ($suffix)<span class="input-group-text">{{ $suffix }}</span>@endif
        @error($key)<div class="invalid-feedback">{{ $message }}</div>@enderror
    @if ($prefix || $suffix)</div>@endif
    @if ($hint)<div class="form-text">{{ $hint }}</div>@endif
</div>
