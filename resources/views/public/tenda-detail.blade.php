@extends('layouts.public')

@section('title', $unitType->name.' - '.config('site.name'))
@section('description', 'Tenda '.$unitType->name.' untuk hingga '.$unitType->capacity.' orang. Cek ketersediaan dan pesan online.')

@section('og_image', optional($unitType->photos->sortBy('sort_order')->first())->url)

@section('content')
    <div class="mx-auto max-w-6xl px-4 py-10 sm:px-6 lg:py-14">
        <a href="{{ route('home') }}#tenda" class="inline-flex items-center gap-2 text-sm font-bold text-forest-800 hover:text-ember-dark">
            <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/></svg>
            Semua tipe tenda
        </a>

        <div class="mt-6 grid gap-10 lg:grid-cols-[1.1fr_0.9fr]">
            @php($photos = $unitType->photos->sortBy('sort_order')->values())
            @if($photos->isEmpty())
                <div class="overflow-hidden rounded-3xl border border-sand">
                    <x-unit-photo :unit-type="$unitType" minHeight="min-h-[22rem] lg:min-h-[32rem]" />
                </div>
            @else
                <div data-gallery>
                    <div class="aspect-[3/2] overflow-hidden rounded-3xl border border-sand bg-forest-100">
                        <img data-gallery-main src="{{ $photos->first()->url }}" alt="{{ $unitType->name }} tenda, foto 1" width="1600" height="1067" fetchpriority="high" decoding="async" class="size-full object-cover">
                    </div>
                    @if($photos->count() > 1)
                        <ul class="mt-3 grid grid-cols-3 gap-3" aria-label="Foto tenda {{ $unitType->name }}">
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

            <div>
                <p class="eyebrow text-ember-dark">Hingga {{ $unitType->capacity }} orang</p>
                <h1 class="font-display mt-2 text-4xl font-semibold text-forest-900 sm:text-5xl">{{ $unitType->name }}</h1>

                <dl class="mt-6 grid grid-cols-2 gap-4 rounded-2xl bg-cream-deep p-5">
                    <div>
                        <dt class="text-xs font-semibold text-ink-soft">Weekday per malam</dt>
                        <dd class="text-xl font-bold text-forest-900">Rp {{ number_format($unitType->base_price_weekday, 0, ',', '.') }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold text-ink-soft">Weekend per malam</dt>
                        <dd class="text-xl font-bold text-forest-900">Rp {{ number_format($unitType->base_price_weekend, 0, ',', '.') }}</dd>
                    </div>
                </dl>

                @if($unitType->description)
                    <p class="mt-6 leading-relaxed text-ink-soft">{{ $unitType->description }}</p>
                @endif

                @if(! empty($unitType->facilities))
                    <h2 class="font-display mt-8 text-xl font-semibold text-forest-900">Fasilitas</h2>
                    <ul class="mt-3 grid grid-cols-2 gap-3">
                        @foreach($unitType->facilities as $facility)
                            <li class="flex items-center gap-2 text-sm font-semibold text-forest-900">
                                <svg class="size-4 shrink-0 text-ember" fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                                {{ $facility }}
                            </li>
                        @endforeach
                    </ul>
                @endif

                <form action="{{ route('booking.cek') }}" method="GET" class="mt-8 rounded-3xl border border-sand bg-[#fffdf8] p-5 sm:p-6">
                    <h2 class="font-display text-xl font-semibold text-forest-900">Cek ketersediaan</h2>
                    <input type="hidden" name="unit_type_id" value="{{ $unitType->id }}">

                    <div class="mt-4 grid grid-cols-2 gap-3">
                        <div>
                            <label for="check_in" class="field-label">Check-in</label>
                            <input id="check_in" type="date" name="check_in" value="{{ request('check_in', $today) }}" min="{{ $today }}" required class="field-input">
                        </div>
                        <div>
                            <label for="check_out" class="field-label">Check-out</label>
                            <input id="check_out" type="date" name="check_out" value="{{ request('check_out', $tomorrow) }}" min="{{ $tomorrow }}" required class="field-input">
                        </div>
                    </div>
                    <div class="mt-3">
                        <label for="guests" class="field-label">Jumlah tamu</label>
                        <input id="guests" type="number" name="guests" min="1" max="{{ $unitType->capacity + 2 }}" value="{{ request('guests', 2) }}" required aria-describedby="guests-hint" class="field-input">
                        <p id="guests-hint" class="mt-2 text-xs text-ink-soft">Kapasitas dasar {{ $unitType->capacity }} orang. Lebih dari itu perlu extra bed.</p>
                    </div>
                    @if($errors->any())
                        <ul class="mt-3 rounded-xl bg-ember-soft px-4 py-3 text-sm font-semibold text-ember-dark" role="alert">
                            @foreach($errors->unique() as $message)<li>{{ $message }}</li>@endforeach
                        </ul>
                    @endif
                    <button type="submit" class="btn btn-primary mt-5 w-full">Cek dan pesan</button>
                </form>
            </div>
        </div>
    </div>
@endsection
