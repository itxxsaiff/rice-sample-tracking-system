@props(['status'])
@php
    $value = $status instanceof \BackedEnum ? $status->value : $status;
    $labels = ['pending' => 'Awaiting response', 'confirmed' => 'Confirmed', 'rejected' => 'Rejected', 'in_progress' => 'Loading', 'completed' => 'Completed', 'active' => 'Active', 'inactive' => 'Inactive', 'loading_pending' => 'Loading pending'];
    $label = $slot->isNotEmpty() ? $slot : ($status instanceof \BackedEnum && method_exists($status, 'label') ? $status->label() : ($labels[$value] ?? ucfirst(str_replace('_', ' ', (string) $value))));
    $css = $value === 'loading_pending' ? 'pending' : $value;
@endphp
<span {{ $attributes->merge(['class' => 'status-badge status-'.$css]) }}>{{ $label }}</span>
