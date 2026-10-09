<link rel="icon" href="{{ asset('favicon-32.png') }}" type="image/png" sizes="32x32">
<link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
<link rel="manifest" href="{{ asset('site.webmanifest') }}">
<link rel="preload" href="{{ Vite::asset('resources/fonts/plus-jakarta-sans-latin-wght-normal.woff2') }}" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="{{ Vite::asset('resources/fonts/fraunces-latin-wght-normal.woff2') }}" as="font" type="font/woff2" crossorigin>
@vite(['resources/css/app.css', 'resources/js/app.js'])
