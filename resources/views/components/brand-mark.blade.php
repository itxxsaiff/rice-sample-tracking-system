@props(['size' => 40])
{{-- Ukasha Pacific International logo (same artwork as the company website). --}}
<img src="{{ asset('images/logo.png') }}" alt="Ukasha Pacific International"
     {{ $attributes->merge(['class' => 'brand-logo', 'style' => "height:{$size}px;width:auto"]) }}>
