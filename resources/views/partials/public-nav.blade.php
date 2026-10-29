@php
    $home = route('home');
    $currentLocale = app()->getLocale();
    $navLinks = [
        __('Tenda') => $home.'#tenda',
        __('Cara pesan') => $home.'#cara-pesan',
        __('Fasilitas') => $home.'#fasilitas',
        __('FAQ') => $home.'#faq',
    ];
    // Guests never sign in: a booking is found again by its code, so that is the only personal link here.
    $findBookingHref = route('booking.find');
@endphp
<header class="sticky top-0 z-50 border-b border-sand/70 bg-cream/95 backdrop-blur">
    <div class="mx-auto flex h-16 max-w-6xl items-center justify-between gap-4 px-4 sm:px-6">
        <a href="{{ $home }}" class="flex min-h-11 items-center text-forest-900" aria-label="{{ __(':name, beranda', ['name' => config('site.name')]) }}">
            <img src="{{ asset(config('site.logo')) }}" alt="" width="44" height="44" class="size-11 shrink-0">
            <span class="ml-2.5 font-display text-xl font-semibold leading-none tracking-tight">{{ config('site.name') }}</span>
        </a>

        <nav aria-label="{{ __('Navigasi utama') }}" class="hidden items-center gap-8 text-sm font-semibold text-ink-soft lg:flex">
            @foreach($navLinks as $label => $href)
                <a href="{{ $href }}" class="inline-flex min-h-11 items-center transition hover:text-forest-900">{{ $label }}</a>
            @endforeach
        </nav>

        <div class="flex items-center gap-3">
            {{-- Smooth elegant language dropdown --}}
            <details class="group relative">
                <summary class="flex cursor-pointer list-none items-center gap-2 rounded-full border border-sand bg-cream/90 px-3 py-1.5 text-xs font-semibold text-forest-900 shadow-sm backdrop-blur transition hover:border-sand-dark hover:bg-cream [&::-webkit-details-marker]:hidden" aria-label="Ganti bahasa / Switch language">
                    <span class="text-forest-800/80">
                        <svg class="size-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <circle cx="12" cy="12" r="10"/>
                            <path d="M12 2a14.5 14.5 0 0 0 0 20M12 2a14.5 14.5 0 0 1 0 20M2 12h20"/>
                        </svg>
                    </span>
                    <span class="tracking-wide">{{ $currentLocale === 'en' ? 'English (EN)' : 'Indonesia (ID)' }}</span>
                    <svg class="size-3.5 text-forest-800/70 transition-transform duration-200 group-open:rotate-180" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
                    </svg>
                </summary>

                <div class="absolute right-0 z-50 mt-2 w-48 overflow-hidden rounded-2xl border border-sand bg-cream/95 p-1.5 shadow-xl backdrop-blur-xl transition-all animate-in fade-in zoom-in-95">
                    <a href="{{ route('lang.switch', 'id') }}"
                       class="flex items-center justify-between rounded-xl px-3 py-2 text-xs font-semibold transition {{ $currentLocale === 'id' ? 'bg-forest-900 text-cream' : 'text-forest-900 hover:bg-cream-deep' }}">
                        <span class="flex items-center gap-2">
                            <span>Bahasa Indonesia</span>
                        </span>
                        @if($currentLocale === 'id')
                            <svg class="size-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                            </svg>
                        @endif
                    </a>

                    <a href="{{ route('lang.switch', 'en') }}"
                       class="flex items-center justify-between rounded-xl px-3 py-2 text-xs font-semibold transition {{ $currentLocale === 'en' ? 'bg-forest-900 text-cream' : 'text-forest-900 hover:bg-cream-deep' }}">
                        <span class="flex items-center gap-2">
                            <span>English</span>
                        </span>
                        @if($currentLocale === 'en')
                            <svg class="size-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                            </svg>
                        @endif
                    </a>
                </div>
            </details>

            <a href="{{ $findBookingHref }}" class="hidden min-h-11 items-center px-3 text-sm font-semibold text-ink-soft transition hover:text-forest-900 lg:inline-flex">{{ __('Cek booking') }}</a>
            <a href="{{ $home }}#cari" class="btn btn-primary hidden !px-5 sm:inline-flex">{{ __('Cek tanggal') }}</a>

            <details class="group relative lg:hidden">
                <summary class="flex size-11 cursor-pointer list-none items-center justify-center rounded-full border border-sand-dark text-forest-900 [&::-webkit-details-marker]:hidden" aria-label="{{ __('Buka menu') }}">
                    <svg class="size-6 group-open:hidden" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16"/></svg>
                    <svg class="hidden size-6 group-open:block" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg>
                </summary>
                <nav aria-label="{{ __('Navigasi seluler') }}" class="absolute right-0 mt-2 w-60 rounded-2xl border border-sand bg-cream p-2 shadow-xl">
                    @foreach($navLinks as $label => $href)
                        <a href="{{ $href }}" class="block rounded-xl px-4 py-3 font-semibold text-forest-900 hover:bg-cream-deep">{{ $label }}</a>
                    @endforeach
                    <a href="{{ $findBookingHref }}" class="mt-1 block rounded-xl border-t border-sand px-4 py-3 font-semibold text-ink-soft hover:bg-cream-deep">{{ __('Cek booking') }}</a>
                    <a href="{{ $home }}#cari" class="btn btn-primary mt-1 w-full">{{ __('Cek tanggal') }}</a>
                </nav>
            </details>
        </div>
    </div>
</header>
