<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Raynad Hospitality Camping</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-gray-50 text-gray-800 antialiased selection:bg-teal-600 selection:text-white">

    <!-- Navbar -->
    <nav x-data="{ open: false }" class="bg-white/90 backdrop-blur-md shadow-sm fixed w-full z-50 transition-all">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16 items-center">
                <div class="flex-shrink-0 flex items-center">
                    <span class="font-bold text-xl text-teal-800 tracking-tight">Raynad Camping</span>
                </div>
                <div class="hidden sm:ml-6 sm:flex sm:space-x-8">
                    <a href="#profil" class="border-transparent text-gray-600 hover:text-teal-600 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium transition">Profil</a>
                    <a href="#tenda" class="border-transparent text-gray-600 hover:text-teal-600 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium transition">Tipe Tenda</a>
                    <a href="#fasilitas" class="border-transparent text-gray-600 hover:text-teal-600 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium transition">Fasilitas</a>
                    <a href="#lokasi" class="border-transparent text-gray-600 hover:text-teal-600 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium transition">Lokasi</a>
                </div>
                <div class="hidden sm:flex items-center space-x-4">
                    <a href="/admin/login" class="text-teal-700 hover:text-teal-900 font-medium text-sm transition">Login Staf</a>
                    <a href="#tenda" class="bg-teal-700 hover:bg-teal-800 text-white px-5 py-2 rounded-md font-medium text-sm transition shadow-sm">Cek Ketersediaan</a>
                </div>
                <!-- Mobile menu button -->
                <div class="-mr-2 flex items-center sm:hidden">
                    <button @click="open = !open" type="button" class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-teal-500" aria-controls="mobile-menu" aria-expanded="false">
                        <span class="sr-only">Buka menu utama</span>
                        <svg class="h-6 w-6" x-show="!open" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                        <svg class="h-6 w-6" x-show="open" style="display: none;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>
        <!-- Mobile Menu -->
        <div x-show="open" class="sm:hidden border-b border-gray-200 bg-white" id="mobile-menu" style="display: none;">
            <div class="pt-2 pb-3 space-y-1">
                <a href="#profil" class="block pl-3 pr-4 py-2 text-base font-medium text-gray-600 hover:text-teal-800 hover:bg-teal-50">Profil</a>
                <a href="#tenda" class="block pl-3 pr-4 py-2 text-base font-medium text-gray-600 hover:text-teal-800 hover:bg-teal-50">Tipe Tenda</a>
                <a href="#fasilitas" class="block pl-3 pr-4 py-2 text-base font-medium text-gray-600 hover:text-teal-800 hover:bg-teal-50">Fasilitas</a>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <div class="relative bg-gray-900 overflow-hidden">
        <div class="absolute inset-0">
            <img class="w-full h-full object-cover opacity-60" src="https://images.unsplash.com/photo-1523987355523-c7b5b0dd90a7?ixlib=rb-4.0.3&auto=format&fit=crop&w=1920&q=80" alt="Pemandangan camping di alam terbuka">
            <div class="absolute inset-0 bg-gradient-to-t from-gray-900/90 via-gray-900/40 to-transparent"></div>
        </div>
        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-32 lg:py-48 flex flex-col items-center text-center">
            <span class="text-teal-400 font-semibold tracking-wider uppercase text-sm mb-4">Pengalaman Menginap di Alam Bebas</span>
            <h1 class="text-4xl tracking-tight font-extrabold text-white sm:text-5xl md:text-6xl lg:text-7xl mb-6">
                Raynad <span class="text-teal-400">Hospitality</span> Camping
            </h1>
            <p class="mt-4 max-w-2xl text-lg text-gray-200 sm:text-xl">
                Menyediakan 32 unit tenda berbagai tipe untuk kebutuhan liburan Anda, lengkap dengan fasilitas restoran dan layanan pre-order. Nikmati keindahan alam tanpa repot.
            </p>
            <div class="mt-10 max-w-sm sm:max-w-none flex justify-center gap-4">
                <a href="#tenda" class="flex items-center justify-center px-8 py-3 border border-transparent text-base font-medium rounded-md text-white bg-teal-600 hover:bg-teal-700 md:py-4 md:text-lg md:px-10 transition">
                    Lihat Tipe Tenda
                </a>
            </div>
        </div>
    </div>

    <!-- Profil Singkat -->
    <section id="profil" class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="lg:grid lg:grid-cols-2 lg:gap-16 items-center">
                <div>
                    <h2 class="text-3xl font-extrabold text-gray-900 sm:text-4xl mb-6">Fasilitas Lengkap untuk Liburan Anda</h2>
                    <p class="text-lg text-gray-600 mb-6 leading-relaxed">
                        Raynad Camping berlokasi di dataran tinggi dengan udara sejuk, menawarkan 7 tipe tenda yang bisa disesuaikan dengan kebutuhan mulai dari rombongan kecil hingga grup. 
                    </p>
                    <p class="text-lg text-gray-600 leading-relaxed mb-8">
                        Fasilitas mencakup area parkir luas, restoran / coffeeshop di dalam area, kamar mandi umum bersih dengan air hangat, dan keamanan 24 jam. Anda juga bisa langsung memesan makanan via QR code dari tenda Anda.
                    </p>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="flex items-center text-gray-700">
                            <svg class="h-6 w-6 text-teal-600 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            Resto & Coffee Shop
                        </div>
                        <div class="flex items-center text-gray-700">
                            <svg class="h-6 w-6 text-teal-600 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            Kamar Mandi Air Hangat
                        </div>
                        <div class="flex items-center text-gray-700">
                            <svg class="h-6 w-6 text-teal-600 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            Area Parkir Luas
                        </div>
                        <div class="flex items-center text-gray-700">
                            <svg class="h-6 w-6 text-teal-600 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            QR Order Makanan
                        </div>
                    </div>
                </div>
                <div class="mt-12 lg:mt-0">
                    <img class="rounded-xl shadow-xl object-cover w-full h-[400px]" src="https://images.unsplash.com/photo-1504280067389-9b9bb20ab20e?ixlib=rb-4.0.3&auto=format&fit=crop&w=1000&q=80" alt="Fasilitas api unggun dan tenda">
                </div>
            </div>
        </div>
    </section>

    <!-- Tipe Tenda -->
    <section id="tenda" class="py-20 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-3xl font-extrabold text-gray-900 sm:text-4xl">Pilihan Tipe Tenda</h2>
                <p class="mt-4 text-xl text-gray-600">Total 32 unit tersedia. Pilih tenda yang sesuai dengan jumlah rombongan Anda.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                @if(isset($unitTypes) && $unitTypes->count() > 0)
                    @foreach($unitTypes as $type)
                    <div class="bg-white rounded-xl overflow-hidden shadow-sm hover:shadow-md transition border border-gray-100 flex flex-col">
                        <div class="h-48 bg-gray-200 relative">
                            <!-- Placeholder image if no photo -->
                            <img src="https://images.unsplash.com/photo-153756526675b-34abc6599aa2?ixlib=rb-4.0.3&auto=format&fit=crop&w=600&q=80" alt="{{ $type->name }}" class="w-full h-full object-cover">
                            <div class="absolute top-4 right-4 bg-white/90 backdrop-blur text-gray-800 text-xs font-bold px-3 py-1 rounded-full shadow-sm">
                                Kapasitas {{ $type->capacity }} Orang
                            </div>
                        </div>
                        <div class="p-6 flex-1 flex flex-col">
                            <h3 class="text-xl font-bold text-gray-900 mb-2">{{ $type->name }}</h3>
                            <p class="text-gray-600 text-sm mb-4 flex-1 line-clamp-3">{{ $type->description ?? 'Tenda nyaman untuk menginap bersama keluarga.' }}</p>
                            
                            <div class="flex flex-wrap gap-2 mb-6">
                                @if(is_array($type->facilities))
                                    @foreach(array_slice($type->facilities, 0, 3) as $fasilitas)
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-md text-xs font-medium bg-gray-100 text-gray-700">
                                            {{ $fasilitas }}
                                        </span>
                                    @endforeach
                                    @if(count($type->facilities) > 3)
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-md text-xs font-medium bg-gray-100 text-gray-500">
                                            +{{ count($type->facilities) - 3 }} lainnya
                                        </span>
                                    @endif
                                @endif
                            </div>

                            <div class="flex justify-between items-end mt-auto pt-4 border-t border-gray-100">
                                <div>
                                    <p class="text-xs text-gray-500 uppercase font-semibold tracking-wider">Mulai dari</p>
                                    <p class="text-xl font-bold text-teal-700">Rp {{ number_format($type->base_price_weekday, 0, ',', '.') }}<span class="text-sm text-gray-500 font-normal">/malam</span></p>
                                </div>
                                <a href="/tenda/{{ $type->slug }}" class="bg-gray-900 hover:bg-gray-800 text-white px-4 py-2 rounded text-sm font-medium transition">Detail</a>
                            </div>
                        </div>
                    </div>
                    @endforeach
                @else
                    <!-- Tampilan statis jika database kosong -->
                    <div class="col-span-full text-center py-12 bg-white rounded-xl border border-gray-100">
                        <p class="text-gray-500">Data tipe tenda belum tersedia di database. Menampilkan contoh format.</p>
                    </div>
                    
                    @php
                    // Hardcode data dari PRD jika DB kosong (Pancar 11, Safari 6, Salak 5, Indian 5, Romance 2, Snail 2, Dome 1)
                    $dummies = [
                        ['name' => 'Tenda Pancar', 'cap' => 4, 'price' => 350000, 'img' => 'https://images.unsplash.com/photo-1517824806704-9040b037703b?w=600&q=80'],
                        ['name' => 'Tenda Safari', 'cap' => 6, 'price' => 500000, 'img' => 'https://images.unsplash.com/photo-1523987355523-c7b5b0dd90a7?w=600&q=80'],
                        ['name' => 'Tenda Indian', 'cap' => 4, 'price' => 400000, 'img' => 'https://images.unsplash.com/photo-1478827536114-da961b7f86d2?w=600&q=80'],
                    ];
                    @endphp

                    @foreach($dummies as $dummy)
                    <div class="bg-white rounded-xl overflow-hidden shadow-sm border border-gray-100 flex flex-col">
                        <div class="h-48 relative">
                            <img src="{{ $dummy['img'] }}" class="w-full h-full object-cover">
                            <div class="absolute top-4 right-4 bg-white/90 backdrop-blur text-gray-800 text-xs font-bold px-3 py-1 rounded-full shadow-sm">
                                Kapasitas {{ $dummy['cap'] }} Orang
                            </div>
                        </div>
                        <div class="p-6 flex-1 flex flex-col">
                            <h3 class="text-xl font-bold text-gray-900 mb-2">{{ $dummy['name'] }}</h3>
                            <p class="text-gray-600 text-sm mb-4 flex-1">Tenda eksklusif dengan kasur busa tebal, bantal, selimut, dan colokan listrik. Nyaman untuk keluarga.</p>
                            
                            <div class="flex flex-wrap gap-2 mb-6">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-md text-xs font-medium bg-gray-100 text-gray-700">Matras Busa</span>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-md text-xs font-medium bg-gray-100 text-gray-700">Stop Kontak</span>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-md text-xs font-medium bg-gray-100 text-gray-700">Lampu</span>
                            </div>

                            <div class="flex justify-between items-end mt-auto pt-4 border-t border-gray-100">
                                <div>
                                    <p class="text-xs text-gray-500 uppercase font-semibold tracking-wider">Mulai dari</p>
                                    <p class="text-xl font-bold text-teal-700">Rp {{ number_format($dummy['price'], 0, ',', '.') }}<span class="text-sm text-gray-500 font-normal">/malam</span></p>
                                </div>
                                <button class="bg-gray-900 hover:bg-gray-800 text-white px-4 py-2 rounded text-sm font-medium transition">Detail</button>
                            </div>
                        </div>
                    </div>
                    @endforeach
                @endif
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-gray-900 pt-16 pb-8 border-t border-gray-800">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-12 mb-12">
                <div>
                    <span class="font-bold text-2xl text-white tracking-tight">Raynad Camping</span>
                    <p class="mt-4 text-gray-400 text-sm leading-relaxed">
                        PT Raynad Cipta Makmur.<br>
                        Pengalaman hospitality camping terbaik dengan fasilitas lengkap dan kemudahan order terintegrasi.
                    </p>
                </div>
                <div>
                    <h3 class="text-sm font-semibold text-gray-200 tracking-wider uppercase mb-4">Kontak & Lokasi</h3>
                    <ul class="space-y-3 text-sm text-gray-400">
                        <li class="flex items-start">
                            <svg class="h-5 w-5 text-gray-500 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            Jalan Raya Pegunungan No. 88,<br>Kawasan Wisata, Jawa Barat
                        </li>
                        <li class="flex items-center">
                            <svg class="h-5 w-5 text-gray-500 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                            0812-3456-7890
                        </li>
                    </ul>
                </div>
                <div>
                    <h3 class="text-sm font-semibold text-gray-200 tracking-wider uppercase mb-4">Menu Bantuan</h3>
                    <ul class="space-y-3 text-sm text-gray-400">
                        <li><a href="#" class="hover:text-white transition">Cara Booking</a></li>
                        <li><a href="#" class="hover:text-white transition">Kebijakan Refund</a></li>
                        <li><a href="#" class="hover:text-white transition">Syarat & Ketentuan</a></li>
                    </ul>
                </div>
            </div>
            <div class="border-t border-gray-800 pt-8 flex flex-col md:flex-row justify-between items-center">
                <p class="text-sm text-gray-500">
                    &copy; 2026 PT Raynad Cipta Makmur. All rights reserved.
                </p>
            </div>
        </div>
    </footer>

</body>
</html>
