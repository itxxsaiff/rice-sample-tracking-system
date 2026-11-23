@props(['size' => 40, 'dark' => false])
{{-- Ukasha Pacific International logo (same artwork as the company website). The dark version suits light backgrounds such as the sign-in page. --}}
<img src="{{ asset($dark ? 'images/logo-dark.png' : 'images/logo.png') }}" alt="Ukasha Pacific International"
     {{ $attributes->merge(['class' => 'brand-logo', 'style' => "height:{$size}px;width:auto"]) }}>
