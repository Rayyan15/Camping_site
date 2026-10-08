@extends('layouts.qr')

@php
    $rupiah = fn (int $amount) => 'Rp '.number_format($amount, 0, ',', '.');
    $currentStep = $progress['step'];
    $stepHints = [
        'baru' => 'Pesanan masuk, menunggu dapur.',
        'diproses' => 'Dapur sedang menyiapkan pesanan Anda.',
        'siap' => 'Pesanan sudah siap dan menunggu diantar.',
        'diantar' => 'Pesanan sedang menuju lokasi Anda.',
        'selesai' => 'Pesanan sudah sampai. Selamat menikmati.',
    ];
@endphp

@section('title', 'Pesanan '.$order->code.' - '.config('site.name'))

@section('content')
    <header class="bg-forest-900 px-5 pb-8 pt-8 text-cream">
        <p class="eyebrow text-forest-300">Kode pesanan</p>
        <h1 class="font-display mt-2 text-3xl font-semibold tracking-wider">{{ $order->code }}</h1>
        <p class="mt-2 text-sm text-forest-100">{{ $spot->name }}, atas nama {{ $order->customer_name }}</p>
    </header>

    <div class="flex-1 px-4 pb-10 pt-6">
        <p id="status-hint" role="status" aria-live="polite" class="text-lg font-semibold text-forest-900">{{ $stepHints[$progress['status']] }}</p>

        <ol class="mt-5 grid gap-0" aria-label="Tahapan pesanan" data-steps data-hints="{{ json_encode($stepHints) }}">
            @foreach($steps as $index => $step)
                @php $state = $index < $currentStep ? 'done' : ($index === $currentStep ? 'current' : 'todo'); @endphp
                <li class="relative flex items-center gap-4 py-3" data-step-item data-state="{{ $state }}">
                    <span class="grid size-8 shrink-0 place-items-center rounded-full border-2 text-sm font-bold
                        {{ $state === 'todo' ? 'border-sand bg-cream text-ink-soft' : 'border-forest-800 bg-forest-800 text-cream' }}"
                        data-dot>{{ $index + 1 }}</span>
                    <span class="font-semibold {{ $state === 'todo' ? 'text-ink-soft' : 'text-forest-900' }}" data-label>{{ $step->label() }}</span>
                    <span class="sr-only" data-sr>{{ $state === 'done' ? '(selesai)' : ($state === 'current' ? '(saat ini)' : '(belum)') }}</span>
                </li>
            @endforeach
        </ol>

        <section class="mt-8 rounded-3xl border border-sand bg-[#fffdf8] p-5" aria-labelledby="judul-rincian">
            <h2 id="judul-rincian" class="font-display text-xl font-semibold text-forest-900">Rincian pesanan</h2>
            <ul class="mt-3 divide-y divide-sand">
                @foreach($order->items as $line)
                    <li class="flex items-start justify-between gap-4 py-3">
                        <div>
                            <p class="font-semibold text-forest-900">{{ $line->qty }} x {{ $line->menuItem->name }}</p>
                            @if($line->notes)
                                <p class="text-sm text-ink-soft">Catatan: {{ $line->notes }}</p>
                            @endif
                        </div>
                        <p class="shrink-0 font-semibold tabular-nums">{{ $rupiah($line->subtotal()) }}</p>
                    </li>
                @endforeach
            </ul>
            <dl class="mt-3 flex items-center justify-between border-t-2 border-forest-800 pt-4">
                <dt class="font-bold text-forest-900">Total</dt>
                <dd class="font-display text-2xl font-semibold tabular-nums text-forest-900">{{ $rupiah($order->total) }}</dd>
            </dl>
            <p class="mt-3 text-sm font-semibold text-ink-soft" data-payment>
                @if($order->bill_to_booking)
                    Ditagihkan ke booking Anda.
                @elseif($order->isPaid())
                    Sudah dibayar.
                @else
                    Belum dibayar. Bayar ke kasir.
                @endif
            </p>
        </section>

        <a href="{{ route('qr.show', $spot->qr_token) }}" class="btn btn-outline mt-6 w-full">Pesan lagi</a>
    </div>
@endsection
