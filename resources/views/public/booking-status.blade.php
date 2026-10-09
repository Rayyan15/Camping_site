@extends('layouts.public')

@php
    use App\Enums\BookingStatus;

    $statusLabel = match ($booking->status) {
        BookingStatus::PendingPayment => 'Menunggu pembayaran',
        BookingStatus::Paid => 'Lunas',
        BookingStatus::CheckedIn => 'Sedang menginap',
        BookingStatus::CheckedOut => 'Selesai',
        BookingStatus::Expired => 'Kedaluwarsa',
        BookingStatus::Cancelled => 'Dibatalkan',
        BookingStatus::Refunded => 'Dana dikembalikan',
        BookingStatus::NeedsReview => 'Sedang ditinjau',
    };
    $statusTone = match ($booking->status) {
        BookingStatus::Paid, BookingStatus::CheckedIn, BookingStatus::CheckedOut => 'bg-forest-100 text-forest-900',
        BookingStatus::PendingPayment, BookingStatus::NeedsReview => 'bg-sand text-sand-dark',
        default => 'bg-ember-soft text-ember-dark',
    };
    $steps = [
        ['label' => 'Booking dibuat', 'done' => true],
        ['label' => 'Pembayaran lunas', 'done' => in_array($booking->status, [BookingStatus::Paid, BookingStatus::CheckedIn, BookingStatus::CheckedOut, BookingStatus::NeedsReview, BookingStatus::Refunded], true)],
        ['label' => 'Check-in', 'done' => in_array($booking->status, [BookingStatus::CheckedIn, BookingStatus::CheckedOut], true)],
        ['label' => 'Check-out', 'done' => $booking->status === BookingStatus::CheckedOut],
    ];
    $isPending = $booking->status === BookingStatus::PendingPayment;
    $holdActive = $isPending && $booking->hold_expires_at?->isFuture();
    $money = fn (int $amount) => 'Rp '.number_format($amount, 0, ',', '.');
@endphp

@section('title', 'Status booking '.$booking->code.' - '.config('site.name'))

@section('noindex', '1')

@section('content')
    <div class="mx-auto max-w-3xl px-4 py-12 sm:px-6 lg:py-16">
        @if(session('success'))
            <p role="status" class="mb-6 rounded-2xl bg-forest-100 px-5 py-4 text-sm font-semibold text-forest-900">{{ session('success') }}</p>
        @endif
        @if(session('error'))
            <p role="alert" class="mb-6 rounded-2xl bg-ember-soft px-5 py-4 text-sm font-semibold text-ember-dark">{{ session('error') }}</p>
        @endif

        <header class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="eyebrow text-sand-dark">Kode booking</p>
                <h1 class="font-display mt-1 text-4xl font-semibold tracking-wider text-forest-900">{{ $booking->code }}</h1>
                <p class="mt-1 text-ink-soft">Atas nama {{ $booking->customer->name }}</p>
            </div>
            <span class="rounded-full px-4 py-2 text-sm font-bold {{ $statusTone }}">{{ $statusLabel }}</span>
        </header>

        <ol class="mt-10 grid grid-cols-2 gap-4 sm:grid-cols-4" aria-label="Tahapan booking">
            @foreach($steps as $step)
                <li class="border-t-4 pt-3 {{ $step['done'] ? 'border-forest-800' : 'border-sand' }}">
                    <span class="text-sm font-bold {{ $step['done'] ? 'text-forest-900' : 'text-ink-soft' }}">{{ $step['label'] }}</span>
                    <span class="sr-only">{{ $step['done'] ? '(selesai)' : '(belum)' }}</span>
                </li>
            @endforeach
        </ol>

        @if($holdActive)
            <section class="mt-8 rounded-2xl bg-cream-deep p-6">
                <h2 class="font-bold text-forest-900">Tenda sedang ditahan untuk Anda</h2>
                <p class="mt-1 text-ink-soft">Selesaikan pembayaran sebelum {{ $booking->hold_expires_at->format('H:i') }} WIB, sisa {{ (int) now()->diffInMinutes($booking->hold_expires_at, false) }} menit.</p>
                <a href="{{ route('checkout.show', $booking->access_token) }}" class="btn btn-primary mt-4">Lanjut ke pembayaran</a>
            </section>
        @endif

        <section class="mt-10 rounded-3xl border border-sand bg-[#fffdf8] p-6 sm:p-8">
            <h2 class="font-display text-2xl font-semibold text-forest-900">Rincian menginap</h2>
            <dl class="mt-5 grid gap-5 sm:grid-cols-3">
                <div>
                    <dt class="field-label">Check-in</dt>
                    <dd class="font-semibold">{{ $booking->check_in->translatedFormat('l, j F Y') }}</dd>
                </div>
                <div>
                    <dt class="field-label">Check-out</dt>
                    <dd class="font-semibold">{{ $booking->check_out->translatedFormat('l, j F Y') }}</dd>
                </div>
                <div>
                    <dt class="field-label">Tamu</dt>
                    <dd class="font-semibold">{{ $booking->guests }} orang</dd>
                </div>
            </dl>

            <ul class="mt-6 divide-y divide-sand">
                @foreach($booking->bookingUnits as $line)
                    <li class="flex items-baseline justify-between gap-4 py-3">
                        <span>{{ $line->unit->unitType->name }} <span class="text-ink-soft">- unit {{ $line->unit->code }}, {{ $line->nights }} malam</span></span>
                        <span class="font-semibold">{{ $money($line->subtotal) }}</span>
                    </li>
                @endforeach
                @foreach($booking->addons as $line)
                    <li class="flex items-baseline justify-between gap-4 py-3">
                        <span>{{ $line->addon->name }} <span class="text-ink-soft">x {{ $line->qty }}</span></span>
                        <span class="font-semibold">{{ $money($line->subtotal) }}</span>
                    </li>
                @endforeach
                @foreach($booking->orders as $order)
                    <li class="flex items-baseline justify-between gap-4 py-3">
                        <span>Pesan makanan <span class="text-ink-soft">{{ $order->code }}</span></span>
                        <span class="font-semibold">{{ $money($order->total) }}</span>
                    </li>
                @endforeach
            </ul>

            <dl class="mt-4 space-y-2 border-t-2 border-forest-900 pt-4">
                <div class="flex justify-between"><dt class="text-ink-soft">Subtotal</dt><dd>{{ $money($booking->subtotal) }}</dd></div>
                <div class="flex justify-between"><dt class="text-ink-soft">Pajak</dt><dd>{{ $money($booking->tax) }}</dd></div>
                <div class="flex justify-between text-lg"><dt class="font-bold text-forest-900">Total</dt><dd class="font-bold text-forest-900">{{ $money($booking->total) }}</dd></div>
                <div class="flex justify-between"><dt class="text-ink-soft">Sudah dibayar</dt><dd class="font-semibold">{{ $money($booking->paid_amount) }}</dd></div>
            </dl>
        </section>

        @includeIf('public.partials.booking-share', ['booking' => $booking])

        @if($refund)
            <section class="mt-8 rounded-2xl bg-cream-deep p-6">
                <h2 class="font-bold text-forest-900">Pembatalan: {{ $refund->status->getLabel() }}</h2>
                <p class="mt-1 text-ink-soft">Nominal pengembalian dana {{ $money($refund->amount) }}.</p>
            </section>
        @endif

        @if($canCancel)
            <section class="mt-8 rounded-3xl border border-ember-soft bg-[#fffdf8] p-6 sm:p-8">
                <h2 class="font-display text-2xl font-semibold text-forest-900">Batalkan booking</h2>
                @if($quote)
                    <p class="mt-2 text-ink-soft">{{ $quote->daysBefore }} hari sebelum check-in.</p>
                    @if($quote->amount > 0)
                        <p class="mt-2 text-ink">
                            Jika dibatalkan sekarang, dana yang dikembalikan:
                            <strong class="text-forest-900">{{ $money($quote->amount) }} ({{ $quote->percent }}%)</strong>.
                            Pengajuan ditinjau pemilik sebelum disetujui.
                        </p>
                    @else
                        <p class="mt-2 font-semibold text-ember-dark">Pembatalan ini tidak mendapat pengembalian dana.</p>
                    @endif
                @else
                    <p class="mt-2 text-ink-soft">Booking belum dibayar, jadi pembatalan tidak dikenai biaya dan tenda langsung dilepas.</p>
                @endif

                <details class="mt-5">
                    <summary class="btn btn-outline cursor-pointer list-none">Ajukan pembatalan</summary>
                    <form method="POST" action="{{ route('booking.cancel', $booking->access_token) }}" class="mt-5 space-y-4">
                        @csrf
                        <div>
                            <label for="reason" class="field-label">Alasan pembatalan</label>
                            <textarea id="reason" name="reason" rows="3" maxlength="500" required class="field-input">{{ old('reason') }}</textarea>
                            @error('reason')<p class="mt-1 text-sm font-semibold text-ember-dark">{{ $message }}</p>@enderror
                        </div>
                        <label class="flex items-start gap-3 text-sm text-ink-soft">
                            <input type="checkbox" required class="mt-0.5 size-5 accent-forest-800">
                            @if($quote && $quote->amount > 0)
                                Saya paham pembatalan tidak dapat diurungkan dan dana yang dikembalikan sebesar {{ $money($quote->amount) }}.
                            @elseif($quote)
                                Saya paham pembatalan tidak dapat diurungkan dan tidak ada dana yang dikembalikan.
                            @else
                                Saya paham pembatalan tidak dapat diurungkan.
                            @endif
                        </label>
                        <button type="submit" class="btn btn-primary">Konfirmasi pembatalan</button>
                    </form>
                </details>
            </section>
        @endif
    </div>
@endsection
