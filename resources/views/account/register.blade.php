@extends('layouts.public')

@section('title', 'Buat akun - '.config('site.name'))

@section('noindex', '1')

@section('content')
    <div class="mx-auto grid max-w-6xl gap-10 px-4 py-12 sm:px-6 lg:grid-cols-[5fr_6fr] lg:gap-16 lg:py-20">
        <div class="lg:pt-4">
            <h1 class="font-display text-4xl font-semibold leading-tight text-forest-900 sm:text-5xl">Simpan booking Anda di satu halaman</h1>
            <p class="mt-4 max-w-prose text-ink-soft">Setelah email diverifikasi, booking yang pernah Anda buat dengan email yang sama ikut muncul di akun. Sebelum itu, tidak ada booking yang ditautkan.</p>
            <p class="mt-8 text-sm text-ink-soft">Sudah punya akun? <a href="{{ route('login') }}" class="inline-flex min-h-11 items-center font-bold text-forest-800 underline underline-offset-4 hover:text-forest-950">Masuk</a></p>
        </div>

        <form method="POST" action="{{ route('account.register.store') }}" class="space-y-5 rounded-3xl border border-sand bg-[#fffdf8] p-6 sm:p-8">
            @csrf
            @include('account.partials.field', ['id' => 'name', 'name' => 'name', 'label' => 'Nama lengkap', 'value' => old('name'), 'autocomplete' => 'name', 'maxlength' => 120])
            @include('account.partials.field', ['id' => 'email', 'name' => 'email', 'label' => 'Email', 'type' => 'email', 'value' => old('email'), 'autocomplete' => 'email', 'maxlength' => 190, 'hint' => 'Tautan verifikasi dikirim ke alamat ini.'])
            @include('account.partials.field', ['id' => 'phone', 'name' => 'phone', 'label' => 'Nomor WhatsApp', 'type' => 'tel', 'value' => old('phone'), 'autocomplete' => 'tel', 'inputmode' => 'tel', 'required' => false, 'hint' => 'Dipakai untuk mengisi formulir booking. Contoh: 0812 3456 7890.'])
            @include('account.partials.field', ['id' => 'password', 'name' => 'password', 'label' => 'Password', 'type' => 'password', 'autocomplete' => 'new-password', 'maxlength' => 128, 'hint' => 'Minimal '.config('auth.customer_password_min').' karakter.'])
            @include('account.partials.field', ['id' => 'password_confirmation', 'name' => 'password_confirmation', 'label' => 'Ulangi password', 'type' => 'password', 'autocomplete' => 'new-password', 'maxlength' => 128])
            <button type="submit" class="btn btn-primary">Buat akun</button>
        </form>
    </div>
@endsection
