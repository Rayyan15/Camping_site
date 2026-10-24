@extends('layouts.public')

@php
    $formatRupiah = fn (int $amount) => 'Rp '.number_format($amount, 0, ',', '.');
    $sortOptions = [
        'termurah' => __('Termurah'),
        'termahal' => __('Termahal'),
        'kapasitas' => __('Paling lega'),
    ];
    $isFiltered = $filters['tamu'] || $filters['fasilitas'] !== [] || $filters['urutkan'] !== 'termurah';
@endphp

@section('title', __('Katalog tenda').' - '.config('site.name'))
@section('description', __('Semua tipe tenda :site dengan kapasitas, fasilitas, dan harga per malam. Saring sesuai rombongan Anda.', ['site' => config('site.name')]))

@section('content')
    <div class="relative bg-dusk-100 pb-32 pt-8 text-forest-900 sm:pb-40 sm:pt-10">
        <div class="mx-auto max-w-6xl px-4 sm:px-6">
            <nav aria-label="{{ __('Jejak halaman') }}">
                <ol class="flex flex-wrap items-center gap-x-2 text-sm font-semibold text-ink-soft">
                    <li><a href="{{ route('home') }}" class="inline-flex min-h-11 items-center underline-offset-4 hover:text-forest-900 hover:underline">{{ __('Beranda') }}</a></li>
                    <li aria-hidden="true">/</li>
                    <li aria-current="page" class="text-forest-900">{{ __('Katalog tenda') }}</li>
                </ol>
            </nav>

            <header class="mt-4 max-w-3xl">
                <p class="font-display text-xl italic text-ember-dark">{{ __('Katalog') }}</p>
                <h1 class="font-display mt-2 text-4xl font-semibold leading-[1.05] sm:text-6xl">{{ __('Semua tenda, berdampingan.') }}</h1>
                <p class="mt-4 max-w-prose text-ink-soft">{{ __('Bandingkan kapasitas, fasilitas, dan harga per malam. Pilih tanggal di halaman tenda untuk melihat unit yang masih kosong.') }}</p>
            </header>

            <form method="GET" action="{{ route('tenda.index') }}" class="mt-8 rounded-3xl border border-sand bg-[#fffdf8] p-5 sm:p-6" aria-label="{{ __('Saring tenda') }}">
                <div class="grid gap-6 lg:grid-cols-[12rem_1fr_auto] lg:items-end">
                    <div>
                        <label for="tamu" class="field-label">{{ __('Jumlah tamu') }}</label>
                        <select id="tamu" name="tamu" class="field-input">
                            <option value="">{{ __('Berapa saja') }}</option>
                            @foreach(range(1, 12) as $count)
                                <option value="{{ $count }}" @selected($filters['tamu'] === $count)>{{ $count }} {{ __('orang') }}</option>
                            @endforeach
                        </select>
                    </div>

                    <fieldset>
                        <legend class="field-label">{{ __('Urutkan') }}</legend>
                        <div class="flex flex-wrap gap-2">
                            @foreach($sortOptions as $value => $label)
                                <label class="cursor-pointer">
                                    <input type="radio" name="urutkan" value="{{ $value }}" class="peer sr-only" @checked($filters['urutkan'] === $value)>
                                    <span class="inline-flex min-h-11 items-center rounded-full border border-sand px-4 text-sm font-semibold text-forest-900 transition peer-checked:border-forest-800 peer-checked:bg-forest-800 peer-checked:text-cream peer-focus-visible:outline peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-forest-800 hover:bg-forest-100 peer-checked:hover:bg-forest-800">{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>

                    <div class="flex items-center gap-3">
                        <button type="submit" class="btn btn-primary">{{ __('Terapkan') }}</button>
                        @if($isFiltered)
                            <a href="{{ route('tenda.index') }}" class="inline-flex min-h-11 items-center text-sm font-semibold text-ink-soft underline underline-offset-4 hover:text-forest-900">{{ __('Hapus filter') }}</a>
                        @endif
                    </div>
                </div>

                @if($facilityOptions->isNotEmpty())
                    <fieldset class="mt-6 border-t border-sand pt-5">
                        <legend class="field-label">{{ __('Harus ada fasilitas') }}</legend>
                        <div class="flex flex-wrap gap-2">
                            @foreach($facilityOptions as $facility)
                                <label class="cursor-pointer">
                                    <input type="checkbox" name="fasilitas[]" value="{{ $facility }}" class="peer sr-only" @checked(in_array($facility, $filters['fasilitas'], true))>
                                    <span class="inline-flex min-h-11 items-center gap-2 rounded-full border border-sand bg-cream px-4 text-sm font-semibold text-forest-900 transition peer-checked:border-ember-dark peer-checked:bg-ember-soft peer-checked:text-ember-dark peer-focus-visible:outline peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-forest-800 hover:bg-forest-100">{{ $facility }}</span>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                @endif
            </form>

            <p class="mt-8 text-sm font-semibold text-ink-soft" role="status">{{ __('Menampilkan :shown dari :total tipe tenda', ['shown' => $unitTypes->count(), 'total' => $totalTypes]) }}</p>

            @if($unitTypes->isEmpty())
                <div class="mt-4 rounded-3xl border border-dashed border-sand-dark bg-cream p-8 text-center">
                    <p class="font-display text-2xl font-semibold">{{ __('Belum ada tenda yang cocok.') }}</p>
                    <p class="mt-2 text-ink-soft">{{ __('Kurangi pilihan fasilitas atau jumlah tamu. Rombongan besar bisa memesan beberapa unit sekaligus.') }}</p>
                    <a href="{{ route('tenda.index') }}" class="btn btn-outline mt-5">{{ __('Tampilkan semua tenda') }}</a>
                </div>
            @else
                <ul class="mt-4 space-y-6">
                    @foreach($unitTypes as $type)
                        @php $photo = $type->photos->sortBy('sort_order')->first(); @endphp
                        <li class="group relative grid overflow-hidden rounded-3xl border border-sand bg-[#fffdf8] md:grid-cols-[minmax(0,2fr)_minmax(0,3fr)]">
                            <div class="relative aspect-[4/3] bg-forest-800 md:aspect-auto md:min-h-72">
                                @if($photo)
                                    <img src="{{ $photo->url }}" alt="{{ __('Tenda tipe :name', ['name' => $type->name]) }}" width="1600" height="1067" loading="lazy" decoding="async" class="absolute inset-0 size-full object-cover transition duration-500 group-hover:scale-[1.03] motion-reduce:transition-none motion-reduce:group-hover:scale-100">
                                @endif
                            </div>
                            <div class="flex flex-col p-6 sm:p-8">
                                <h2 class="font-display text-3xl font-semibold">
                                    <a href="{{ route('tenda.show', ['slug' => $type->slug]) }}" class="after:absolute after:inset-0 after:content-[''] focus-visible:outline-none group-focus-within:underline">{{ $type->name }}</a>
                                </h2>
                                <p class="mt-1 text-sm font-semibold text-ink-soft">{{ __('Hingga :count orang', ['count' => $type->capacity]) }}<span aria-hidden="true"> &middot; </span><span class="sr-only">, </span>{{ trans_choice(':count unit', $type->units_count) }}</p>
                                @if($type->description)
                                    <p class="mt-3 max-w-prose text-ink-soft">{{ \Illuminate\Support\Str::limit($type->description, 180) }}</p>
                                @endif
                                @if(! empty($type->facilities))
                                    <ul class="mt-4 flex flex-wrap gap-2" aria-label="{{ __('Fasilitas :name', ['name' => $type->name]) }}">
                                        @foreach($type->facilities as $facility)
                                            <li class="rounded-full bg-forest-100 px-3 py-1 text-xs font-semibold text-forest-900">{{ $facility }}</li>
                                        @endforeach
                                    </ul>
                                @endif
                                <div class="mt-6 flex flex-wrap items-end justify-between gap-4 border-t border-sand pt-5 md:mt-auto">
                                    <dl class="flex items-end gap-6">
                                        <div>
                                            <dt class="text-xs font-semibold text-ink-soft">{{ __('Weekday, per malam') }}</dt>
                                            <dd class="font-display text-2xl font-semibold">{{ $formatRupiah($type->base_price_weekday) }}</dd>
                                        </div>
                                        <div>
                                            <dt class="text-xs font-semibold text-ink-soft">{{ __('Weekend') }}</dt>
                                            <dd class="font-semibold">{{ $formatRupiah($type->base_price_weekend) }}</dd>
                                        </div>
                                    </dl>
                                    <span class="inline-flex items-center gap-1 text-sm font-bold text-ember-dark" aria-hidden="true">
                                        {{ __('Pilih tanggal') }}
                                        <svg class="size-4 transition group-hover:translate-x-0.5 motion-reduce:transition-none" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m9 18 6-6-6-6"/></svg>
                                    </span>
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
        <x-ridge class="text-forest-950" />
    </div>
@endsection
