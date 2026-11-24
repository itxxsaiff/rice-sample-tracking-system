@props(['size' => 40])
{{-- Ukasha Pacific International logo (the TM version), used everywhere in the app. --}}
<img src="{{ asset('images/logo-dark.png') }}" alt="Ukasha Pacific International"
     {{ $attributes->merge(['class' => 'brand-logo', 'style' => "height:{$size}px;width:auto"]) }}>
