@extends('layouts.public')

@section('title', 'Cek status booking - '.config('site.name'))

@section('noindex', '1')

@section('content')
    <div class="mx-auto max-w-xl px-4 py-12 sm:px-6 lg:py-16">
        <p class="eyebrow text-sand-dark">Cek status booking</p>
        <h1 class="font-display mt-1 text-3xl font-semibold text-forest-900 sm:text-4xl">Masukkan kode booking Anda</h1>
        <p class="mt-3 max-w-prose text-ink-soft">Kode booking ada di pesan konfirmasi dan invoice. Untuk menjaga data tamu, kami minta satu data lagi yang Anda isi saat memesan.</p>

        @if(session('error'))
            <p role="alert" class="mt-6 rounded-2xl bg-ember-soft px-5 py-4 text-sm font-semibold text-ember-dark">{{ session('error') }}</p>
        @endif

        <form method="POST" action="{{ route('booking.find.submit') }}" class="mt-8 space-y-5 rounded-3xl border border-sand bg-[#fffdf8] p-6 sm:p-8">
            @csrf
            <div>
                <label for="code" class="field-label">Kode booking</label>
                <input id="code" name="code" type="text" value="{{ old('code') }}" required maxlength="32"
                       autocomplete="off" autocapitalize="characters" placeholder="RCM-261201-ABC123" class="field-input font-mono tracking-wider">
                @error('code')<p class="mt-1 text-sm font-semibold text-ember-dark">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="proof" class="field-label">4 digit terakhir telepon atau email</label>
                <input id="proof" name="proof" type="text" required maxlength="255" autocomplete="off"
                       aria-describedby="proof-hint" class="field-input">
                <p id="proof-hint" class="mt-1 text-sm text-ink-soft">Pakai nomor telepon atau email yang Anda isi saat memesan.</p>
                @error('proof')<p class="mt-1 text-sm font-semibold text-ember-dark">{{ $message }}</p>@enderror
            </div>
            <button type="submit" class="btn btn-primary">Lihat status booking</button>
        </form>
    </div>
@endsection
