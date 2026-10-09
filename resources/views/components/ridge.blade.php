@props(['class' => ''])

{{-- Tent-peak edge between phases. Colour comes from text-* on the caller: it is the colour of the NEXT phase. --}}
<svg viewBox="0 0 1200 48" preserveAspectRatio="none" aria-hidden="true" focusable="false" fill="currentColor" {{ $attributes->merge(['class' => 'pointer-events-none absolute inset-x-0 bottom-0 block h-8 w-full translate-y-px sm:h-12 '.$class]) }}>
    <path d="M0 48L50 8L100 48L170 0L240 48L300 14L360 48L430 4L500 48L560 18L620 48L700 2L770 48L830 12L900 48L970 6L1040 48L1110 16L1200 48Z"/>
</svg>
