@extends('layouts.public')

@php
    $today = today()->toDateString();
    $searched = $availability !== null;
    $guestsWanted = (int) ($search['guests'] ?? 0);
    $stayParams = array_filter($search);
    $formatRupiah = fn (int $amount) => 'Rp '.number_format($amount, 0, ',', '.');

    $values = [
        ['title' => 'Ketersediaan langsung', 'text' => 'Pilih tanggal dan lihat tenda yang masih kosong, lengkap dengan sisa unitnya.',
            'icon' => 'M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5'],
        ['title' => 'Satu kali checkout', 'text' => 'Tenda, extra bed, dan pesanan makanan dibayar sekaligus. Tidak perlu bolak-balik.',
            'icon' => 'M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 00-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm12.75 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z'],
        ['title' => 'Bayar online', 'text' => 'Virtual account, QRIS, atau e-wallet. Status booking bisa dicek lewat kode booking tanpa login.',
            'icon' => 'M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z'],
    ];

    $steps = [
        ['title' => 'Pilih tanggal', 'text' => 'Isi check-in, check-out, dan jumlah tamu. Sistem menampilkan tipe tenda yang masih kosong.'],
        ['title' => 'Pilih tenda dan tambahan', 'text' => 'Pilih unit, tambah extra bed, dan jadwalkan makanan atau kopi yang ingin disiapkan.'],
        ['title' => 'Bayar dan terima kode', 'text' => 'Bayar online dalam '.$holdMinutes.' menit. Setelah itu kode booking dibuat dan dapat dipakai untuk cek status.'],
    ];

    $facilities = [
        ['title' => 'Tenda dengan nomor unit sendiri', 'text' => 'Setiap unit punya kode, jadi tidak ada tenda yang dipesan dua kali.'],
        ['title' => 'Resto', 'text' => 'Menu makanan yang sama bisa dipesan saat booking atau dari QR di tenda.'],
        ['title' => 'Coffeeshop', 'text' => 'Kopi dan minuman dipesan lewat menu yang sama, diantar ke lokasi Anda.'],
        ['title' => 'Extra bed', 'text' => 'Tambah kasur per malam saat memilih tenda, bila jumlah tamu melebihi kapasitas.'],
    ];

    $faqs = [
        ['q' => 'Jam berapa check-in dan check-out?', 'a' => 'Jam resmi tercantum pada konfirmasi booking. Untuk check-in lebih awal atau check-out lebih lambat, tanyakan lebih dulu ke pengelola.'],
        ['q' => 'Bagaimana aturan refund?', 'a' => 'Pembatalan 7 hari atau lebih sebelum check-in dikembalikan 100%. Pembatalan 3 sampai 6 hari sebelumnya dikembalikan 50%. Kurang dari 3 hari tidak ada pengembalian.'],
        ['q' => 'Berapa lama tenda ditahan sebelum saya bayar?', 'a' => 'Tenda ditahan '.$holdMinutes.' menit sejak checkout dibuat. Bila belum dibayar, tahanan dilepas otomatis dan tenda kembali tersedia.'],
        ['q' => 'Tamu saya melebihi kapasitas tenda. Bagaimana?', 'a' => 'Tambahkan extra bed saat memilih tenda. Kapasitas dasar tiap tipe tertera di kartu tenda.'],
        ['q' => 'Bisakah memesan makanan sebelum datang?', 'a' => 'Bisa. Pilih menu, tanggal, dan jam penyajian saat booking. Setelah menginap, pesan tambahan lewat QR di tenda.'],
        ['q' => 'Metode pembayaran apa yang tersedia?', 'a' => 'Pembayaran online lewat virtual account bank, QRIS, dan e-wallet.'],
    ];
@endphp

@section('title', config('site.name').' - Pesan tenda online, 7 tipe, 32 unit')

@push('head')
    <script type="application/ld+json">{!! json_encode($structuredData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
@endpush

@section('content')
    {{-- Hero --}}
    <section class="relative z-20 flex min-h-[92svh] items-end text-cream" aria-labelledby="judul-hero">
        <div class="hero-lqip absolute inset-0 overflow-hidden">
            <img src="{{ asset('images/hero-canvas-tent-1200.webp') }}" srcset="{{ asset('images/hero-canvas-tent-480.webp') }} 480w, {{ asset('images/hero-canvas-tent-800.webp') }} 800w, {{ asset('images/hero-canvas-tent-1200.webp') }} 1200w, {{ asset('images/hero-canvas-tent-1600.webp') }} 1600w" sizes="100vw" alt="Tenda kanvas di antara pepohonan dengan teras kayu, tamu duduk di depannya" width="1600" height="898" fetchpriority="high" decoding="async" class="size-full object-cover object-[60%_50%]">
            <div class="absolute inset-0 bg-gradient-to-t from-forest-950 via-forest-950/55 to-forest-950/25" aria-hidden="true"></div>
        </div>

        <div class="relative mx-auto w-full max-w-6xl px-4 pb-12 pt-40 sm:px-6 lg:pb-16">
            <h1 id="judul-hero" class="font-display max-w-3xl text-5xl font-semibold leading-[1.04] sm:text-6xl lg:text-7xl" data-reveal>
                Satu malam di bawah kanvas.
            </h1>
            <p class="mt-5 max-w-xl text-lg leading-relaxed text-cream/85" data-reveal data-reveal-delay="100">
                Pilih tanggal, lihat tenda yang masih kosong, lalu bayar online.
            </p>
            <div class="mt-10" data-reveal data-reveal-delay="200">
                <x-stay-search :search="$search" :today="$today" />
            </div>
        </div>
    </section>

    {{-- Value points --}}
    <section class="mx-auto max-w-6xl px-4 pb-6 pt-20 sm:px-6" aria-label="Keunggulan pemesanan">
        <ul class="grid gap-8 md:grid-cols-3">
            @foreach($values as $value)
                <li class="flex gap-4" data-reveal data-reveal-delay="{{ $loop->index * 100 }}">
                    <span class="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-forest-800 text-cream">
                        <svg class="size-6" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $value['icon'] }}"/></svg>
                    </span>
                    <div>
                        <h2 class="font-display text-xl font-semibold text-forest-900">{{ $value['title'] }}</h2>
                        <p class="mt-1 text-ink-soft">{{ $value['text'] }}</p>
                    </div>
                </li>
            @endforeach
        </ul>
    </section>

    {{-- Tent types --}}
    <section id="tenda" class="mx-auto max-w-6xl px-4 py-20 sm:px-6" aria-labelledby="judul-tenda">
        <div class="max-w-2xl" data-reveal>
            <p class="eyebrow text-ember-dark">{{ $unitTypes->count() }} tipe tenda</p>
            <h2 id="judul-tenda" class="font-display mt-3 text-3xl font-semibold text-forest-900 sm:text-4xl">Pilih tenda sesuai rombongan Anda</h2>
            <p class="mt-3 text-ink-soft">Kapasitas dan harga berbeda tiap tipe. Harga di bawah adalah harga per malam, weekday dan weekend.</p>
        </div>

        @if($searched)
            <p class="mt-8 rounded-2xl border border-forest-300 bg-forest-100 px-5 py-4 text-sm font-semibold text-forest-900" role="status">
                Hasil untuk {{ \Carbon\Carbon::parse($search['check_in'])->format('d M Y') }} sampai {{ \Carbon\Carbon::parse($search['check_out'])->format('d M Y') }}@if($guestsWanted), {{ $guestsWanted }} tamu @endif.
                <a href="{{ route('home') }}#tenda" class="ml-2 underline underline-offset-4">Hapus pencarian</a>
            </p>
        @endif

        @if($unitTypes->isEmpty())
            <p class="mt-10 rounded-2xl bg-cream-deep p-8 text-center text-ink-soft">Tipe tenda belum tersedia. Silakan kembali lagi nanti.</p>
        @else
            <ul class="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($unitTypes as $type)
                    @php
                        $free = $searched ? $availability[$type->id] : null;
                        $soldOut = $searched && $free === 0;
                        $needsExtraBed = $guestsWanted > $type->capacity;
                    @endphp
                    <li class="group relative flex flex-col overflow-hidden rounded-3xl border border-sand bg-[#fffdf8] transition hover:-translate-y-1 hover:shadow-xl hover:shadow-forest-950/10 {{ $soldOut ? 'opacity-70' : '' }}" data-reveal data-reveal-delay="{{ ($loop->index % 3) * 90 }}">
                        <div class="relative aspect-[4/3] overflow-hidden">
                            <x-unit-photo :unit-type="$type" />
                            @if($searched)
                                <span class="absolute left-3 top-3 rounded-full px-3 py-1 text-xs font-bold {{ $soldOut ? 'bg-ink text-cream' : 'bg-forest-800 text-cream' }}">
                                    {{ $soldOut ? 'Penuh di tanggal ini' : 'Sisa '.$free.' unit' }}
                                </span>
                            @endif
                        </div>
                        <div class="flex flex-1 flex-col p-5">
                            <div class="flex items-start justify-between gap-3">
                                <h3 class="font-display text-2xl font-semibold text-forest-900">
                                    <a href="{{ route('tenda.show', ['slug' => $type->slug] + $stayParams) }}" class="after:absolute after:inset-0 after:content-['']">{{ $type->name }}</a>
                                </h3>
                                <p class="shrink-0 pt-1 text-sm font-semibold text-ink-soft">{{ $type->units_count }} unit</p>
                            </div>
                            <p class="mt-1 flex items-center gap-2 text-sm font-semibold text-ink-soft">
                                <svg class="size-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/></svg>
                                Hingga {{ $type->capacity }} orang
                                @if($needsExtraBed)<span class="rounded-full bg-ember-soft px-2 py-0.5 text-xs text-ember-dark">perlu extra bed</span>@endif
                            </p>
                            @if(! empty($type->facilities))
                                <ul class="mt-4 flex flex-wrap gap-2" aria-label="Fasilitas {{ $type->name }}">
                                    @foreach(array_slice($type->facilities, 0, 4) as $facility)
                                        <li class="rounded-full bg-cream-deep px-3 py-1 text-xs font-semibold text-forest-800">{{ $facility }}</li>
                                    @endforeach
                                </ul>
                            @endif
                            <dl class="mt-auto grid grid-cols-2 gap-3 border-t border-sand pt-4">
                                <div>
                                    <dt class="text-xs font-semibold text-ink-soft">Weekday</dt>
                                    <dd class="font-bold text-forest-900">{{ $formatRupiah($type->base_price_weekday) }}</dd>
                                </div>
                                <div>
                                    <dt class="text-xs font-semibold text-ink-soft">Weekend</dt>
                                    <dd class="font-bold text-forest-900">{{ $formatRupiah($type->base_price_weekend) }}</dd>
                                </div>
                            </dl>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    {{-- How it works --}}
    <section id="cara-pesan" class="bg-cream-deep py-20" aria-labelledby="judul-cara">
        <div class="mx-auto max-w-6xl px-4 sm:px-6">
            <div class="max-w-2xl" data-reveal>
                <p class="eyebrow text-ember-dark">Cara pesan</p>
                <h2 id="judul-cara" class="font-display mt-3 text-3xl font-semibold text-forest-900 sm:text-4xl">Tiga langkah sampai kode booking</h2>
            </div>
            <ol class="mt-12 grid gap-10 md:grid-cols-3">
                @foreach($steps as $step)
                    <li class="relative rounded-3xl border border-sand bg-cream p-6 pt-10" data-reveal data-reveal-delay="{{ $loop->index * 100 }}">
                        <span class="font-display absolute -top-5 left-6 flex size-11 items-center justify-center rounded-full bg-ember text-xl font-semibold text-white ring-4 ring-cream-deep" aria-hidden="true">{{ $loop->iteration }}</span>
                        <h3 class="font-display text-xl font-semibold text-forest-900"><span class="sr-only">Langkah {{ $loop->iteration }}: </span>{{ $step['title'] }}</h3>
                        <p class="mt-2 text-ink-soft">{{ $step['text'] }}</p>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>

    {{-- Facilities and QR ordering --}}
    <section id="fasilitas" class="mx-auto max-w-6xl px-4 py-20 sm:px-6" aria-labelledby="judul-fasilitas">
        <div class="grid gap-12 lg:grid-cols-2 lg:items-center">
            <div data-reveal>
                <p class="eyebrow text-ember-dark">Fasilitas</p>
                <h2 id="judul-fasilitas" class="font-display mt-3 text-3xl font-semibold text-forest-900 sm:text-4xl">Lapar atau ingin kopi? Pesan dari tenda.</h2>
                <p class="mt-4 text-ink-soft">Setiap tenda punya QR sendiri. Scan, pilih menu, dan pesanan masuk ke antrian dapur dengan lokasi Anda sudah terisi. Tidak perlu login.</p>
                <ul class="mt-8 space-y-5">
                    @foreach($facilities as $facility)
                        <li class="flex gap-3">
                            <svg class="mt-1 size-5 shrink-0 text-ember" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                            <div>
                                <p class="font-bold text-forest-900">{{ $facility['title'] }}</p>
                                <p class="text-ink-soft">{{ $facility['text'] }}</p>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>

            <div class="relative" data-reveal data-reveal-delay="120">
                <img src="{{ asset('images/tent-rocks-800.webp') }}" srcset="{{ asset('images/tent-rocks-480.webp') }} 480w, {{ asset('images/tent-rocks-800.webp') }} 800w, {{ asset('images/tent-rocks-1200.webp') }} 1200w" sizes="(min-width: 1152px) 560px, (min-width: 1024px) 45vw, 100vw" alt="Tenda kanvas putih terbuka di antara batu dan pepohonan" width="1200" height="800" loading="lazy" decoding="async" class="aspect-[4/5] w-full rounded-[2rem] object-cover sm:aspect-[5/4]">
                <div class="absolute -bottom-8 left-4 w-60 rounded-3xl bg-forest-900 p-5 text-cream shadow-2xl shadow-forest-950/30 sm:left-auto sm:-right-4">
                    <p class="eyebrow text-sand">QR di tenda</p>
                    <svg class="mt-3 size-28 rounded-xl bg-cream p-2 text-forest-950" viewBox="0 0 21 21" shape-rendering="crispEdges" fill="currentColor" aria-label="Ilustrasi kode QR" role="img">
                        <path d="M0 0h7v7H0zM1 1v5h5V1zM2 2h3v3H2zM14 0h7v7h-7zM15 1v5h5V1zM16 2h3v3h-3zM0 14h7v7H0zM1 15v5h5v-5zM2 16h3v3H2zM8 0h2v2H8zM9 3h2v2H9zM8 6h1v2H8zM11 8h2v2h-2zM8 9h2v2H8zM0 8h2v2H0zM3 9h2v2H3zM5 8h1v1H5zM14 8h2v2h-2zM17 9h3v2h-3zM19 8h2v1h-2zM8 13h2v2H8zM11 12h2v3h-2zM14 14h3v2h-3zM18 14h3v2h-3zM8 17h3v2H8zM12 18h2v3h-2zM16 17h2v2h-2zM19 18h2v3h-2zM10 20h1v1h-1z"/>
                    </svg>
                    <p class="mt-3 text-sm text-forest-100">Scan, pilih menu, pesan ke lokasi Anda.</p>
                </div>
            </div>
        </div>
    </section>

    {{-- Numbers band --}}
    <section class="bg-forest-900 py-16 text-cream" aria-label="Angka penting">
        <dl class="mx-auto grid max-w-6xl grid-cols-2 gap-10 px-4 text-center sm:px-6 lg:grid-cols-4">
            @foreach([
                [$totalUnits, 'unit tenda'],
                [$unitTypes->count(), 'tipe tenda'],
                [$holdMinutes, 'menit tenda ditahan untuk Anda'],
                ['1', 'kali checkout untuk tenda dan makanan'],
            ] as [$number, $caption])
                <div data-reveal data-reveal-delay="{{ $loop->index * 80 }}">
                    <dd class="font-display text-5xl font-semibold text-sand">{{ $number }}</dd>
                    <dt class="mx-auto mt-2 max-w-[12rem] text-sm text-forest-100">{{ $caption }}</dt>
                </div>
            @endforeach
        </dl>
    </section>

    {{-- FAQ --}}
    <section id="faq" class="mx-auto max-w-3xl px-4 py-20 sm:px-6" aria-labelledby="judul-faq">
        <div data-reveal>
            <p class="eyebrow text-ember-dark">Pertanyaan umum</p>
            <h2 id="judul-faq" class="font-display mt-3 text-3xl font-semibold text-forest-900 sm:text-4xl">Sebelum Anda memesan</h2>
        </div>
        <div class="mt-10 divide-y divide-sand border-y border-sand" data-reveal>
            @foreach($faqs as $faq)
                <details class="faq-item group py-1">
                    <summary class="flex min-h-14 cursor-pointer items-center justify-between gap-4 py-3 font-bold text-forest-900">
                        {{ $faq['q'] }}
                        <svg class="faq-chevron size-5 shrink-0 text-ember transition-transform" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/></svg>
                    </summary>
                    <p class="pb-4 pr-9 text-ink-soft">{{ $faq['a'] }}</p>
                </details>
            @endforeach
        </div>
    </section>

    {{-- Closing CTA --}}
    <section class="px-4 pb-20 sm:px-6" aria-labelledby="judul-penutup">
        <div class="relative mx-auto max-w-6xl overflow-hidden rounded-[2rem] bg-forest-950 px-6 py-20 text-center text-cream sm:px-12" data-reveal>
            <img src="{{ asset('images/tent-trees-800.webp') }}" srcset="{{ asset('images/tent-trees-480.webp') }} 480w, {{ asset('images/tent-trees-800.webp') }} 800w, {{ asset('images/tent-trees-1200.webp') }} 1200w" sizes="(min-width: 1152px) 1152px, 100vw" alt="" width="1200" height="800" loading="lazy" decoding="async" class="absolute inset-0 size-full object-cover">
            <div class="absolute inset-0 bg-forest-950/70" aria-hidden="true"></div>
            <div class="relative">
                <h2 id="judul-penutup" class="font-display text-3xl font-semibold sm:text-5xl">Tentukan tanggalnya.</h2>
                <p class="mx-auto mt-3 max-w-xl text-cream/85">Tenda ditahan {{ $holdMinutes }} menit setelah Anda checkout.</p>
                <div class="mt-8 flex flex-col justify-center gap-3 sm:flex-row">
                    <a href="#cari" class="btn btn-primary">Cek ketersediaan</a>
                    @if(config('site.whatsapp_number'))
                        <a href="https://wa.me/{{ config('site.whatsapp_number') }}" target="_blank" rel="noopener" class="btn btn-outline-light">Tanya lewat WhatsApp</a>
                    @endif
                </div>
            </div>
        </div>
    </section>
@endsection
