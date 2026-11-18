@props(['status'])
@php
    $value = $status instanceof \BackedEnum ? $status->value : $status;
    $label = $status instanceof \BackedEnum && method_exists($status, 'label') ? $status->label() : ucfirst(str_replace('_', ' ', (string) $value));
@endphp
<span {{ $attributes->merge(['class' => 'status-badge status-'.$value]) }}>{{ $label }}</span>
