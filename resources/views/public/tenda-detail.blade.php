@extends('layouts.public')

@php
    $photos = $unitType->photos->sortBy('sort_order')->values();
    $formatRupiah = fn (int $amount) => 'Rp '.number_format($amount, 0, ',', '.');
    $breadcrumbData = [
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => config('site.name'), 'item' => route('home')],
            ['@type' => 'ListItem', 'position' => 2, 'name' => 'Tenda', 'item' => route('home').'#tenda'],
            ['@type' => 'ListItem', 'position' => 3, 'name' => $unitType->name, 'item' => route('tenda.show', ['slug' => $unitType->slug])],
        ],
    ];
@endphp

@section('title', $unitType->name.' - '.config('site.name'))
@section('description', 'Tenda '.$unitType->name.' untuk hingga '.$unitType->capacity.' orang. Cek ketersediaan dan pesan online.')

@section('og_image', $photos->first()?->url ?? '')

@push('head')
    <script type="application/ld+json">{!! json_encode($breadcrumbData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
@endpush

@section('content')
    {{-- Petang: the visitor has picked a tent and is deciding on a night. Same dusk tint and tent-peak edge as the landing. --}}
    <div class="relative bg-dusk-100 pb-32 pt-8 text-forest-900 sm:pb-40 sm:pt-10">
        <div class="mx-auto max-w-6xl px-4 sm:px-6">
            <nav aria-label="Jejak halaman">
                <ol class="flex flex-wrap items-center gap-x-2 text-sm font-semibold text-ink-soft">
                    <li><a href="{{ route('home') }}" class="inline-flex min-h-11 items-center underline-offset-4 hover:text-forest-900 hover:underline">Beranda</a></li>
                    <li aria-hidden="true">/</li>
                    <li><a href="{{ route('home') }}#tenda" class="inline-flex min-h-11 items-center underline-offset-4 hover:text-forest-900 hover:underline">Semua tenda</a></li>
                    <li aria-hidden="true">/</li>
                    <li aria-current="page" class="text-forest-900">{{ $unitType->name }}</li>
                </ol>
            </nav>

            <header class="mt-4 grid gap-8 lg:grid-cols-[1.4fr_1fr] lg:items-end">
                <div>
                    <p class="font-display text-xl italic text-ember-dark">Petang</p>
                    <h1 class="font-display mt-2 text-[clamp(3.5rem,11vw,8rem)] font-semibold leading-[0.95]">{{ $unitType->name }}</h1>
                </div>
                <div>
                    <dl class="grid grid-cols-2 gap-x-6 gap-y-4 border-t border-forest-900/25 pt-4">
                        <div>
                            <dt class="text-xs font-semibold text-ink-soft">Weekday, per malam</dt>
                            <dd class="font-display text-3xl font-semibold">{{ $formatRupiah($unitType->base_price_weekday) }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold text-ink-soft">Weekend, per malam</dt>
                            <dd class="font-display text-3xl font-semibold">{{ $formatRupiah($unitType->base_price_weekend) }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold text-ink-soft">Kapasitas</dt>
                            <dd class="text-lg font-bold">Hingga {{ $unitType->capacity }} orang</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold text-ink-soft">Jumlah unit</dt>
                            <dd class="text-lg font-bold">{{ $unitType->units_count }} unit</dd>
                        </div>
                    </dl>
                    <a href="#ketersediaan" class="btn btn-primary mt-6 w-full sm:w-auto">Cek ketersediaan</a>
                </div>
            </header>

            <div class="mt-10">
                @if($photos->isEmpty())
                    <div class="overflow-hidden rounded-3xl border border-sand">
                        <x-unit-photo :unit-type="$unitType" minHeight="min-h-[22rem] lg:min-h-[32rem]" />
                    </div>
                @else
                    <div data-gallery class="grid gap-3 @if($photos->count() > 1) lg:grid-cols-[1fr_9rem] @endif">
                        <div class="aspect-[3/2] overflow-hidden rounded-3xl bg-forest-100 lg:aspect-[16/10]">
                            <img data-gallery-main src="{{ $photos->first()->url }}" alt="{{ $unitType->name }} tenda, foto 1" width="1600" height="1067" fetchpriority="high" decoding="async" class="size-full object-cover">
                        </div>
                        @if($photos->count() > 1)
                            <ul class="grid grid-cols-4 gap-3 sm:grid-cols-5 lg:grid-cols-1 lg:content-start" aria-label="Foto tenda {{ $unitType->name }}">
                                @foreach($photos as $photo)
                                    <li>
                                        <a href="{{ $photo->url }}" data-gallery-thumb data-alt="{{ $unitType->name }} tenda, foto {{ $loop->iteration }}" @if($loop->first) aria-current="true" @endif aria-label="Lihat foto {{ $loop->iteration }} dari {{ $photos->count() }}" class="block aspect-[3/2] overflow-hidden rounded-2xl border-2 border-transparent opacity-70 transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ember aria-[current=true]:border-ember aria-[current=true]:opacity-100 hover:opacity-100">
                                            <img src="{{ $photo->url }}" alt="" width="400" height="267" loading="lazy" decoding="async" class="size-full object-cover">
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                @endif
            </div>

            <div class="mt-16 grid gap-10 lg:grid-cols-[1fr_1.4fr] lg:gap-16" data-reveal>
                <div>
                    <h2 class="font-display text-3xl font-semibold sm:text-4xl">Tentang tenda ini</h2>
                    @if($unitType->description)
                        <p class="mt-4 max-w-prose leading-relaxed text-ink-soft">{{ $unitType->description }}</p>
                    @else
                        <p class="mt-4 max-w-prose leading-relaxed text-ink-soft">Deskripsi tipe ini belum ditulis pengelola. Tanyakan ke pengelola bila ada yang ingin Anda pastikan.</p>
                    @endif
                </div>
                <div>
                    <h2 class="font-display text-3xl font-semibold sm:text-4xl">Fasilitas</h2>
                    @if(! empty($unitType->facilities))
                        <ul class="mt-4 grid gap-x-8 sm:grid-cols-2">
                            @foreach($unitType->facilities as $facility)
                                <li class="flex items-center gap-3 border-b border-forest-900/20 py-3 font-semibold">
                                    <svg class="size-4 shrink-0 text-ember-dark" fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                                    {{ $facility }}
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="mt-4 text-ink-soft">Daftar fasilitas tipe ini belum diisi pengelola.</p>
                    @endif
                </div>
            </div>

            @include('public.partials.tenda-availability')
        </div>

        @include('public.partials.tenda-other-types')

        <x-ridge class="text-forest-950" />
    </div>
@endsection
