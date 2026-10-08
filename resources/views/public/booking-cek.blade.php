@extends('layouts.public')

@section('title', 'Lengkapi pemesanan '.$unitType->name.' - '.config('site.name'))
@section('noindex', '1')

@php
    $checkInDate = \Carbon\CarbonImmutable::parse($stay['check_in'])->locale('id');
    $checkOutDate = \Carbon\CarbonImmutable::parse($stay['check_out'])->locale('id');
    $rupiah = fn (int $amount) => 'Rp '.number_format($amount, 0, ',', '.');
    $maxUnits = min($availableUnits->count(), config('booking.max_units_per_booking'));
    $maxAddon = config('booking.max_addon_quantity');
    $maxFood = config('booking.max_preorder_quantity');
@endphp

@section('content')
    <div class="mx-auto max-w-6xl px-4 py-10 sm:px-6 lg:py-14">
        <p class="eyebrow text-ember-dark">Langkah 2 dari 3</p>
        <h1 class="font-display mt-2 text-3xl font-semibold text-forest-900 sm:text-4xl">Lengkapi pemesanan</h1>
        <p class="mt-2 max-w-prose text-ink-soft">
            {{ $unitType->name }}, {{ $checkInDate->translatedFormat('j F') }} sampai {{ $checkOutDate->translatedFormat('j F Y') }}
            ({{ $nights }} malam). Unit ditahan {{ config('booking.hold_minutes') }} menit setelah Anda melanjutkan ke pembayaran.
        </p>

        @if(session('error'))
            <p class="mt-6 rounded-2xl bg-ember-soft px-5 py-4 font-semibold text-ember-dark" role="alert">{{ session('error') }}</p>
        @endif

        @if($errors->any())
            <div class="mt-6 rounded-2xl bg-ember-soft px-5 py-4 text-sm text-ember-dark" role="alert">
                <p class="font-bold">Mohon periksa kembali:</p>
                <ul class="mt-1 list-disc space-y-0.5 pl-5">
                    @foreach($errors->unique() as $message)<li>{{ $message }}</li>@endforeach
                </ul>
            </div>
        @endif

        @if($isAvailable)
            <form action="{{ route('booking.store') }}" method="POST" id="booking-form"
                  data-nights="{{ $nights }}" data-unit-price="{{ $stayPrice }}" data-tax-rate="{{ $taxRate }}"
                  class="mt-8 grid gap-8 lg:grid-cols-[1fr_21rem]">
                @csrf
                <input type="hidden" name="unit_type_id" value="{{ $unitType->id }}">
                <input type="hidden" name="check_in" value="{{ $stay['check_in'] }}">
                <input type="hidden" name="check_out" value="{{ $stay['check_out'] }}">

                <div class="space-y-6">
                    <section class="rounded-3xl border border-sand bg-[#fffdf8] p-6 sm:p-8" aria-labelledby="sec-unit">
                        <h2 id="sec-unit" class="font-display text-xl font-semibold text-forest-900">Unit dan tamu</h2>
                        <p class="mt-1 text-sm text-ink-soft">Tersedia {{ $availableUnits->count() }} unit {{ $unitType->name }}, muat {{ $unitType->capacity }} orang per unit.</p>

                        <div class="mt-5 flex flex-wrap items-end gap-x-10 gap-y-5">
                            <div>
                                <span class="field-label" id="lbl-qty">Jumlah unit</span>
                                <x-stepper name="quantity" label="unit" :value="old('quantity', 1)" :min="1" :max="$maxUnits" role="units" />
                            </div>
                            <div class="w-40">
                                <label for="guests" class="field-label">Jumlah tamu</label>
                                <input id="guests" type="number" name="guests" min="1" max="50" value="{{ old('guests', $stay['guests']) }}" required class="field-input">
                            </div>
                        </div>
                    </section>

                    <section class="rounded-3xl border border-sand bg-[#fffdf8] p-6 sm:p-8" aria-labelledby="sec-data">
                        <h2 id="sec-data" class="font-display text-xl font-semibold text-forest-900">Data pemesan</h2>
                        <div class="mt-5 space-y-4">
                            <div>
                                <label for="customer_name" class="field-label">Nama lengkap</label>
                                <input id="customer_name" type="text" name="customer_name" value="{{ old('customer_name') }}" autocomplete="name" required class="field-input">
                            </div>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label for="customer_phone" class="field-label">Nomor WhatsApp</label>
                                    <input id="customer_phone" type="tel" name="customer_phone" value="{{ old('customer_phone') }}" autocomplete="tel" inputmode="tel" placeholder="0812 3456 7890" required class="field-input">
                                </div>
                                <div>
                                    <label for="customer_email" class="field-label">Email</label>
                                    <input id="customer_email" type="email" name="customer_email" value="{{ old('customer_email') }}" autocomplete="email" required class="field-input">
                                </div>
                            </div>
                            <div>
                                <label for="notes" class="field-label">Catatan (opsional)</label>
                                <textarea id="notes" name="notes" rows="3" maxlength="500" class="field-input" placeholder="Perkiraan jam tiba, alergi makanan, permintaan khusus">{{ old('notes') }}</textarea>
                            </div>
                        </div>
                    </section>

                    @if($addons->isNotEmpty())
                        <section class="rounded-3xl border border-sand bg-[#fffdf8] p-6 sm:p-8" aria-labelledby="sec-addon">
                            <h2 id="sec-addon" class="font-display text-xl font-semibold text-forest-900">Tambahan</h2>
                            <ul class="mt-4 divide-y divide-sand">
                                @foreach($addons as $addon)
                                    <li class="flex flex-wrap items-center justify-between gap-4 py-4 first:pt-0 last:pb-0">
                                        <div>
                                            <p class="font-bold text-forest-900" id="addon-{{ $addon->id }}">{{ $addon->name }}</p>
                                            <p class="text-sm text-ink-soft">
                                                {{ $rupiah($addon->price) }} {{ $addon->unit }}@if($addon->isPerNight()), dihitung {{ $nights }} malam @endif
                                            </p>
                                        </div>
                                        <x-stepper :name="'addons['.$addon->id.']'" :label="$addon->name" :value="old('addons.'.$addon->id, 0)" :min="0" :max="$maxAddon" :price="$addon->price" :per-night="$addon->isPerNight()" role="addon" />
                                    </li>
                                @endforeach
                            </ul>
                        </section>
                    @endif

                    @if($menuCategories->isNotEmpty())
                        <details class="group rounded-3xl border border-sand bg-[#fffdf8]" id="preorder" @if(old('preorder')) open @endif>
                            <summary class="flex cursor-pointer list-none items-center justify-between gap-4 rounded-3xl p-6 sm:p-8 [&::-webkit-details-marker]:hidden">
                                <span>
                                    <span class="font-display block text-xl font-semibold text-forest-900">Pesan makanan dan minuman dari sekarang</span>
                                    <span class="mt-1 block text-sm text-ink-soft">Opsional. Pilih menu dan jam penyajian, dapur menyiapkannya saat Anda tiba.</span>
                                </span>
                                <svg class="size-5 shrink-0 text-forest-800 transition group-open:rotate-180" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                            </summary>

                            <div class="space-y-8 border-t border-sand px-6 pb-8 pt-6 sm:px-8">
                                @foreach($menuCategories as $category)
                                    <div>
                                        <h3 class="eyebrow text-sand-dark">{{ $category->name }}</h3>
                                        <ul class="mt-3 divide-y divide-sand">
                                            @foreach($category->items as $item)
                                                @php $chosen = (int) old('preorder.'.$item->id.'.qty', 0); @endphp
                                                <li class="py-4" data-menu-row>
                                                    <div class="flex flex-wrap items-center justify-between gap-4">
                                                        <div class="min-w-0 max-w-md">
                                                            <p class="font-bold text-forest-900">{{ $item->name }}</p>
                                                            @if($item->description)<p class="text-sm text-ink-soft">{{ $item->description }}</p>@endif
                                                            <p class="mt-1 text-sm font-bold text-ember-dark">{{ $rupiah($item->price) }}</p>
                                                        </div>
                                                        <x-stepper :name="'preorder['.$item->id.'][qty]'" :label="$item->name" :value="$chosen" :min="0" :max="$maxFood" :price="$item->price" role="food" />
                                                    </div>
                                                    <div class="mt-3 grid max-w-md grid-cols-2 gap-3" data-serve @if($chosen < 1) hidden @endif>
                                                        <div>
                                                            <label for="sd-{{ $item->id }}" class="field-label">Tanggal saji</label>
                                                            <select id="sd-{{ $item->id }}" name="preorder[{{ $item->id }}][serve_date]" class="field-input">
                                                                @foreach($serveDates as $date)
                                                                    <option value="{{ $date->toDateString() }}" @selected(old('preorder.'.$item->id.'.serve_date', $serveDates->first()->toDateString()) === $date->toDateString())>{{ $date->locale('id')->translatedFormat('D, j M') }}</option>
                                                                @endforeach
                                                            </select>
                                                        </div>
                                                        <div>
                                                            <label for="st-{{ $item->id }}" class="field-label">Jam saji</label>
                                                            <select id="st-{{ $item->id }}" name="preorder[{{ $item->id }}][serve_time]" class="field-input">
                                                                @foreach($serveTimes as $time)
                                                                    <option value="{{ $time }}" @selected(old('preorder.'.$item->id.'.serve_time', '12:00') === $time)>{{ $time }} WIB</option>
                                                                @endforeach
                                                            </select>
                                                        </div>
                                                    </div>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endforeach
                            </div>
                        </details>
                    @endif
                </div>

                <aside class="h-fit rounded-3xl bg-forest-900 p-6 text-cream lg:sticky lg:top-24" aria-label="Perkiraan biaya">
                    <h2 class="font-display text-xl font-semibold">Perkiraan biaya</h2>
                    <p class="mt-1 text-xs text-forest-300">Perkiraan sementara. Total akhir dihitung ulang oleh server saat pesanan dibuat.</p>

                    <dl class="mt-5 space-y-3 text-sm">
                        <div class="flex justify-between gap-4"><dt class="text-forest-300" data-est-label="units">Unit</dt><dd class="font-bold" data-est="units">-</dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-forest-300">Tambahan</dt><dd class="font-bold" data-est="addons">-</dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-forest-300">Makanan dan minuman</dt><dd class="font-bold" data-est="food">-</dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-forest-300">Pajak ({{ rtrim(rtrim(number_format($taxRate * 100, 2, ',', ''), '0'), ',') }}%)</dt><dd class="font-bold" data-est="tax">-</dd></div>
                        <div class="flex justify-between gap-4 border-t border-forest-700 pt-3 text-base"><dt>Estimasi total</dt><dd class="font-bold" data-est="total" aria-live="polite">-</dd></div>
                    </dl>

                    <button type="submit" class="btn btn-primary mt-6 w-full">Lanjut ke pembayaran</button>
                </aside>
            </form>

        @else
            <div class="mx-auto mt-10 max-w-lg rounded-3xl border border-sand bg-[#fffdf8] p-10 text-center">
                <h2 class="font-display text-2xl font-semibold text-forest-900">Tenda sudah penuh</h2>
                <p class="mt-3 text-ink-soft">Tidak ada tenda <strong>{{ $unitType->name }}</strong> yang tersedia di tanggal ini. Coba tipe lain atau ubah tanggal.</p>
                <a href="{{ route('home') }}#cari" class="btn btn-primary mt-6">Cari tanggal lain</a>
            </div>
        @endif
    </div>
@endsection
