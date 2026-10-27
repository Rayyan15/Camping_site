@extends('layouts.public')

@php
    $today = today()->toDateString();
    $searched = $availability !== null;
    $guestsWanted = (int) ($search['guests'] ?? 0);
    $stayParams = array_filter($search);
    $formatRupiah = fn (int $amount) => 'Rp '.number_format($amount, 0, ',', '.');
    $whatsapp = config('site.whatsapp_number');
    $address = config('site.address');
    $mapsUrl = config('site.maps_url');
    $instagram = config('site.instagram_url');
    $tiktok = config('site.tiktok_url');
    $hasContact = $address || $mapsUrl || $whatsapp || $instagram || $tiktok;
    $settings = app(\App\Services\SettingRepository::class);
    $checkInTime = str_replace(':', '.', $settings->checkInTime());
    $checkOutTime = str_replace(':', '.', $settings->checkOutTime());
    $extraGuestLimit = (int) config('booking.max_extra_guests_per_unit');
    $includesBreakfast = (bool) config('booking.base_price_includes_breakfast');
    $headline = explode(' ', __('Satu malam di bawah kanvas.'));
    $featuredDish = $menuByCategory->flatten()->filter(fn ($item) => $item->photoUrl())->sortByDesc('price')->first();

    $phases = ['sore' => __('Sore'), 'petang' => __('Petang'), 'malam' => __('Malam'), 'subuh' => __('Subuh')];

    $steps = [
        ['title' => __('Pilih tanggal'), 'text' => __('Isi check-in, check-out, dan jumlah tamu. Sistem menampilkan tipe tenda yang masih kosong beserta sisa unitnya.')],
        ['title' => __('Pilih tenda dan tambahan'), 'text' => __('Pilih unit, tambah tamu bila perlu, dan jadwalkan makanan atau kopi yang ingin disiapkan sebelum Anda datang.')],
        ['title' => __('Transfer dan kirim bukti'), 'text' => __('Transfer ke rekening yang tampil di halaman pembayaran, lalu unggah bukti dalam :minutes menit. Konfirmasi admin dikirim lewat email.', ['minutes' => $holdMinutes])],
    ];

    $facilities = [
        ['title' => __('Nomor unit sendiri'), 'text' => __('Tiap tenda punya kode unit, jadi tidak ada tenda yang dipesan dua kali.')],
        ['title' => __('Resto'), 'text' => __('Menu yang sama bisa dipesan saat booking atau dari QR di tenda.')],
        ['title' => __('Coffeeshop'), 'text' => __('Kopi dan minuman lewat menu yang sama, diantar ke lokasi Anda.')],
        ['title' => __('Tamu tambahan'), 'text' => __('Tiap tenda menampung 4 orang, bisa ditambah sampai :limit orang lewat add-on saat memilih tenda.', ['limit' => $extraGuestLimit])],
    ];

    $faqs = [
        ['q' => __('Jam berapa check-in dan check-out?'), 'a' => __('Check-in mulai pukul :in dan check-out paling lambat pukul :out.', ['in' => $checkInTime, 'out' => $checkOutTime]).($whatsapp ? ' '.__('Untuk masuk lebih awal atau keluar lebih lambat, tanyakan dulu lewat WhatsApp.') : '')],
        ['q' => __('Bagaimana aturan refund?'), 'a' => $refundSummary ?? __('Aturan refund ditentukan pengelola. Tanyakan sebelum memesan.')],
        ...($includesBreakfast ? [['q' => __('Apakah harga sudah termasuk sarapan?'), 'a' => __('Ya, harga dasar per malam sudah termasuk sarapan.')]] : []),
        ['q' => __('Berapa lama tenda ditahan sebelum saya bayar?'), 'a' => __('Tenda ditahan :minutes menit sejak checkout dibuat. Bila belum dibayar, tahanan dilepas otomatis dan tenda kembali tersedia.', ['minutes' => $holdMinutes])],
        ['q' => __('Tamu saya melebihi kapasitas tenda. Bagaimana?'), 'a' => __('Tiap tenda menampung 4 orang. Anda bisa menambah paling banyak :limit orang per tenda lewat add-on tamu tambahan saat memilih tenda.', ['limit' => $extraGuestLimit])],
        ['q' => __('Bisakah memesan makanan sebelum datang?'), 'a' => __('Bisa. Pilih menu, tanggal, dan jam penyajian saat booking. Setelah menginap, pesan tambahan lewat QR di tenda.')],
        ['q' => __('Metode pembayaran apa yang tersedia?'), 'a' => __('Transfer bank ke rekening yang tampil di halaman pembayaran, lalu unggah foto bukti transfer. Admin mencocokkan dengan mutasi dan mengirim konfirmasi lewat email.')],
        ['q' => __('Bagaimana cek status booking saya?'), 'a' => __('Buka halaman Cek status booking, lalu isi kode booking dan 4 digit terakhir nomor telepon atau email yang Anda pakai saat memesan.')],
    ];
@endphp

@section('title', config('site.name').' - '.__('Pesan tenda online, :types tipe, :units unit', ['types' => $unitTypes->count(), 'units' => $totalUnits]))

@push('head')
    <script type="application/ld+json">{!! json_encode($structuredData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
@endpush

@section('content')
    {{-- Phase marker: real in-page links, current phase follows the scroll. --}}
    <nav aria-label="{{ __('Fase menginap') }}" class="fixed left-4 top-1/2 z-30 hidden -translate-y-1/2 min-[1360px]:block">
        <ol class="space-y-2">
            @foreach($phases as $id => $label)
                <li><a href="#{{ $id }}" data-phase-link="{{ $id }}" aria-current="{{ $loop->first ? 'true' : 'false' }}" class="phase-link">{{ $label }}</a></li>
            @endforeach
        </ol>
    </nav>

    {{-- SORE: arrive and check dates. --}}
    <section id="sore" data-phase="sore" class="relative z-20 flex min-h-[100svh] items-end text-cream" aria-labelledby="judul-hero">
        <div class="hero-lqip absolute inset-0 overflow-hidden">
            <img data-parallax="-0.25" src="{{ asset('images/hero-safari-sunset-1200.webp') }}" srcset="{{ asset('images/hero-safari-sunset-480.webp') }} 480w, {{ asset('images/hero-safari-sunset-800.webp') }} 800w, {{ asset('images/hero-safari-sunset-1200.webp') }} 1200w" sizes="100vw" alt="{{ __('Tenda Safari di Halimun Highland saat matahari terbenam, dengan meja dan kursi di teras') }}" width="1200" height="1264" fetchpriority="high" decoding="async" class="absolute inset-x-0 -top-[28%] h-[128%] w-full object-cover object-[50%_60%]">
            {{-- Scrim keeps the headline readable over the brightest part of the photo. --}}
            <div class="absolute inset-0 bg-gradient-to-t from-forest-950 via-forest-950/50 to-forest-950/20" aria-hidden="true"></div>
        </div>

        <div class="relative mx-auto w-full max-w-6xl px-4 pb-24 pt-40 sm:px-6 sm:pb-28">
            <h1 id="judul-hero" class="font-display max-w-5xl text-[clamp(3.25rem,10vw,8.5rem)] font-semibold leading-[0.95]">
                @foreach($headline as $word)<span class="rise-word" style="--i: {{ $loop->index }}">{{ $word }}</span>@if(! $loop->last) @endif @endforeach
            </h1>
            <p class="mt-6 max-w-xl text-lg leading-relaxed text-cream/90">
                {{ __('Pilih tanggal, lihat tenda yang masih kosong, lalu bayar online. Tenda, extra bed, dan makanan dalam satu checkout.') }}
            </p>
            <div class="mt-10">
                <x-stay-search :search="$search" :today="$today" />
            </div>
            <p class="mt-6 text-sm font-semibold text-cream/80">
                {{ $totalUnits }} {{ __('unit') }}<span aria-hidden="true"> &middot; </span><span class="sr-only">, </span>{{ $unitTypes->count() }} {{ __('tipe') }}<span aria-hidden="true"> &middot; </span><span class="sr-only">, </span>{{ __('ditahan :minutes menit setelah checkout', ['minutes' => $holdMinutes]) }}
            </p>
        </div>
        <x-ridge class="text-dusk-100" />
    </section>

    {{-- PETANG: choose a tent, then learn how booking works. --}}
    <section id="petang" data-phase="petang" class="relative bg-dusk-100 pb-32 pt-16 text-forest-900 sm:pb-40 sm:pt-20" aria-labelledby="judul-tenda">
        <div class="mx-auto max-w-6xl px-4 sm:px-6">
            <div id="tenda" class="grid gap-8 lg:grid-cols-[1.3fr_1fr] lg:items-end" data-reveal>
                <div>
                    <p class="font-display text-xl italic text-ember-dark">{{ __('Petang') }}</p>
                    <h2 id="judul-tenda" class="font-display mt-2 text-5xl font-semibold leading-[1.02] sm:text-6xl lg:text-7xl">{{ __('Pilih tenda untuk rombongan Anda.') }}</h2>
                </div>
                <div class="flex flex-col gap-5">
                    <p class="max-w-md text-ink-soft">{{ __(':count tipe, kapasitas dan harga berbeda. Harga per malam, weekday dan weekend. Geser ke samping, atau buka katalog untuk membandingkan semuanya.', ['count' => $unitTypes->count()]) }}</p>
                </div>
            </div>

            @if($searched)
                <p class="mt-8 rounded-2xl border border-forest-300 bg-forest-100 px-5 py-4 text-sm font-semibold text-forest-900" role="status">
                    @php
                        $searchDates = ['from' => \Carbon\Carbon::parse($search['check_in'])->translatedFormat('d M Y'), 'to' => \Carbon\Carbon::parse($search['check_out'])->translatedFormat('d M Y')];
                    @endphp
                    {{ $guestsWanted ? trans_choice('Hasil untuk :from sampai :to, :count tamu.', $guestsWanted, $searchDates) : __('Hasil untuk :from sampai :to.', $searchDates) }}
                    <a href="{{ route('home') }}#tenda" class="ml-2 underline underline-offset-4">{{ __('Hapus pencarian') }}</a>
                </p>
            @endif
        </div>

        @if($unitTypes->isEmpty())
            <p class="mx-auto mt-10 max-w-6xl rounded-2xl bg-cream p-8 text-center text-ink-soft sm:mx-6 lg:mx-auto">{{ __('Tipe tenda belum tersedia. Silakan kembali lagi nanti.') }}</p>
        @else
            {{-- The rail shares the max-w-6xl column with every other section, so its edges line up with the headings above and below. --}}
            <div data-rail="tenda" role="region" aria-label="{{ __('Daftar tipe tenda, geser ke samping') }}" tabindex="0" class="tent-rail mx-auto mt-10 max-w-6xl scroll-px-4 overflow-x-auto pb-6 sm:scroll-px-6">
                <ul class="flex w-max gap-4 px-4 sm:gap-6 sm:px-6">
                    @foreach($unitTypes as $type)
                        @php
                            $free = $searched ? $availability[$type->id] : null;
                            $soldOut = $searched && $free === 0;
                            $needsExtraBed = $guestsWanted > $type->capacity;
                            $photo = $type->photos->sortBy('sort_order')->first();
                        @endphp
                        <li class="relative isolate flex h-[30rem] shrink-0 overflow-hidden rounded-3xl bg-forest-800 text-cream sm:h-[36rem] {{ $loop->first ? 'w-[86vw] sm:w-[38rem]' : 'w-[76vw] sm:w-[24rem]' }} {{ $soldOut ? 'opacity-75' : '' }}">
                            @if($photo)
                                <img src="{{ $photo->url }}" alt="{{ __('Tenda tipe :name', ['name' => $type->name]) }}" width="1600" height="1067" loading="lazy" decoding="async" class="absolute inset-0 -z-10 size-full object-cover">
                            @endif
                            <div class="absolute inset-0 -z-10 bg-gradient-to-t from-forest-950 via-forest-950/55 to-transparent" aria-hidden="true"></div>

                            @if($searched || $loop->first)
                                <span class="absolute left-4 top-4 rounded-full bg-forest-950/85 px-3 py-1 text-xs font-bold text-cream">
                                    @if($searched){{ $soldOut ? __('Penuh di tanggal ini') : trans_choice('Sisa :count unit', $free) }}@else {{ __('Harga terendah') }} @endif
                                </span>
                            @endif

                            <div class="mt-auto w-full p-5 sm:p-6">
                                <h3 class="font-display text-4xl font-semibold leading-none sm:text-5xl">
                                    <a href="{{ route('tenda.show', ['slug' => $type->slug] + $stayParams) }}" class="after:absolute after:inset-0 after:content-['']">{{ $type->name }}</a>
                                </h3>
                                <p class="mt-2 text-sm font-semibold text-cream/90">
                                    {{ __('Hingga :count orang', ['count' => $type->capacity]) }}<span aria-hidden="true"> &middot; </span><span class="sr-only">, </span>{{ trans_choice(':count unit', $type->units_count) }}
                                    @if($needsExtraBed)<span class="ml-1 rounded-full bg-ember px-2 py-0.5 text-xs text-white">{{ __('perlu extra bed') }}</span>@endif
                                </p>
                                @if(! empty($type->facilities))
                                    <ul class="mt-3 flex flex-wrap gap-2" aria-label="{{ __('Fasilitas :name', ['name' => $type->name]) }}">
                                        @foreach(array_slice($type->facilities, 0, $loop->first ? 5 : 3) as $facility)
                                            <li class="rounded-full bg-forest-950/70 px-3 py-1 text-xs font-semibold text-cream">{{ $facility }}</li>
                                        @endforeach
                                    </ul>
                                @endif
                                <dl class="mt-4 flex items-end gap-6 border-t border-cream/30 pt-4">
                                    <div>
                                        <dt class="text-xs font-semibold text-cream/80">{{ __('Weekday, per malam') }}</dt>
                                        <dd class="font-display text-2xl font-semibold">{{ $formatRupiah($type->base_price_weekday) }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs font-semibold text-cream/80">{{ __('Weekend') }}</dt>
                                        <dd class="text-base font-semibold">{{ $formatRupiah($type->base_price_weekend) }}</dd>
                                    </div>
                                </dl>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>

            {{-- Autoplay needs a way to stop it (WCAG 2.2.2). It stays out of sight for mouse users, who stop it by hovering, and appears for keyboard and screen reader users. --}}
            <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-4 px-4 sm:px-6">
                <a href="{{ route('tenda.index', array_filter(['tamu' => $guestsWanted ?: null])) }}" class="btn btn-primary">{{ __('Lihat katalog tenda') }}</a>
                <div class="rail-controls hidden gap-2 sm:flex">
                    <button type="button" data-rail-step="tenda" data-dir="-1" class="ss-nav" aria-label="{{ __('Tenda sebelumnya') }}">
                        <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m15 18-6-6 6-6"/></svg>
                    </button>
                    <button type="button" data-rail-step="tenda" data-dir="1" class="ss-nav" aria-label="{{ __('Tenda berikutnya') }}">
                        <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m9 18 6-6-6-6"/></svg>
                    </button>
                </div>
                <button type="button" data-rail-toggle="tenda" aria-pressed="false" class="rail-controls btn btn-outline sr-only focus:not-sr-only focus:mt-3" aria-label="{{ __('Jeda geser otomatis') }}">
                    <span data-label="pause">{{ __('Jeda geser otomatis') }}</span>
                    <span data-label="play" class="hidden">{{ __('Putar geser otomatis') }}</span>
                </button>
            </div>
        @endif

        <div id="cara-pesan" class="mx-auto mt-24 grid max-w-6xl gap-10 px-4 sm:px-6 lg:grid-cols-[1fr_1.7fr] lg:gap-16">
            <div class="lg:sticky lg:top-28 lg:self-start" data-reveal>
                <p class="font-display text-xl italic text-ember-dark">{{ __('Cara pesan') }}</p>
                <h2 class="font-display mt-2 text-4xl font-semibold leading-[1.05] sm:text-5xl">{{ __('Dari tanggal sampai kode booking.') }}</h2>
            </div>
            <ol class="border-t border-forest-900/25">
                @foreach($steps as $step)
                    <li class="grid gap-3 border-b border-forest-900/25 py-8 sm:grid-cols-[5rem_1fr]" data-reveal data-reveal-delay="{{ $loop->index * 90 }}">
                        <span class="font-display text-6xl font-semibold italic leading-none text-ember-dark" aria-hidden="true">{{ $loop->iteration }}</span>
                        <div>
                            <h3 class="font-display text-2xl font-semibold"><span class="sr-only">{{ __('Langkah :n:', ['n' => $loop->iteration]) }} </span>{{ $step['title'] }}</h3>
                            <p class="mt-2 max-w-xl text-ink-soft">{{ $step['text'] }}</p>
                        </div>
                    </li>
                @endforeach
            </ol>
        </div>
        <x-ridge class="text-forest-950" />
    </section>

    {{-- MALAM: food and coffee, ordered by QR. --}}
    <section id="malam" data-phase="malam" class="relative overflow-hidden bg-forest-950 pb-32 pt-20 text-cream sm:pb-40" aria-labelledby="judul-fasilitas">
        <div class="stars absolute inset-0" aria-hidden="true"></div>

        <div id="fasilitas" class="relative mx-auto grid max-w-6xl gap-14 px-4 sm:px-6 lg:grid-cols-12 lg:items-center">
            <div class="lg:col-span-6" data-reveal>
                <p class="font-display text-xl italic text-ember-light">{{ __('Malam') }}</p>
                <h2 id="judul-fasilitas" class="font-display mt-2 text-5xl font-semibold leading-[1.02] sm:text-6xl">{{ __('Lapar atau ingin kopi? Pesan dari tenda.') }}</h2>
                <p class="mt-5 max-w-lg text-forest-100">{{ __('Setiap tenda punya QR sendiri. Scan, pilih menu, dan pesanan masuk ke antrean dapur dengan lokasi Anda sudah terisi. Tidak perlu login.') }}</p>
                <dl class="mt-8 grid gap-x-8 gap-y-5 sm:grid-cols-2">
                    @foreach($facilities as $facility)
                        <div class="border-t border-forest-300/40 pt-4">
                            <dt class="font-display text-xl font-semibold">{{ $facility['title'] }}</dt>
                            <dd class="mt-1 text-sm text-forest-100">{{ $facility['text'] }}</dd>
                        </div>
                    @endforeach
                </dl>
            </div>

            <div class="relative lg:col-span-6" data-reveal data-reveal-delay="120">
                <img src="{{ asset('images/safari-interior-800.webp') }}" srcset="{{ asset('images/safari-interior-480.webp') }} 480w, {{ asset('images/safari-interior-800.webp') }} 800w, {{ asset('images/safari-interior-1200.webp') }} 1200w" sizes="(min-width: 1152px) 560px, (min-width: 1024px) 45vw, 100vw" alt="{{ __('Bagian dalam tenda Safari: kasur, rak, dan kursi lipat') }}" width="1200" height="1113" loading="lazy" decoding="async" class="aspect-[4/5] w-full rounded-[2rem] object-cover sm:aspect-[5/4]">
                <div class="absolute -bottom-8 left-4 flex w-72 items-center gap-4 rounded-3xl bg-cream p-4 text-forest-900 shadow-2xl shadow-black/40 sm:left-auto sm:-right-4">
                    <svg class="size-20 shrink-0 rounded-xl bg-white p-1.5 text-forest-950" viewBox="0 0 21 21" shape-rendering="crispEdges" fill="currentColor" aria-label="{{ __('Ilustrasi kode QR') }}" role="img">
                        <path d="M0 0h7v7H0zM1 1v5h5V1zM2 2h3v3H2zM14 0h7v7h-7zM15 1v5h5V1zM16 2h3v3h-3zM0 14h7v7H0zM1 15v5h5v-5zM2 16h3v3H2zM8 0h2v2H8zM9 3h2v2H9zM8 6h1v2H8zM11 8h2v2h-2zM8 9h2v2H8zM0 8h2v2H0zM3 9h2v2H3zM5 8h1v1H5zM14 8h2v2h-2zM17 9h3v2h-3zM19 8h2v1h-2zM8 13h2v2H8zM11 12h2v3h-2zM14 14h3v2h-3zM18 14h3v2h-3zM8 17h3v2H8zM12 18h2v3h-2zM16 17h2v2h-2zM19 18h2v3h-2zM10 20h1v1h-1z"/>
                    </svg>
                    <div>
                        <p class="font-display text-lg italic leading-tight text-ember-dark">{{ __('QR di tenda') }}</p>
                        <p class="mt-1 text-sm leading-snug text-ink-soft">{{ __('Scan, pilih menu, pesanan diantar ke tenda Anda.') }}</p>
                    </div>
                </div>
            </div>
        </div>

        @if($menuByCategory->isNotEmpty())
            <div class="relative mx-auto mt-28 max-w-6xl px-4 sm:px-6">
                <div class="grid gap-10 lg:grid-cols-[22rem_1fr] lg:gap-14">
                    @if($featuredDish)
                        <figure class="self-start" data-reveal>
                            <img src="{{ $featuredDish->photoUrl() }}" alt="{{ $featuredDish->name }}" width="800" height="800" loading="lazy" decoding="async" class="aspect-square w-full rounded-3xl object-cover">
                            <figcaption class="mt-3 flex items-baseline justify-between gap-4">
                                <span class="font-display text-xl font-semibold">{{ $featuredDish->name }}</span>
                                <span class="text-sm font-semibold text-forest-100">{{ $formatRupiah($featuredDish->price) }}</span>
                            </figcaption>
                        </figure>
                    @endif

                    <div data-reveal data-reveal-delay="100">
                        <h3 class="font-display text-3xl font-semibold sm:text-4xl">{{ __('Menu yang sama untuk semua pesanan.') }}</h3>
                        <p class="mt-2 max-w-lg text-forest-100">{{ __('Dipesan saat booking atau lewat QR di tenda. Yang habis tidak ditampilkan.') }}</p>
                        <div class="mt-8 grid gap-x-10 gap-y-8 sm:grid-cols-2">
                            @foreach($menuByCategory as $category => $items)
                                <section aria-label="{{ $category }}">
                                    <h4 class="font-display text-lg italic text-ember-light">{{ $category }}</h4>
                                    <ul class="mt-2">
                                        @foreach($items as $item)
                                            <li class="flex items-baseline gap-3 py-1.5">
                                                <span class="font-semibold">{{ $item->name }}</span>
                                                <span class="min-w-6 flex-1 border-b border-dotted border-forest-300/60" aria-hidden="true"></span>
                                                <span class="text-sm text-forest-100">{{ $formatRupiah($item->price) }}</span>
                                            </li>
                                        @endforeach
                                    </ul>
                                </section>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        @endif
        <x-ridge class="text-forest-100" />
    </section>

    {{-- SUBUH: questions, then book. --}}
    <section id="subuh" data-phase="subuh" class="bg-forest-100 pt-16 text-forest-900 sm:pt-20" aria-labelledby="judul-faq">
        <div id="faq" class="mx-auto grid max-w-6xl gap-10 px-4 sm:px-6 lg:grid-cols-[1fr_1.7fr] lg:gap-16">
            <div class="lg:self-start" data-reveal>
                <p class="font-display text-xl italic text-ember-dark">{{ __('Subuh') }}</p>
                <h2 id="judul-faq" class="font-display mt-2 text-4xl font-semibold leading-[1.05] sm:text-5xl">{{ __('Sebelum Anda memesan.') }}</h2>
                <p class="mt-5 max-w-sm text-ink-soft">{{ __('Sudah punya booking?') }} <a href="{{ route('booking.find') }}" class="font-bold text-forest-900 underline underline-offset-4">{{ __('Cek status booking') }}</a> {{ __('dengan kode booking Anda.') }}</p>
            </div>
            <div class="divide-y divide-forest-900/20 border-y border-forest-900/20" data-reveal>
                @foreach($faqs as $faq)
                    <details class="faq-item group py-1">
                        <summary class="flex min-h-14 cursor-pointer items-center justify-between gap-4 py-3 text-lg font-bold">
                            {{ $faq['q'] }}
                            <svg class="faq-chevron size-5 shrink-0 text-ember-dark transition-transform" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/></svg>
                        </summary>
                        <p class="max-w-2xl pb-4 pr-9 text-ink-soft">{{ $faq['a'] }}</p>
                    </details>
                @endforeach
            </div>
        </div>

        @if($hasContact)
            <div class="mx-auto mt-16 max-w-6xl px-4 sm:px-6" data-reveal>
                <dl class="grid gap-6 border-t border-forest-900/20 pt-8 text-center sm:grid-cols-2 lg:grid-cols-5">
                    @if($address)<div><dt class="font-display text-lg italic text-ember-dark">{{ __('Alamat') }}</dt><dd class="mx-auto mt-1 max-w-56 text-ink-soft">{{ $address }}</dd></div>@endif
                    @if($mapsUrl)<div><dt class="font-display text-lg italic text-ember-dark">{{ __('Peta') }}</dt><dd class="mt-1"><a href="{{ $mapsUrl }}" target="_blank" rel="noopener" class="inline-flex min-h-11 items-center gap-2 font-bold underline underline-offset-4">{{ __('Buka di peta') }}</a></dd></div>@endif
                    @if($whatsapp)<div><dt class="font-display text-lg italic text-ember-dark">WhatsApp</dt><dd class="mt-1"><a href="https://wa.me/{{ $whatsapp }}" target="_blank" rel="noopener" class="inline-flex min-h-11 items-center gap-2 font-bold underline underline-offset-4">{{ __('Kirim pesan') }}</a></dd></div>@endif
                    @if($instagram)<div><dt class="font-display text-lg italic text-ember-dark">Instagram</dt><dd class="mt-1"><a href="{{ $instagram }}" target="_blank" rel="noopener" class="inline-flex min-h-11 items-center gap-2 font-bold underline underline-offset-4">{{ __('Buka Instagram') }}</a></dd></div>@endif
                    @if($tiktok)<div><dt class="font-display text-lg italic text-ember-dark">TikTok</dt><dd class="mt-1"><a href="{{ $tiktok }}" target="_blank" rel="noopener" class="inline-flex min-h-11 items-center gap-2 font-bold underline underline-offset-4"><svg class="size-5 shrink-0" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M16.6 3c.3 2.4 1.7 3.9 4 4.1v3.1a7.2 7.2 0 0 1-4-1.3v6.1a5.6 5.6 0 1 1-5.6-5.6c.3 0 .6 0 .9.1v3.2a2.5 2.5 0 1 0 1.6 2.3V3h3.1Z"/></svg>{{ __('Buka TikTok') }}</a></dd></div>@endif
                </dl>
            </div>
        @endif

        <div class="relative mt-20 overflow-hidden bg-forest-950 text-cream" data-reveal>
            <img src="{{ asset('images/safari-row-dusk-800.webp') }}" srcset="{{ asset('images/safari-row-dusk-480.webp') }} 480w, {{ asset('images/safari-row-dusk-800.webp') }} 800w, {{ asset('images/safari-row-dusk-1200.webp') }} 900w" sizes="100vw" alt="" width="900" height="1600" loading="lazy" decoding="async" class="absolute inset-0 size-full object-cover object-[50%_35%]">
            <div class="absolute inset-0 bg-forest-950/70" aria-hidden="true"></div>
            <div class="relative mx-auto max-w-6xl px-4 py-24 sm:px-6 sm:py-32">
                <h2 class="font-display max-w-3xl text-5xl font-semibold leading-[1.02] sm:text-7xl">{{ __('Pilih malam Anda.') }}</h2>
                <p class="mt-4 max-w-xl text-lg text-cream/90">{{ __('Tenda ditahan :minutes menit setelah Anda checkout.', ['minutes' => $holdMinutes]) }}</p>
                <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                    <a href="#cari" class="btn btn-primary">{{ __('Cek ketersediaan') }}</a>
                    @if($whatsapp)
                        <a href="https://wa.me/{{ $whatsapp }}" target="_blank" rel="noopener" class="btn btn-outline-light">{{ __('Tanya lewat WhatsApp') }}</a>
                    @endif
                </div>
            </div>
        </div>
    </section>
@endsection
