@extends('layouts.public')

@section('title', 'Pembayaran '.$booking->code.' - '.config('site.name'))
@section('noindex', '1')

@php
    $rupiah = fn (int $amount) => 'Rp '.number_format($amount, 0, ',', '.');
    $nights = $booking->check_in->diffInDays($booking->check_out);
    $checkIn = $booking->check_in->locale('id');
    $checkOut = $booking->check_out->locale('id');
    $remaining = $holdActive ? max(0, (int) now()->diffInSeconds($booking->hold_expires_at, false)) : 0;
    $downPaid = $booking->paid_amount > 0;
@endphp

@section('content')
    <div class="mx-auto max-w-5xl px-4 py-10 sm:px-6 lg:py-14">
        <p class="eyebrow text-ember-dark">Langkah 3 dari 3</p>
        <h1 class="font-display mt-2 text-3xl font-semibold text-forest-900 sm:text-4xl">Pembayaran</h1>
        <p class="mt-2 text-ink-soft">Kode booking <strong class="font-mono tracking-wider text-forest-900">{{ $booking->code }}</strong>. Alamat halaman ini juga berlaku sebagai tautan status pesanan, jadi simpan atau bagikan hanya kepada orang yang Anda percaya.</p>

        @if(session('error'))
            <p class="mt-6 rounded-2xl bg-ember-soft px-5 py-4 font-semibold text-ember-dark" role="alert">{{ session('error') }}</p>
        @endif

        @unless($holdActive)
            <div class="mx-auto mt-10 max-w-lg rounded-3xl border border-sand bg-[#fffdf8] p-10 text-center" role="status">
                <h2 class="font-display text-2xl font-semibold text-forest-900">Waktu pembayaran habis</h2>
                <p class="mt-3 text-ink-soft">Tenda yang Anda pilih sudah dilepas agar bisa dipesan tamu lain. Cari tanggal lagi untuk membuat pesanan baru.</p>
                <a href="{{ route('home') }}#cari" class="btn btn-primary mt-6">Cari tanggal lagi</a>
            </div>
        @else
            <div class="mt-8 grid gap-8 lg:grid-cols-[1fr_20rem]">
                <section class="rounded-3xl border border-sand bg-[#fffdf8] p-6 sm:p-8" aria-labelledby="sum-title">
                    <h2 id="sum-title" class="font-display text-xl font-semibold text-forest-900">Rincian pesanan</h2>
                    <p class="mt-1 text-sm text-ink-soft">
                        {{ $checkIn->translatedFormat('j F') }} sampai {{ $checkOut->translatedFormat('j F Y') }}, {{ $nights }} malam, {{ $booking->guests }} tamu, atas nama {{ $booking->customer->name }}.
                    </p>

                    <table class="mt-6 w-full text-sm">
                        <caption class="sr-only">Rincian biaya</caption>
                        <tbody class="divide-y divide-sand">
                            @foreach($booking->bookingUnits as $line)
                                <tr>
                                    <td class="py-3 pr-4">
                                        <span class="font-bold text-forest-900">{{ $line->unit->unitType->name }}</span>
                                        <span class="block text-ink-soft">Unit {{ $line->unit->code }}, {{ $line->nights }} malam</span>
                                    </td>
                                    <td class="py-3 text-right font-bold text-forest-900">{{ $rupiah($line->subtotal) }}</td>
                                </tr>
                            @endforeach

                            @foreach($booking->addons as $line)
                                <tr>
                                    <td class="py-3 pr-4">
                                        <span class="font-bold text-forest-900">{{ $line->addon->name }}</span>
                                        <span class="block text-ink-soft">
                                            {{ $line->qty }} x {{ $rupiah($line->price) }}@if($line->addon->isPerNight()) x {{ $nights }} malam @endif
                                        </span>
                                    </td>
                                    <td class="py-3 text-right font-bold text-forest-900">{{ $rupiah($line->subtotal) }}</td>
                                </tr>
                            @endforeach

                            @foreach($booking->orders as $order)
                                @foreach($order->items as $line)
                                    <tr>
                                        <td class="py-3 pr-4">
                                            <span class="font-bold text-forest-900">{{ $line->menuItem->name }}</span>
                                            <span class="block text-ink-soft">
                                                {{ $line->qty }} x {{ $rupiah($line->price) }}, disajikan {{ $order->scheduled_at->locale('id')->translatedFormat('j M, H:i') }} WIB
                                            </span>
                                        </td>
                                        <td class="py-3 text-right font-bold text-forest-900">{{ $rupiah($line->qty * $line->price) }}</td>
                                    </tr>
                                @endforeach
                            @endforeach
                        </tbody>
                        <tfoot class="border-t-2 border-forest-800">
                            <tr><td class="pt-4 text-ink-soft">Subtotal</td><td class="pt-4 text-right">{{ $rupiah($booking->subtotal) }}</td></tr>
                            <tr><td class="py-1 text-ink-soft">Pajak</td><td class="py-1 text-right">{{ $rupiah($booking->tax) }}</td></tr>
                            <tr class="text-base"><td class="pt-2 font-bold text-forest-900">Total</td><td class="pt-2 text-right font-bold text-forest-900">{{ $rupiah($booking->total) }}</td></tr>
                        </tfoot>
                    </table>
                </section>

                <aside class="h-fit rounded-3xl bg-forest-900 p-6 text-cream lg:sticky lg:top-24" aria-label="Pembayaran">
                    @if($downPaid)
                        <p class="text-sm text-forest-300">DP diterima</p>
                        <p class="text-2xl font-bold">{{ $rupiah($booking->paid_amount) }}</p>
                        <p class="mt-2 text-sm text-forest-300">Tenda Anda dipegang sampai {{ $booking->hold_expires_at->locale('id')->translatedFormat('j F Y, H:i') }} WIB. Lunasi sisanya sebelum itu, atau saat check-in.</p>

                        <p class="mt-6 text-sm text-forest-300">Sisa tagihan</p>
                        <p class="text-2xl font-bold">{{ $rupiah($outstanding) }}</p>
                    @else
                        <p class="text-sm text-forest-300">Selesaikan pembayaran dalam</p>
                        <p id="countdown" class="font-display mt-1 text-5xl font-semibold tabular-nums" data-remaining="{{ $remaining }}" role="timer">--:--</p>
                        <p class="mt-2 text-sm text-forest-300">Setelah waktu habis, tenda dilepas otomatis.</p>

                        <p class="mt-6 text-sm text-forest-300">Total bayar</p>
                        <p class="text-2xl font-bold">{{ $rupiah($outstanding) }}</p>
                    @endif

                    <form action="{{ route('checkout.pay', $booking->access_token) }}" method="POST" class="mt-6">
                        @csrf
                        @if($downPayment !== null)
                            <fieldset class="mb-5 grid gap-2">
                                <legend class="mb-2 text-sm text-forest-300">Cara bayar</legend>
                                <label class="flex min-h-14 cursor-pointer items-center gap-3 rounded-xl border-[1.5px] border-forest-700 px-4 py-3 has-[:checked]:border-cream has-[:checked]:bg-forest-800">
                                    <input type="radio" name="plan" value="full" checked class="size-5 accent-ember">
                                    <span><span class="block font-semibold">Bayar penuh</span><span class="block text-sm text-forest-300">{{ $rupiah($outstanding) }}</span></span>
                                </label>
                                <label class="flex min-h-14 cursor-pointer items-center gap-3 rounded-xl border-[1.5px] border-forest-700 px-4 py-3 has-[:checked]:border-cream has-[:checked]:bg-forest-800">
                                    <input type="radio" name="plan" value="down_payment" class="size-5 accent-ember">
                                    <span><span class="block font-semibold">Bayar DP dulu</span><span class="block text-sm text-forest-300">{{ $rupiah($downPayment) }} sekarang, sisa {{ $rupiah($outstanding - $downPayment) }} paling lambat saat check-in</span></span>
                                </label>
                            </fieldset>
                        @endif
                        <button type="submit" class="btn btn-primary w-full">{{ $downPaid ? 'Lunasi sekarang' : 'Bayar sekarang' }}</button>
                    </form>
                </aside>
            </div>

        @endunless
    </div>
@endsection
