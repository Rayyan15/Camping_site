@extends('layouts.public')

@section('title', 'Masuk - '.config('site.name'))

@section('noindex', '1')

@section('content')
    <div class="mx-auto grid max-w-6xl gap-10 px-4 py-12 sm:px-6 lg:grid-cols-[5fr_6fr] lg:gap-16 lg:py-20">
        <div class="lg:pt-4">
            <h1 class="font-display text-4xl font-semibold leading-tight text-forest-900 sm:text-5xl">Masuk untuk melihat booking Anda</h1>
            <p class="mt-4 max-w-prose text-ink-soft">Akun hanya untuk dua hal: melihat riwayat booking di satu halaman dan mengisi data pesanan lebih cepat. Memesan tenda tetap bisa tanpa akun.</p>
            <p class="mt-8 text-sm text-ink-soft">Belum punya akun? <a href="{{ route('account.register') }}" class="inline-flex min-h-11 items-center font-bold text-forest-800 underline underline-offset-4 hover:text-forest-950">Daftar</a></p>
            <p class="text-sm text-ink-soft">Punya kode booking saja? <a href="{{ route('booking.find') }}" class="inline-flex min-h-11 items-center font-bold text-forest-800 underline underline-offset-4 hover:text-forest-950">Cek status booking</a></p>
        </div>

        <div>
            @include('account.partials.status')

            <form method="POST" action="{{ route('login.store') }}" class="space-y-5 rounded-3xl border border-sand bg-[#fffdf8] p-6 sm:p-8">
                @csrf
                @include('account.partials.field', ['id' => 'email', 'name' => 'email', 'label' => 'Email', 'type' => 'email', 'value' => old('email'), 'autocomplete' => 'email', 'maxlength' => 190])
                @include('account.partials.field', ['id' => 'password', 'name' => 'password', 'label' => 'Password', 'type' => 'password', 'autocomplete' => 'current-password', 'maxlength' => 128])
                <div class="flex flex-wrap items-center gap-x-6 gap-y-2">
                    <button type="submit" class="btn btn-primary">Masuk</button>
                    <a href="{{ route('password.request') }}" class="inline-flex min-h-11 items-center text-sm font-bold text-forest-800 underline underline-offset-4 hover:text-forest-950">Lupa password</a>
                </div>
            </form>
        </div>
    </div>
@endsection
