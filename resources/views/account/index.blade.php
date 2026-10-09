@extends('layouts.public')

@php
    $money = fn (int $amount) => 'Rp '.number_format($amount, 0, ',', '.');
    $statusTone = fn ($status) => match ($status->getColor()) {
        'success' => 'bg-forest-100 text-forest-900',
        'danger' => 'bg-ember-soft text-ember-dark',
        default => 'bg-sand text-forest-900',
    };
@endphp

@section('title', 'Akun saya - '.config('site.name'))

@section('noindex', '1')

@section('content')
    <div class="mx-auto max-w-4xl px-4 py-12 sm:px-6 lg:py-16">
        <div class="flex flex-wrap items-end justify-between gap-x-8 gap-y-4">
            <div>
                <h1 class="font-display text-3xl font-semibold text-forest-900 sm:text-4xl">Riwayat menginap</h1>
                <p class="mt-2 text-ink-soft">Akun {{ $user->name }}, {{ $user->email }}</p>
            </div>
            <div class="flex items-center gap-x-5">
                <a href="{{ route('account.profile') }}" class="inline-flex min-h-11 items-center text-sm font-bold text-forest-800 underline underline-offset-4 hover:text-forest-950">Ubah data akun</a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="inline-flex min-h-11 items-center text-sm font-bold text-forest-800 underline underline-offset-4 hover:text-forest-950">Keluar</button>
                </form>
            </div>
        </div>

        <div class="mt-8">
            @include('account.partials.status')
        </div>

        @if($bookings->isEmpty())
            <div class="rounded-3xl border border-sand bg-[#fffdf8] p-6 sm:p-8">
                <h2 class="font-display text-2xl font-semibold text-forest-900">Belum ada booking di akun ini</h2>
                <p class="mt-2 max-w-prose text-ink-soft">Booking yang Anda buat sambil masuk akan muncul di sini. Booking lama dengan email {{ $user->email }} juga ikut tampil setelah email terverifikasi.</p>
                <div class="mt-6 flex flex-wrap items-center gap-x-6 gap-y-2">
                    <a href="{{ route('home') }}#cari" class="btn btn-primary">Cek tanggal menginap</a>
                    <a href="{{ route('booking.find') }}" class="inline-flex min-h-11 items-center text-sm font-bold text-forest-800 underline underline-offset-4 hover:text-forest-950">Punya kode booking? Cek statusnya</a>
                </div>
            </div>
        @else
            <ul class="divide-y divide-sand rounded-3xl border border-sand bg-[#fffdf8]">
                @foreach($bookings as $booking)
                    @php
                        $nights = $booking->check_in->diffInDays($booking->check_out);
                        $tents = $booking->bookingUnits->map(fn ($row) => $row->unit?->unitType?->name)->filter()->unique()->implode(', ');
                    @endphp
                    <li class="grid gap-4 p-5 sm:grid-cols-[4.5rem_1fr_auto] sm:items-center sm:gap-6 sm:p-6">
                        <div class="flex items-baseline gap-2 sm:block sm:text-center" aria-hidden="true">
                            <span class="font-display text-4xl font-semibold leading-none text-forest-900">{{ $booking->check_in->format('j') }}</span>
                            <span class="text-sm font-bold uppercase tracking-wider text-ink-soft sm:mt-1 sm:block">{{ $booking->check_in->locale('id')->translatedFormat('M Y') }}</span>
                        </div>

                        <div class="min-w-0">
                            <p class="font-mono text-sm font-bold tracking-wider text-forest-900">{{ $booking->code }}</p>
                            <p class="mt-1 text-ink">
                                {{ $booking->check_in->locale('id')->translatedFormat('j M Y') }} sampai {{ $booking->check_out->locale('id')->translatedFormat('j M Y') }}, {{ $nights }} malam
                            </p>
                            @if($tents !== '')
                                <p class="text-sm text-ink-soft">{{ $tents }}</p>
                            @endif
                            <div class="mt-3 flex flex-wrap items-center gap-x-5 gap-y-1">
                                <a href="{{ route('booking.status', $booking->access_token) }}" class="inline-flex min-h-11 items-center text-sm font-bold text-forest-800 underline underline-offset-4 hover:text-forest-950">Lihat status<span class="sr-only">, booking {{ $booking->code }}</span></a>
                                @if($booking->isInvoiceable())
                                    <a href="{{ route('booking.invoice', $booking->access_token) }}" class="inline-flex min-h-11 items-center text-sm font-bold text-forest-800 underline underline-offset-4 hover:text-forest-950">Unduh invoice<span class="sr-only">, booking {{ $booking->code }}</span></a>
                                @endif
                            </div>
                        </div>

                        <div class="flex items-center justify-between gap-4 sm:block sm:text-right">
                            <span class="inline-flex rounded-md px-2.5 py-1 text-xs font-bold {{ $statusTone($booking->status) }}">{{ $booking->status->getLabel() }}</span>
                            <p class="font-display text-xl font-semibold text-forest-900 sm:mt-3">{{ $money($booking->total) }}</p>
                        </div>
                    </li>
                @endforeach
            </ul>

            @if($bookings->hasPages())
                <nav aria-label="Halaman riwayat" class="mt-6 flex items-center justify-between gap-4 text-sm font-bold text-forest-800">
                    @if($bookings->previousPageUrl())
                        <a href="{{ $bookings->previousPageUrl() }}" rel="prev" class="inline-flex min-h-11 items-center underline underline-offset-4 hover:text-forest-950">Booking lebih baru</a>
                    @else
                        <span></span>
                    @endif
                    @if($bookings->nextPageUrl())
                        <a href="{{ $bookings->nextPageUrl() }}" rel="next" class="inline-flex min-h-11 items-center underline underline-offset-4 hover:text-forest-950">Booking lebih lama</a>
                    @endif
                </nav>
            @endif
        @endif
    </div>
@endsection
