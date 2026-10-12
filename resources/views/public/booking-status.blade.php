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

        @if($proof?->status === \App\Enums\PaymentStatus::Failed)
            <section class="mt-8 rounded-2xl bg-ember-soft p-6" role="alert">
                <h2 class="font-bold text-ember-dark">Bukti ditolak: {{ $proof->rejection_reason }}</h2>
                <p class="mt-1 text-ink">Silakan unggah ulang bukti transfer yang benar. Kami juga mengirim penjelasan ini ke email Anda.</p>
                @if($isPending)
                    <a href="{{ route('checkout.show', $booking->access_token) }}" class="btn btn-primary mt-4">Unggah ulang bukti</a>
                @endif
            </section>
        @endif

        @if($proof?->status === \App\Enums\PaymentStatus::Pending)
            <section class="mt-8 rounded-2xl bg-cream-deep p-6" role="status">
                <h2 class="font-bold text-forest-900">Bukti diterima, menunggu konfirmasi admin</h2>
                <p class="mt-1 text-ink-soft">Admin mencocokkan bukti dengan mutasi rekening. Tenda dipegang sampai {{ $booking->hold_expires_at->locale('id')->translatedFormat('j F Y, H:i') }} WIB. Cek email Anda: konfirmasi pembayaran dikirim ke sana, dan email itulah yang ditunjukkan di lokasi.</p>
                @if($holdActive)
                    <a href="{{ route('checkout.show', $booking->access_token) }}" class="btn btn-outline mt-4">Kirim ulang bukti</a>
                @endif
            </section>
        @elseif($holdActive)
            <section class="mt-8 rounded-2xl bg-cream-deep p-6">
                @if($booking->paid_amount > 0)
                    <h2 class="font-bold text-forest-900">DP Rp {{ number_format($booking->paid_amount, 0, ',', '.') }} sudah diterima</h2>
                    <p class="mt-1 text-ink-soft">Tenda dipegang sampai {{ $booking->hold_expires_at->locale('id')->translatedFormat('j F Y, H:i') }} WIB. Lunasi sisanya sebelum itu, atau saat check-in.</p>
                    <a href="{{ route('checkout.show', $booking->access_token) }}" class="btn btn-primary mt-4">Lunasi sisa tagihan</a>
                @else
                    <h2 class="font-bold text-forest-900">Tenda sedang ditahan untuk Anda</h2>
                    <p class="mt-1 text-ink-soft">Transfer dan kirim bukti sebelum {{ $booking->hold_expires_at->locale('id')->translatedFormat('j F Y, H:i') }} WIB.</p>
                    <a href="{{ route('checkout.show', $booking->access_token) }}" class="btn btn-primary mt-4">Lanjut ke pembayaran</a>
                @endif
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
                @if($booking->tax > 0)
                    <div class="flex justify-between"><dt class="text-ink-soft">Pajak</dt><dd>{{ $money($booking->tax) }}</dd></div>
                @endif
                <div class="flex justify-between text-lg"><dt class="font-bold text-forest-900">Total</dt><dd class="font-bold text-forest-900">{{ $money($booking->total) }}</dd></div>
                <div class="flex justify-between"><dt class="text-ink-soft">Sudah dibayar</dt><dd class="font-semibold">{{ $money($booking->paid_amount) }}</dd></div>
            </dl>
        </section>

        @includeIf('public.partials.booking-share', ['booking' => $booking])

        <div class="mt-4">
            @include('public.partials.admin-whatsapp', ['booking' => $booking])
        </div>

        @if($refund)
            <section class="mt-8 rounded-2xl bg-cream-deep p-6">
                <h2 class="font-bold text-forest-900">Pembatalan: {{ $refund->status->getLabel() }}</h2>
                <p class="mt-1 text-ink-soft">Nominal pengembalian dana {{ $money($refund->amount) }}.</p>
            </section>
        @endif

        @if($booking->status === BookingStatus::Paid)
            <section class="mt-8 rounded-3xl border border-sand bg-[#fffdf8] p-6 sm:p-8" aria-labelledby="judul-jadwal-ulang">
                <h2 id="judul-jadwal-ulang" class="font-display text-2xl font-semibold text-forest-900">Ubah tanggal menginap</h2>
                @if($booking->reschedule_credit > 0)
                    <p class="mt-2 font-semibold text-forest-900">Kelebihan bayar {{ $money($booking->reschedule_credit) }} akan dikembalikan oleh admin.</p>
                @endif
                @if($outstanding > 0)
                    <p class="mt-2 font-semibold text-forest-900">Sisa tagihan {{ $money($outstanding) }}. Transfer selisihnya lalu kirim bukti ke admin lewat WhatsApp di atas.</p>
                @endif
                @if($rescheduleBlockedReason)
                    <p class="mt-2 text-ink-soft">{{ $rescheduleBlockedReason }}</p>
                @else
                    <p class="mt-2 text-ink-soft">Bisa dilakukan satu kali, paling lambat {{ config('booking.reschedule.min_days_before_check_in') }} hari sebelum check-in. Harga dihitung ulang untuk tanggal baru. Bila lebih mahal, hubungi admin untuk membayar selisihnya.</p>
                    <details class="mt-5" @if($errors->hasAny(['check_in', 'check_out'])) open @endif>
                        <summary class="btn btn-outline cursor-pointer list-none">Pilih tanggal baru</summary>
                        <form method="POST" action="{{ route('booking.reschedule', $booking->access_token) }}" class="mt-5 grid gap-4 sm:grid-cols-2">
                            @csrf
                            <div>
                                <label for="new_check_in" class="field-label">Check-in baru</label>
                                <input id="new_check_in" name="check_in" type="date" required min="{{ now()->toDateString() }}" value="{{ old('check_in') }}" class="field-input">
                                @error('check_in')<p class="mt-1 text-sm font-semibold text-ember-dark">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="new_check_out" class="field-label">Check-out baru</label>
                                <input id="new_check_out" name="check_out" type="date" required min="{{ now()->addDay()->toDateString() }}" value="{{ old('check_out') }}" class="field-input">
                                @error('check_out')<p class="mt-1 text-sm font-semibold text-ember-dark">{{ $message }}</p>@enderror
                            </div>
                            <label class="flex min-h-11 cursor-pointer items-start gap-3 py-2 text-sm text-ink-soft sm:col-span-2">
                                <input type="checkbox" required class="mt-0.5 size-5 accent-forest-800">
                                Saya paham jadwal hanya bisa diubah satu kali.
                            </label>
                            <div class="sm:col-span-2">
                                <button type="submit" class="btn btn-primary">Konfirmasi tanggal baru</button>
                            </div>
                        </form>
                    </details>
                @endif
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
                        <label class="flex min-h-11 cursor-pointer items-start gap-3 py-2 text-sm text-ink-soft">
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
