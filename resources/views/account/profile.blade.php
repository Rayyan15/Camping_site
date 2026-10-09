@extends('layouts.public')

@section('title', 'Data akun - '.config('site.name'))

@section('noindex', '1')

@section('content')
    <div class="mx-auto max-w-5xl px-4 py-12 sm:px-6 lg:py-16">
        <a href="{{ route('account.index') }}" class="inline-flex min-h-11 items-center text-sm font-bold text-forest-800 underline underline-offset-4 hover:text-forest-950">Kembali ke riwayat menginap</a>
        <h1 class="font-display mt-2 text-3xl font-semibold text-forest-900 sm:text-4xl">Data akun</h1>

        <div class="mt-6">
            @include('account.partials.status')
        </div>

        <div class="grid gap-8 lg:grid-cols-2 lg:gap-12">
            <form method="POST" action="{{ route('account.profile.update') }}" class="space-y-5 rounded-3xl border border-sand bg-[#fffdf8] p-6 sm:p-8">
                @csrf
                @method('PUT')
                <h2 class="font-display text-2xl font-semibold text-forest-900">Nama dan telepon</h2>
                @include('account.partials.field', ['id' => 'name', 'name' => 'name', 'label' => 'Nama lengkap', 'value' => old('name', $user->name), 'autocomplete' => 'name', 'maxlength' => 120])
                <div>
                    <p class="field-label">Email</p>
                    <p class="break-all font-semibold text-ink">{{ $user->email }}</p>
                    <p class="mt-1 text-sm text-ink-soft">Email tidak bisa diubah karena dipakai menautkan booking.</p>
                </div>
                @include('account.partials.field', ['id' => 'phone', 'name' => 'phone', 'label' => 'Nomor WhatsApp', 'type' => 'tel', 'value' => old('phone', $phone), 'autocomplete' => 'tel', 'inputmode' => 'tel', 'required' => false, 'hint' => 'Contoh: 0812 3456 7890.'])
                <button type="submit" class="btn btn-primary">Simpan data</button>
            </form>

            <form method="POST" action="{{ route('account.password.update') }}" class="space-y-5 rounded-3xl border border-sand bg-[#fffdf8] p-6 sm:p-8">
                @csrf
                @method('PUT')
                <h2 class="font-display text-2xl font-semibold text-forest-900">Ganti password</h2>
                @include('account.partials.field', ['id' => 'current_password', 'name' => 'current_password', 'label' => 'Password saat ini', 'type' => 'password', 'autocomplete' => 'current-password', 'maxlength' => 128])
                @include('account.partials.field', ['id' => 'new_password', 'name' => 'password', 'label' => 'Password baru', 'type' => 'password', 'autocomplete' => 'new-password', 'maxlength' => 128, 'hint' => 'Minimal '.config('auth.customer_password_min').' karakter.'])
                @include('account.partials.field', ['id' => 'new_password_confirmation', 'name' => 'password_confirmation', 'label' => 'Ulangi password baru', 'type' => 'password', 'autocomplete' => 'new-password', 'maxlength' => 128])
                <button type="submit" class="btn btn-primary">Ganti password</button>
            </form>
        </div>
    </div>
@endsection
