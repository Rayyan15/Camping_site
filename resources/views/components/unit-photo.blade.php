@props(['unitType', 'imgClass' => 'size-full object-cover', 'minHeight' => '', 'priority' => false])

@php($photo = $unitType->photos->sortBy('sort_order')->first())

@if($photo)
    <img src="{{ $photo->url }}" alt="{{ $unitType->name }} tenda, foto 1" width="1600" height="1067" @if($priority) fetchpriority="high" @else loading="lazy" @endif decoding="async" {{ $attributes->merge(['class' => $imgClass.' aspect-[3/2] '.$minHeight]) }}>
@else
    <div role="img" aria-label="Foto tenda {{ $unitType->name }} belum tersedia" {{ $attributes->merge(['class' => 'flex h-full w-full flex-col items-start justify-between bg-forest-100 p-6 text-forest-800 '.$minHeight]) }}>
        <svg class="size-6 text-forest-700" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 20.25h18M12 3.75 4.5 20.25M12 3.75l7.5 16.5M12 3.75v3m0 13.5-2.25-5.25h4.5L12 20.25Z"/></svg>
        <span class="font-display text-3xl font-semibold leading-tight text-forest-900">{{ $unitType->name }}</span>
    </div>
@endif
