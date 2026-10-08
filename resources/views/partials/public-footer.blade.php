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
            <p class="eyebrow text-cream">Jelajahi</p>
            <ul class="mt-4 space-y-2 text-sm">
                <li><a class="hover:text-cream" href="{{ route('home') }}#tenda">Tipe tenda</a></li>
                <li><a class="hover:text-cream" href="{{ route('home') }}#cara-pesan">Cara pesan</a></li>
                <li><a class="hover:text-cream" href="{{ route('home') }}#fasilitas">Fasilitas</a></li>
                <li><a class="hover:text-cream" href="{{ route('home') }}#faq">Pertanyaan umum</a></li>
            </ul>
        </nav>

        <div>
            <p class="eyebrow text-cream">Kontak</p>
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
        <p class="mx-auto max-w-6xl px-4 py-5 text-xs sm:px-6">&copy; {{ now()->year }} {{ config('site.name') }}. Seluruh hak dilindungi.</p>
    </div>
</footer>
