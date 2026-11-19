<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
@auth
    <meta name="app-user" content='@json(['id' => auth()->id(), 'name' => auth()->user()->name, 'role' => auth()->user()->role->value])'>
@endauth
<meta name="theme-color" content="#0f2a4a">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="Rice Samples">
<meta name="format-detection" content="telephone=no">
<title>{{ $title ? $title.' · ' : '' }}{{ config('app.name') }}</title>
<link rel="icon" href="{{ asset('favicon-32.png') }}" type="image/png" sizes="32x32">
<link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
<link rel="manifest" href="{{ asset('site.webmanifest') }}">
@fonts
@vite(['resources/css/app.css', 'resources/js/app.js'])
