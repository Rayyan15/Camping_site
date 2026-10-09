@extends('layouts.public')

@section('title', 'Password baru - '.config('site.name'))

@section('noindex', '1')

@section('content')
    <div class="mx-auto max-w-xl px-4 py-12 sm:px-6 lg:py-16">
        <h1 class="font-display text-3xl font-semibold text-forest-900 sm:text-4xl">Buat password baru</h1>
        <p class="mt-3 max-w-prose text-ink-soft">Setelah disimpan, Anda masuk dengan password baru. Password lama tidak berlaku lagi.</p>

        <form method="POST" action="{{ route('password.update') }}" class="mt-8 space-y-5 rounded-3xl border border-sand bg-[#fffdf8] p-6 sm:p-8">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            @error('token')<p role="alert" class="text-sm font-semibold text-ember-dark">{{ $message }}</p>@enderror
            @include('account.partials.field', ['id' => 'email', 'name' => 'email', 'label' => 'Email', 'type' => 'email', 'value' => old('email', $email), 'autocomplete' => 'email', 'maxlength' => 190])
            @include('account.partials.field', ['id' => 'password', 'name' => 'password', 'label' => 'Password baru', 'type' => 'password', 'autocomplete' => 'new-password', 'maxlength' => 128, 'hint' => 'Minimal '.config('auth.customer_password_min').' karakter.'])
            @include('account.partials.field', ['id' => 'password_confirmation', 'name' => 'password_confirmation', 'label' => 'Ulangi password baru', 'type' => 'password', 'autocomplete' => 'new-password', 'maxlength' => 128])
            <div class="flex flex-wrap items-center gap-x-6 gap-y-2">
                <button type="submit" class="btn btn-primary">Simpan password</button>
                <a href="{{ route('password.request') }}" class="inline-flex min-h-11 items-center text-sm font-bold text-forest-800 underline underline-offset-4 hover:text-forest-950">Minta tautan baru</a>
            </div>
        </form>
    </div>
@endsection
