@php
    $siteName = config('site.name');
    $pageTitle = trim($__env->yieldContent('title')) ?: $siteName;
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#1c3526">
    <title>{{ $pageTitle }}</title>

    @include('partials.head-assets')
</head>
<body class="min-h-screen bg-cream">
    <main id="konten" class="mx-auto flex min-h-screen w-full max-w-xl flex-col">
        @yield('content')
    </main>
</body>
</html>
