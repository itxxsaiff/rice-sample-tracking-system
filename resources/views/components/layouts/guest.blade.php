@props(['title' => null])
<!DOCTYPE html>
<html lang="en">
<head>
    @include('partials.head', ['title' => $title])
</head>
<body>
    <main class="auth-wrap">
        {{ $slot }}
    </main>
    @include('partials.flash')
</body>
</html>
