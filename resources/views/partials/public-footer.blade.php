@php
    $whatsapp = config('site.whatsapp_number');
    $address = config('site.address');
    $mapsUrl = config('site.maps_url');
    $instagram = config('site.instagram_url');
@endphp
<footer class="bg-forest-950 text-forest-300">
    <div class="mx-auto grid max-w-6xl gap-10 px-4 py-14 sm:px-6 md:grid-cols-[1.4fr_1fr_1fr]">
        <div>
            <p class="font-display text-2xl font-semibold text-cream">{{ config('site.name') }}</p>
            <p class="mt-3 max-w-sm text-sm leading-relaxed">32 unit dalam 7 tipe tenda. Pesan online, bayar online, dan pesan makanan dari resto atau coffeeshop lewat QR di tenda.</p>
        </div>

        <nav aria-label="Tautan footer">
            <p class="font-display text-lg italic text-cream">Jelajahi</p>
            <ul class="mt-4 space-y-2 text-sm">
                <li><a class="inline-block py-2 hover:text-cream" href="{{ route('home') }}#tenda">Tipe tenda</a></li>
                <li><a class="inline-block py-2 hover:text-cream" href="{{ route('home') }}#cara-pesan">Cara pesan</a></li>
                <li><a class="inline-block py-2 hover:text-cream" href="{{ route('home') }}#fasilitas">Fasilitas</a></li>
                <li><a class="inline-block py-2 hover:text-cream" href="{{ route('home') }}#faq">Pertanyaan umum</a></li>
                <li><a class="inline-block py-2 hover:text-cream" href="{{ route('booking.find') }}">Cek status booking</a></li>            </ul>
        </nav>

        <div>
            <p class="font-display text-lg italic text-cream">Kontak</p>
            <ul class="mt-4 space-y-2 text-sm">
                @if($address)<li>{{ $address }}</li>@endif
                @if($mapsUrl)<li><a class="underline underline-offset-4 hover:text-cream" href="{{ $mapsUrl }}" target="_blank" rel="noopener">Lihat di peta</a></li>@endif
                @if($whatsapp)<li><a class="underline underline-offset-4 hover:text-cream" href="https://wa.me/{{ $whatsapp }}" target="_blank" rel="noopener">WhatsApp</a></li>@endif
                @if($instagram)<li><a class="underline underline-offset-4 hover:text-cream" href="{{ $instagram }}" target="_blank" rel="noopener">Instagram</a></li>@endif
                @if(! $address && ! $mapsUrl && ! $whatsapp && ! $instagram)<li>Kontak segera tersedia.</li>@endif
            </ul>
        </div>
    </div>
    <div class="border-t border-forest-800">
        <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-4 px-4 py-4 sm:px-6">
            <p class="text-xs">&copy; {{ now()->year }} {{ config('site.name') }}. Seluruh hak dilindungi.</p>
            {{-- Staff entry lives here, away from the booking actions guests use. --}}
            <a href="{{ route('filament.admin.auth.login') }}" class="btn btn-outline-light !min-h-11 !px-5 !text-sm">Login Admin</a>
        </div>
    </div>
</footer>
