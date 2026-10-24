@extends('layouts.public')

@section('title', __('Pembayaran').' '.$booking->code.' - '.config('site.name'))
@section('noindex', '1')

@php
    $rupiah = fn (int $amount) => 'Rp '.number_format($amount, 0, ',', '.');
    $nights = $booking->check_in->diffInDays($booking->check_out);
    $checkIn = $booking->check_in->locale(app()->getLocale());
    $checkOut = $booking->check_out->locale(app()->getLocale());
    $remaining = $holdActive ? max(0, (int) now()->diffInSeconds($booking->hold_expires_at, false)) : 0;
    $downPaid = $booking->paid_amount > 0;
@endphp

@section('content')
    <div class="mx-auto max-w-5xl px-4 py-10 sm:px-6 lg:py-14">
        <p class="eyebrow text-ember-dark">{{ __('Langkah 3 dari 3') }}</p>
        <h1 class="font-display mt-2 text-3xl font-semibold text-forest-900 sm:text-4xl">{{ __('Pembayaran') }}</h1>
        <p class="mt-2 text-ink-soft">{{ __('Kode booking') }} <strong class="font-mono tracking-wider text-forest-900">{{ $booking->code }}</strong>. {{ __('Alamat halaman ini juga berlaku sebagai tautan status pesanan, jadi simpan atau bagikan hanya kepada orang yang Anda percaya.') }}</p>

        @if(session('success'))
            <p class="mt-6 rounded-2xl bg-forest-100 px-5 py-4 font-semibold text-forest-900" role="status">{{ __(session('success')) }}</p>
        @endif
        @if(session('error'))
            <p class="mt-6 rounded-2xl bg-ember-soft px-5 py-4 font-semibold text-ember-dark" role="alert">{{ __(session('error')) }}</p>
        @endif

        @if($proof?->status === \App\Enums\PaymentStatus::Failed)
            <p class="mt-6 rounded-2xl bg-ember-soft px-5 py-4 font-semibold text-ember-dark" role="alert">{{ __('Bukti transfer ditolak: :reason. Silakan unggah ulang bukti yang benar.', ['reason' => $proof->rejection_reason]) }}</p>
        @endif

        @unless($holdActive)
            <div class="mx-auto mt-10 max-w-lg rounded-3xl border border-sand bg-[#fffdf8] p-10 text-center" role="status">
                <h2 class="font-display text-2xl font-semibold text-forest-900">{{ __('Waktu pembayaran habis') }}</h2>
                <p class="mt-3 text-ink-soft">{{ __('Tenda yang Anda pilih sudah dilepas agar bisa dipesan tamu lain. Cari tanggal lagi untuk membuat pesanan baru.') }}</p>
                <a href="{{ route('home') }}#cari" class="btn btn-primary mt-6">{{ __('Cari tanggal lagi') }}</a>
            </div>
        @else
            <div class="mt-8 grid items-start gap-8 lg:grid-cols-[1fr_20rem]">
                <section class="rounded-3xl border border-sand bg-[#fffdf8] p-6 sm:p-8" aria-labelledby="sum-title">
                    <h2 id="sum-title" class="font-display text-xl font-semibold text-forest-900">{{ __('Rincian pesanan') }}</h2>
                    <p class="mt-1 text-sm text-ink-soft">
                        {{ $checkIn->translatedFormat('j F') }} {{ __('sampai') }} {{ $checkOut->translatedFormat('j F Y') }}, {{ trans_choice(':count malam', $nights) }}, {{ trans_choice(':count tamu', $booking->guests) }}, {{ __('atas nama :name', ['name' => $booking->customer->name]) }}.
                    </p>

                    <table class="mt-6 w-full text-sm">
                        <caption class="sr-only">{{ __('Rincian biaya') }}</caption>
                        <tbody class="divide-y divide-sand">
                            @foreach($booking->bookingUnits as $line)
                                <tr>
                                    <td class="py-3 pr-4">
                                        <span class="font-bold text-forest-900">{{ $line->unit->unitType->name }}</span>
                                        <span class="block text-ink-soft">{{ __('Unit') }} {{ $line->unit->code }}, {{ trans_choice(':count malam', $line->nights) }}</span>
                                    </td>
                                    <td class="py-3 text-right font-bold text-forest-900">{{ $rupiah($line->subtotal) }}</td>
                                </tr>
                            @endforeach

                            @foreach($booking->addons as $line)
                                <tr>
                                    <td class="py-3 pr-4">
                                        <span class="font-bold text-forest-900">{{ $line->addon->name }}</span>
                                        <span class="block text-ink-soft">
                                            {{ $line->qty }} x {{ $rupiah($line->price) }}@if($line->addon->isPerNight()) x {{ trans_choice(':count malam', $nights) }} @endif
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
                                                {{ $line->qty }} x {{ $rupiah($line->price) }}, {{ __('disajikan :time WIB', ['time' => $order->scheduled_at->locale(app()->getLocale())->translatedFormat('j M, H:i')]) }}
                                            </span>
                                        </td>
                                        <td class="py-3 text-right font-bold text-forest-900">{{ $rupiah($line->qty * $line->price) }}</td>
                                    </tr>
                                @endforeach
                            @endforeach
                        </tbody>
                        <tfoot class="border-t-2 border-forest-800">
                            <tr><td class="pt-4 text-ink-soft">{{ __('Subtotal') }}</td><td class="pt-4 text-right">{{ $rupiah($booking->subtotal) }}</td></tr>
                            <tr class="text-base"><td class="pt-2 font-bold text-forest-900">{{ __('Total') }}</td><td class="pt-2 text-right font-bold text-forest-900">{{ $rupiah($booking->total) }}</td></tr>
                        </tfoot>
                    </table>
                </section>

                <aside class="h-fit rounded-3xl bg-forest-900 p-6 text-cream lg:sticky lg:top-24" aria-label="{{ __('Pembayaran') }}">
                    @if($proof?->status === \App\Enums\PaymentStatus::Pending)
                        <p class="font-display text-2xl font-semibold" role="status">{{ __('Bukti diterima, menunggu konfirmasi admin') }}</p>
                        <p class="mt-2 text-sm text-forest-300">{{ __('Tenda Anda dipegang sampai :time WIB. Kami mengirim email setelah pembayaran dikonfirmasi, jadi cek email Anda.', ['time' => $booking->hold_expires_at->locale(app()->getLocale())->translatedFormat('j F Y, H:i')]) }}</p>
                    @elseif($cashChosen)
                        <p class="font-display text-2xl font-semibold" role="status">{{ __('Bayar tunai saat tiba') }}</p>
                        <p class="mt-2 text-sm text-forest-300">{{ __('Tenda Anda dipegang sampai :time WIB. Siapkan uang pas dan tunjukkan kode booking di meja depan.', ['time' => $booking->hold_expires_at->locale(app()->getLocale())->translatedFormat('j F Y, H:i')]) }}</p>
                    @elseif($downPaid)
                        <p class="text-sm text-forest-300">{{ __('DP diterima') }}</p>
                        <p class="text-2xl font-bold">{{ $rupiah($booking->paid_amount) }}</p>
                        <p class="mt-2 text-sm text-forest-300">{{ __('Tenda Anda dipegang sampai :time WIB. Lunasi sisanya sebelum itu, atau saat check-in.', ['time' => $booking->hold_expires_at->locale(app()->getLocale())->translatedFormat('j F Y, H:i')]) }}</p>
                    @else
                        <p class="text-sm text-forest-300">{{ __('Selesaikan pembayaran dalam') }}</p>
                        <p id="countdown" class="font-display mt-1 text-5xl font-semibold tabular-nums" data-remaining="{{ $remaining }}" role="timer">--:--:--</p>
                        <p class="mt-2 text-sm text-forest-300">{{ __('Setelah waktu habis, tenda dilepas otomatis.') }}</p>
                    @endif

                    <p class="mt-6 text-sm text-forest-300">{{ $downPaid ? __('Sisa tagihan') : __('Total dibayar') }}</p>
                    <p class="text-2xl font-bold">{{ $rupiah($outstanding) }}</p>

                    @if(! $cashChosen)
                        @if($methods === [])
                            <p class="mt-6 rounded-xl border-[1.5px] border-forest-300 p-4 font-semibold" role="status">{{ __('Pembayaran belum bisa dilakukan di sini, hubungi admin.') }}</p>
                        @else
                            <h2 class="mt-6 text-sm font-semibold text-forest-300">{{ __('Pilih cara bayar') }}</h2>
                            <div class="mt-2 space-y-2">
                                @foreach($methods as $method)
                                    @php $isFirst = $loop->first; @endphp
                                    {{-- One shared name makes the panels an exclusive accordion without any script. --}}
                                    <details name="metode-bayar" class="group rounded-2xl bg-forest-800 open:bg-cream open:text-ink" @if($isFirst || old('method') === $method->value) open @endif>
                                        <summary class="flex min-h-12 cursor-pointer list-none items-center justify-between gap-3 rounded-2xl px-4 py-3 font-semibold [&::-webkit-details-marker]:hidden">
                                            {{ match ($method) { \App\Enums\PaymentMethod::Transfer => __('Transfer bank'), \App\Enums\PaymentMethod::Qris => 'QRIS', default => __('Tunai saat tiba') } }}
                                            <svg class="size-4 shrink-0 transition group-open:rotate-180 motion-reduce:transition-none" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m19 9-7 7-7-7"/></svg>
                                        </summary>
                                        <div class="px-4 pb-4">
                                            @if($method === \App\Enums\PaymentMethod::Cash)
                                                <p class="text-sm text-ink-soft">{{ __('Bayar di meja depan saat check-in. Tenda dipegang sampai akhir hari check-in; jika Anda tidak datang, tenda dilepas otomatis.') }}</p>
                                                <form action="{{ route('checkout.cash', $booking->access_token) }}" method="POST" class="mt-4">
                                                    @csrf
                                                    <button type="submit" class="btn btn-primary w-full">{{ __('Bayar tunai saat tiba') }}</button>
                                                </form>
                                            @else
                                                @if($method === \App\Enums\PaymentMethod::Transfer)
                                                    @if($bank->isComplete())
                                                        <dl class="rounded-xl border-[1.5px] border-sand p-4" aria-label="{{ __('Rekening tujuan') }}">
                                                            <dt class="text-sm text-ink-soft">{{ __('Rekening tujuan') }}</dt>
                                                            <dd class="mt-1 font-semibold">{{ $bank->bankName }}</dd>
                                                            <dd class="font-mono text-xl font-bold tracking-wider text-forest-900">{{ $bank->accountNumber }}</dd>
                                                            <dd class="text-sm text-ink-soft">{{ __('a.n.') }} {{ $bank->accountHolder }}</dd>
                                                        </dl>
                                                    @else
                                                        <p class="font-semibold" role="status">{{ __('Rekening belum diatur, hubungi admin.') }}</p>
                                                    @endif
                                                @else
                                                    <img src="{{ $qrisUrl }}" alt="{{ __('Kode QRIS :name', ['name' => config('site.name')]) }}" width="320" height="320" class="mx-auto w-full max-w-64 rounded-xl bg-white p-2">
                                                    <p class="mt-2 text-center text-sm text-ink-soft">{{ __('Pindai dengan aplikasi bank atau e-wallet, lalu isi nominal :amount.', ['amount' => $rupiah($outstanding)]) }}</p>
                                                @endif

                                                @if($method === \App\Enums\PaymentMethod::Qris || $bank->isComplete())
                                                    @php $fieldId = 'proof-'.$method->value; @endphp
                                                    <form action="{{ route('checkout.proof', $booking->access_token) }}" method="POST" enctype="multipart/form-data" class="mt-4">
                                                        @csrf
                                                        <input type="hidden" name="method" value="{{ $method->value }}">
                                                        <label for="{{ $fieldId }}" class="field-label">{{ $proof?->status === \App\Enums\PaymentStatus::Pending ? __('Kirim ulang bukti bayar') : __('Bukti bayar') }}</label>
                                                        <input id="{{ $fieldId }}" name="proof" type="file" accept=".jpg,.jpeg,.png,.webp,.pdf,image/jpeg,image/png,image/webp,application/pdf" required aria-describedby="{{ $fieldId }}-hint @error('proof') proof-error @enderror" class="field-input">
                                                        <p id="{{ $fieldId }}-hint" class="mt-2 text-sm text-ink-soft">{{ __('JPG, PNG, WebP, atau PDF, maksimal 4 MB. Pastikan nominal dan tanggal terbaca.') }}</p>
                                                        @if(old('method', 'transfer') === $method->value)
                                                             @error('proof')
                                                                <p id="proof-error" class="mt-2 text-sm font-semibold text-ember-dark" role="alert">{{ __($message) }}</p>
                                                            @enderror
                                                        @endif
                                                        <button type="submit" class="btn btn-primary mt-4 w-full">{{ __('Kirim bukti bayar') }}</button>
                                                    </form>
                                                @endif
                                            @endif
                                        </div>
                                    </details>
                                @endforeach
                            </div>
                        @endif
                    @endif

                    <div class="mt-6">
                        @include('public.partials.admin-whatsapp', ['booking' => $booking, 'class' => 'btn btn-outline-light w-full'])
                    </div>
                </aside>
            </div>

        @endunless
    </div>
@endsection
