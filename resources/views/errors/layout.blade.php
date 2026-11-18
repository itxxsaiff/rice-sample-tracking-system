<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>@yield('title') · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css'])
</head>
<body>
    <main class="auth-wrap">
        <div class="text-center" style="max-width:420px">
            <div class="empty-icon mx-auto mb-3" style="width:72px;height:72px;border-radius:20px;background:var(--brand-soft);color:var(--brand);display:flex;align-items:center;justify-content:center;font-size:2rem"><i class="bi bi-@yield('icon', 'exclamation-circle')"></i></div>
            <div class="small-caps mb-1">Error @yield('code')</div>
            <h1 class="h4 fw-bold">@yield('title')</h1>
            <p class="text-muted">@yield('message')</p>
            <div class="d-flex gap-2 justify-content-center">
                <a href="javascript:history.back()" class="btn btn-light">Go back</a>
                <a href="{{ url('/') }}" class="btn btn-primary">Dashboard</a>
            </div>
        </div>
    </main>
</body>
</html>
