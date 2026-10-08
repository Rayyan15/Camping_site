@php
    $home = route('home');
    $navLinks = [
        'Tenda' => $home.'#tenda',
        'Cara pesan' => $home.'#cara-pesan',
        'Fasilitas' => $home.'#fasilitas',
        'FAQ' => $home.'#faq',
    ];
@endphp
<header class="sticky top-0 z-50 border-b border-sand/70 bg-cream/95 backdrop-blur">
    <div class="mx-auto flex h-16 max-w-6xl items-center justify-between gap-4 px-4 sm:px-6">
        <a href="{{ $home }}" class="flex items-center text-forest-900" aria-label="{{ config('site.name') }}, beranda">
            <span class="font-display text-2xl font-semibold tracking-tight">Raynad</span>
        </a>

        <nav aria-label="Navigasi utama" class="hidden items-center gap-8 text-sm font-semibold text-ink-soft md:flex">
            @foreach($navLinks as $label => $href)
                <a href="{{ $href }}" class="py-2 transition hover:text-forest-900">{{ $label }}</a>
            @endforeach
        </nav>

        <div class="flex items-center gap-2">
            <a href="{{ $home }}#cari" class="btn btn-primary hidden !min-h-10 !px-5 sm:inline-flex">Cek tanggal</a>

            <details class="group relative md:hidden">
                <summary class="flex size-11 cursor-pointer list-none items-center justify-center rounded-full border border-sand text-forest-900 [&::-webkit-details-marker]:hidden" aria-label="Buka menu">
                    <svg class="size-6 group-open:hidden" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16"/></svg>
                    <svg class="hidden size-6 group-open:block" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg>
                </summary>
                <nav aria-label="Navigasi seluler" class="absolute right-0 mt-2 w-60 rounded-2xl border border-sand bg-cream p-2 shadow-xl">
                    @foreach($navLinks as $label => $href)
                        <a href="{{ $href }}" class="block rounded-xl px-4 py-3 font-semibold text-forest-900 hover:bg-cream-deep">{{ $label }}</a>
                    @endforeach
                    <a href="{{ $home }}#cari" class="btn btn-primary mt-1 w-full">Cek tanggal</a>
                </nav>
            </details>
        </div>
    </div>
</header>
