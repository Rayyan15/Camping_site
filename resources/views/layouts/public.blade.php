@php
    $siteName = config('site.name');
    $pageTitle = trim($__env->yieldContent('title')) ?: $siteName.' - Menginap di tenda, pesan online';
    $pageDescription = trim($__env->yieldContent('description')) ?: 'Pesan tenda di '.$siteName.': 7 tipe tenda, 32 unit, cek ketersediaan per tanggal, bayar online, dan pesan makanan lewat QR dari tenda.';
    $whatsapp = config('site.whatsapp_number');
    $customOgImage = trim($__env->yieldContent('og_image'));
    $hasCustomOgImage = $customOgImage !== '';
    $ogImage = $hasCustomOgImage ? url($customOgImage) : asset('images/og-default.jpg');
    $ogImageWidth = $hasCustomOgImage ? 1600 : 1200;
    $ogImageHeight = $hasCustomOgImage ? 1067 : 630;
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $pageTitle }}</title>
    <meta name="description" content="{{ $pageDescription }}">
    <meta name="theme-color" content="#1c3526">
    @if(trim($__env->yieldContent('noindex')))
        <meta name="robots" content="noindex, nofollow">
    @endif
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ $siteName }}">
    <meta property="og:title" content="{{ $pageTitle }}">
    <meta property="og:description" content="{{ $pageDescription }}">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:locale" content="id_ID">
    <meta property="og:image" content="{{ $ogImage }}">
    <meta property="og:image:width" content="{{ $ogImageWidth }}">
    <meta property="og:image:height" content="{{ $ogImageHeight }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $pageTitle }}">
    <meta name="twitter:description" content="{{ $pageDescription }}">
    <meta name="twitter:image" content="{{ $ogImage }}">
    <link rel="canonical" href="{{ url()->current() }}">

    @include('partials.head-assets')
    @stack('head')
</head>
<body class="min-h-screen flex flex-col">
    <a href="#konten" class="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-[100] focus:bg-cream focus:text-forest-900 focus:px-4 focus:py-2 focus:rounded-lg">Lewati ke konten</a>

    @include('partials.public-nav')

    <main id="konten" class="flex-1">
        @yield('content')
    </main>

    @include('partials.public-footer')

    @if($whatsapp)
        <a href="https://wa.me/{{ $whatsapp }}" target="_blank" rel="noopener"
           class="fixed bottom-4 right-4 z-40 inline-flex items-center gap-2 rounded-full bg-forest-800 py-3 pl-4 pr-5 text-sm font-bold text-cream shadow-lg shadow-forest-950/30 ring-2 ring-cream transition hover:bg-forest-700">
            <svg class="size-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.76c0 1.6 1.123 2.994 2.707 3.227 1.087.16 2.185.283 3.293.369V21l4.076-4.076a1.526 1.526 0 011.037-.443 48.282 48.282 0 005.68-.494c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z"/></svg>
            Chat WhatsApp
        </a>
    @endif
</body>
</html>
