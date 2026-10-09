@extends('layouts.public')

@section('title', 'Lupa password - '.config('site.name'))

@section('noindex', '1')

@section('content')
    <div class="mx-auto max-w-xl px-4 py-12 sm:px-6 lg:py-16">
        <h1 class="font-display text-3xl font-semibold text-forest-900 sm:text-4xl">Atur ulang password</h1>
        <p class="mt-3 max-w-prose text-ink-soft">Isi email akun Anda. Kami kirim tautan untuk membuat password baru.</p>

        <div class="mt-8">
            @include('account.partials.status')

            <form method="POST" action="{{ route('password.email') }}" class="space-y-5 rounded-3xl border border-sand bg-[#fffdf8] p-6 sm:p-8">
                @csrf
                @include('account.partials.field', ['id' => 'email', 'name' => 'email', 'label' => 'Email', 'type' => 'email', 'value' => old('email'), 'autocomplete' => 'email', 'maxlength' => 190])
                <div class="flex flex-wrap items-center gap-x-6 gap-y-2">
                    <button type="submit" class="btn btn-primary">Kirim tautan</button>
                    <a href="{{ route('login') }}" class="inline-flex min-h-11 items-center text-sm font-bold text-forest-800 underline underline-offset-4 hover:text-forest-950">Kembali ke halaman masuk</a>
                </div>
            </form>
        </div>
    </div>
@endsection
