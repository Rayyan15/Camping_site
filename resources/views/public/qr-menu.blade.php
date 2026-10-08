@extends('layouts.qr')

@php
    use App\Enums\QrPaymentChoice;

    $rupiah = fn (int $amount) => 'Rp '.number_format($amount, 0, ',', '.');
    $previous = collect(old('items', []))->keyBy('menu_item_id');
    $availableChoices = $booking ? QrPaymentChoice::cases() : [QrPaymentChoice::Cashier];
    $selectedChoice = old('payment_choice', QrPaymentChoice::Cashier->value);
@endphp

@section('title', 'Pesan makanan - '.$spot->name.' - '.config('site.name'))

@section('content')
    <header class="relative overflow-hidden bg-forest-900 px-5 pb-10 pt-8 text-cream">
        <p class="eyebrow text-forest-300">{{ config('site.name') }}</p>
        <h1 class="font-display mt-3 text-4xl font-semibold leading-tight">{{ $spot->name }}</h1>
        <p class="mt-2 max-w-sm text-sm leading-relaxed text-forest-100">Pilih menu, kirim, dan dapur langsung menyiapkan. Pesanan diantar ke lokasi ini.</p>
        <svg class="absolute inset-x-0 bottom-0 h-4 w-full text-cream" viewBox="0 0 360 16" preserveAspectRatio="none" aria-hidden="true">
            <path d="M0 16V9l30-5 40 7 50-8 60 9 55-7 50 6 45-8 30 6v10z" fill="currentColor"/>
        </svg>
    </header>

    @if($errors->any())
        <div role="alert" class="mx-4 mt-4 rounded-2xl bg-ember-soft px-4 py-3 text-sm font-semibold text-ember-dark">
            <p>Pesanan belum terkirim. Periksa kembali:</p>
            <ul class="mt-1 list-inside list-disc font-medium">
                @foreach($errors->all() as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if($categories->isEmpty())
        <p class="px-5 py-16 text-center text-ink-soft">Menu belum tersedia saat ini. Silakan tanya langsung ke kasir.</p>
    @else
        <form id="order-form" action="{{ route('qr.store', $spot->qr_token) }}" method="POST" class="flex-1 pb-36">
            @csrf

            <nav aria-label="Kategori menu" class="sticky top-0 z-20 border-b border-sand bg-cream/95 backdrop-blur">
                <ul class="flex gap-2 overflow-x-auto px-4 py-3 [scrollbar-width:none]">
                    @foreach($categories as $category)
                        <li class="shrink-0">
                            <a href="#kategori-{{ $category->id }}" class="inline-flex min-h-11 items-center rounded-full border border-sand bg-[#fffdf8] px-4 text-sm font-bold text-forest-900 transition hover:bg-forest-100">{{ $category->name }}</a>
                        </li>
                    @endforeach
                </ul>
            </nav>

            @foreach($categories as $category)
                <section id="kategori-{{ $category->id }}" class="scroll-mt-20 px-4 pt-8" aria-labelledby="judul-{{ $category->id }}">
                    <h2 id="judul-{{ $category->id }}" class="font-display text-2xl font-semibold text-forest-900">{{ $category->name }}</h2>
                    <ul class="mt-2 divide-y divide-sand">
                        @foreach($category->items as $item)
                            @php
                                $qty = (int) ($previous->get($item->id)['qty'] ?? 0);
                                $note = $previous->get($item->id)['notes'] ?? '';
                            @endphp
                            <li class="py-4" data-line data-price="{{ $item->price }}">
                                <div class="flex items-start justify-between gap-4">
                                    @if($item->photoUrl())
                                        <img src="{{ $item->photoUrl() }}" alt="{{ $item->name }}" width="72" height="72" loading="lazy" decoding="async" class="size-[72px] shrink-0 rounded-xl object-cover">
                                    @endif
                                    <div class="min-w-0 flex-1">
                                        <h3 class="font-semibold text-forest-900">{{ $item->name }}</h3>
                                        @if($item->description)
                                            <p class="mt-0.5 text-sm leading-snug text-ink-soft">{{ $item->description }}</p>
                                        @endif
                                        <p class="mt-1.5 font-bold text-ember-dark">{{ $rupiah($item->price) }}</p>
                                    </div>
                                    <div class="inline-flex shrink-0 items-center rounded-full border border-sand bg-[#fffdf8]">
                                        <button type="button" data-step="-1" class="grid size-11 place-items-center rounded-full text-forest-800 transition hover:bg-forest-100 disabled:opacity-35" aria-label="Kurangi {{ $item->name }}">
                                            <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="M5 12h14"/></svg>
                                        </button>
                                        <input type="number" name="items[{{ $item->id }}][qty]" value="{{ $qty }}" min="0" max="{{ \App\Http\Requests\Public\StoreQrOrderRequest::MAX_QTY }}" inputmode="numeric"
                                               aria-label="Jumlah {{ $item->name }}" data-qty
                                               class="w-9 appearance-none border-0 bg-transparent p-0 text-center font-bold text-forest-900 [-moz-appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none">
                                        <button type="button" data-step="1" class="grid size-11 place-items-center rounded-full text-forest-800 transition hover:bg-forest-100 disabled:opacity-35" aria-label="Tambah {{ $item->name }}">
                                            <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg>
                                        </button>
                                    </div>
                                </div>
                                <div data-notes @if($qty === 0) hidden @endif class="mt-3">
                                    <label for="catatan-{{ $item->id }}" class="field-label">Catatan untuk {{ $item->name }}</label>
                                    <input id="catatan-{{ $item->id }}" type="text" name="items[{{ $item->id }}][notes]" value="{{ $note }}" maxlength="{{ \App\Http\Requests\Public\StoreQrOrderRequest::MAX_NOTE_LENGTH }}" placeholder="Contoh: tidak pedas" class="field-input !min-h-11 !font-medium">
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endforeach

            <section class="mx-4 mt-10 rounded-3xl border border-sand bg-[#fffdf8] p-5" aria-labelledby="judul-pemesan">
                <h2 id="judul-pemesan" class="font-display text-2xl font-semibold text-forest-900">Data pemesan</h2>

                <div class="mt-4">
                    <label for="customer_name" class="field-label">Nama</label>
                    <input id="customer_name" name="customer_name" type="text" required maxlength="80" autocomplete="name" value="{{ old('customer_name', $booking?->customer?->name) }}" class="field-input">
                </div>
                <div class="mt-4">
                    <label for="customer_phone" class="field-label">Nomor WhatsApp (opsional)</label>
                    <input id="customer_phone" name="customer_phone" type="tel" inputmode="tel" autocomplete="tel" maxlength="20" value="{{ old('customer_phone') }}" class="field-input">
                </div>

                <fieldset class="mt-6">
                    <legend class="field-label">Cara bayar</legend>
                    <div class="grid gap-2">
                        @foreach($availableChoices as $choice)
                            <label class="flex min-h-14 cursor-pointer items-center gap-3 rounded-xl border-[1.5px] border-sand bg-cream px-4 py-3 has-[:checked]:border-forest-700 has-[:checked]:bg-forest-100">
                                <input type="radio" name="payment_choice" value="{{ $choice->value }}" required @checked($selectedChoice === $choice->value) class="size-5 accent-forest-800">
                                <span class="font-semibold text-forest-900">
                                    {{ $choice->label() }}
                                    @if($choice === QrPaymentChoice::Booking)
                                        <span class="block text-sm font-medium text-ink-soft">Masuk ke tagihan booking {{ $booking->code }}</span>
                                    @else
                                        <span class="block text-sm font-medium text-ink-soft">Bayar saat pesanan sampai atau di kasir</span>
                                    @endif
                                </span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>
            </section>
        </form>

        <div class="fixed inset-x-0 bottom-0 z-30 mx-auto max-w-xl rounded-t-3xl bg-forest-900 px-5 pb-5 pt-4 text-cream shadow-[0_-8px_24px_rgb(20_38_27/0.25)]" role="region" aria-label="Keranjang">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <p class="text-xs font-bold uppercase tracking-widest text-forest-300"><span data-cart-count>0</span> item</p>
                    <p class="font-display text-2xl font-semibold tabular-nums" data-cart-total aria-live="polite">Rp 0</p>
                </div>
                <button type="submit" form="order-form" data-cart-submit class="btn btn-primary min-w-40 disabled:cursor-not-allowed disabled:opacity-50">Kirim pesanan</button>
            </div>
        </div>
    @endif
@endsection
