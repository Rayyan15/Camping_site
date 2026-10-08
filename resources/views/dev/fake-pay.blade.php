@extends('layouts.public')

@section('title', 'Simulasi pembayaran - '.config('site.name'))

@section('content')
    <div class="mx-auto max-w-md px-4 py-16 sm:px-6">
        <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-forest-900/10">
            <p class="eyebrow text-ember-dark">Hanya lokal</p>
            <h1 class="font-display mt-2 text-2xl font-semibold text-forest-900">Simulasi pembayaran (hanya lokal)</h1>
            <p class="mt-3 text-ink-soft">
                Pesanan {{ $payment->payable->code }}, tagihan Rp {{ number_format($payment->amount, 0, ',', '.') }}.
                Halaman ini menggantikan halaman pembayaran gateway dan tidak tersedia di produksi.
            </p>

            <form action="{{ route('dev.pay', $payment->gateway_ref) }}" method="POST" class="mt-6 flex gap-3">
                @csrf
                <button type="submit" name="outcome" value="paid"
                        class="rounded-xl bg-forest-900 px-5 py-3 font-semibold text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-forest-900">
                    Bayar
                </button>
                <button type="submit" name="outcome" value="failed"
                        class="rounded-xl bg-ember-soft px-5 py-3 font-semibold text-ember-dark focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ember-dark">
                    Gagal
                </button>
            </form>
        </div>
    </div>
@endsection
