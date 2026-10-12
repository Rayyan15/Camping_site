{{-- Brand palette as CSS variables for every custom panel view; values come from config/brand.php. --}}
@php($brand = config('brand'))
<style>
    @font-face {
        font-family: "Plus Jakarta Sans";
        font-style: normal;
        font-weight: 200 800;
        font-display: swap;
        src: url("{{ Vite::asset('resources/fonts/plus-jakarta-sans-latin-wght-normal.woff2') }}") format("woff2");
    }

    :root {
        @foreach($brand['forest'] as $step => $hex)
            --hh-forest-{{ $step }}: {{ $hex }};
        @endforeach
        --hh-cream: {{ $brand['cream'] }};
        --hh-cream-deep: {{ $brand['cream_deep'] }};
        --hh-sand: {{ $brand['sand'] }};
        --hh-sand-dark: {{ $brand['sand_dark'] }};
        --hh-ember: {{ $brand['ember'] }};
        --hh-ember-dark: {{ $brand['ember_dark'] }};
        --hh-ember-light: {{ $brand['ember_light'] }};
        --hh-ember-soft: {{ $brand['ember_soft'] }};
        --hh-ink: {{ $brand['ink'] }};
        --hh-ink-soft: {{ $brand['ink_soft'] }};
    }
</style>
