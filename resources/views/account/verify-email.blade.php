@extends('layouts.public')

@section('title', 'Verifikasi email - '.config('site.name'))

@section('noindex', '1')

@section('content')
    <div class="mx-auto max-w-xl px-4 py-12 sm:px-6 lg:py-16">
        <h1 class="font-display text-3xl font-semibold text-forest-900 sm:text-4xl">Verifikasi email dulu</h1>
        <p class="mt-3 max-w-prose text-ink-soft">Kami mengirim tautan verifikasi ke <strong class="font-bold text-forest-900 break-all">{{ $email }}</strong>. Buka tautan itu untuk melihat riwayat booking. Cek juga folder spam.</p>

        <div class="mt-8">
            @include('account.partials.status')

            <div class="flex flex-wrap items-center gap-x-6 gap-y-3">
                <form method="POST" action="{{ route('verification.send') }}">
                    @csrf
                    <button type="submit" class="btn btn-primary">Kirim ulang tautan</button>
                </form>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="inline-flex min-h-11 items-center text-sm font-bold text-forest-800 underline underline-offset-4 hover:text-forest-950">Keluar</button>
                </form>
            </div>
        </div>
    </div>
@endsection
