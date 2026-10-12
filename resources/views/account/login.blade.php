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
                <div>
                    <label for="password" class="field-label">Password</label>
                    {{-- The toggle stays hidden until JS runs, so without JS the field is a plain password input. --}}
                    <div class="relative">
                        <input id="password" name="password" type="password" autocomplete="current-password" maxlength="128" required
                               @error('password') aria-invalid="true" aria-describedby="password-error" @enderror
                               class="field-input pr-14">
                        <button type="button" hidden data-password-toggle aria-controls="password" aria-pressed="false" aria-label="Tampilkan kata sandi"
                                class="absolute inset-y-0 right-1 my-auto flex size-11 items-center justify-center rounded-lg text-forest-800 hover:text-forest-950 focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-forest-700">
                            <svg data-icon="show" class="size-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/></svg>
                            <svg data-icon="hide" class="hidden size-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88"/></svg>
                        </button>
                    </div>
                    @error('password')
                        <p id="password-error" class="mt-1 text-sm font-semibold text-ember-dark">{{ $message }}</p>
                    @enderror
                </div>
                <div class="flex flex-wrap items-center gap-x-6 gap-y-2">
                    <button type="submit" class="btn btn-primary">Masuk</button>
                    <a href="{{ route('password.request') }}" class="inline-flex min-h-11 items-center text-sm font-bold text-forest-800 underline underline-offset-4 hover:text-forest-950">Lupa password</a>
                </div>
            </form>
        </div>
    </div>
@endsection
