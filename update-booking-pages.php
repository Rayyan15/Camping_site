<?php

$navbar = <<<'HTML'
    <!-- Navbar Minimalis -->
    <nav class="bg-gray-950 shadow-sm w-full z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-20 items-center">
                <a href="/" class="flex items-center text-white hover:text-emerald-400 transition gap-2">
                    <svg class="h-6 w-6 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    <span class="font-semibold text-sm tracking-wider uppercase">Kembali ke Beranda</span>
                </a>
                <div class="flex items-center gap-2 text-white">
                    <svg class="w-8 h-8 text-emerald-400" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2L2 22h20L12 2zm0 4.5l6.5 13h-13L12 6.5z"/></svg>
                    <span class="font-bold text-xl tracking-tight">Raynad</span>
                </div>
            </div>
        </div>
    </nav>
HTML;

$head = <<<'HTML'
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #FAFAFA; }
        .glass-panel { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); }
    </style>
HTML;

// 1. tenda-detail.blade.php
$tendaDetail = <<<HTML
<!DOCTYPE html>
<html lang="id">
<head>
    <title>Detail {{ \$unitType->name }} - Raynad Camping</title>
$head
</head>
<body class="text-gray-800 antialiased selection:bg-emerald-500 selection:text-white">
$navbar
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div class="bg-white rounded-3xl shadow-xl border border-gray-100 overflow-hidden lg:flex">
            <!-- Galeri Foto -->
            <div class="lg:w-1/2 relative">
                @if(\$unitType->photos && \$unitType->photos->count() > 0)
                    <img src="{{ asset('storage/'.\$unitType->photos->first()->path) }}" alt="{{ \$unitType->name }}" class="w-full h-full object-cover min-h-[500px]">
                @else
                    <img src="https://images.unsplash.com/photo-1523987355523-c7b5b0dd90a7?w=800&q=80" alt="Placeholder Tenda" class="w-full h-full object-cover min-h-[500px]">
                @endif
                <div class="absolute top-6 right-6 bg-white/90 backdrop-blur text-gray-900 text-xs font-bold px-4 py-2 rounded-full flex items-center gap-1 shadow-lg">
                    <svg class="w-4 h-4 text-emerald-500" fill="currentColor" viewBox="0 0 20 20"><path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z"></path></svg>
                    Maks. {{ \$unitType->capacity }} Orang
                </div>
            </div>

            <!-- Detail Info & Form Booking -->
            <div class="p-10 lg:w-1/2 flex flex-col justify-between bg-white">
                <div>
                    <div class="flex flex-col sm:flex-row justify-between items-start gap-4">
                        <div>
                            <h1 class="text-4xl font-extrabold text-gray-900 tracking-tight">{{ \$unitType->name }}</h1>
                            <p class="text-emerald-600 font-semibold mt-2 text-sm uppercase tracking-wider">Premium Lodge</p>
                        </div>
                        <div class="sm:text-right">
                            <p class="text-xs text-gray-400 uppercase tracking-widest font-bold mb-1">Mulai Dari</p>
                            <p class="text-3xl font-extrabold text-emerald-600">Rp {{ number_format(\$unitType->base_price_weekday, 0, ',', '.') }}<span class="text-sm text-gray-400 font-normal">/mlm</span></p>
                        </div>
                    </div>

                    <div class="mt-8 text-gray-600 font-light leading-relaxed">
                        <p>{{ \$unitType->description ?? 'Nikmati pengalaman menginap nyaman di tenda eksklusif dengan pemandangan alam memukau. Cocok untuk keluarga dan pasangan yang mendambakan ketenangan tanpa mengorbankan kemewahan.' }}</p>
                    </div>

                    <div class="mt-10">
                        <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider mb-4 border-b pb-2">Fasilitas Termasuk</h3>
                        <div class="grid grid-cols-2 gap-4">
                            @if(is_array(\$unitType->facilities))
                                @foreach(\$unitType->facilities as \$fasilitas)
                                <div class="flex items-center text-sm font-medium text-gray-700">
                                    <div class="w-8 h-8 rounded-full bg-emerald-50 flex items-center justify-center mr-3">
                                        <svg class="h-4 w-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    </div>
                                    {{ \$fasilitas }}
                                </div>
                                @endforeach
                            @else
                                <div class="flex items-center text-sm font-medium text-gray-700"><div class="w-8 h-8 rounded-full bg-emerald-50 flex items-center justify-center mr-3"><svg class="h-4 w-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg></div>Kasur Busa Premium</div>
                                <div class="flex items-center text-sm font-medium text-gray-700"><div class="w-8 h-8 rounded-full bg-emerald-50 flex items-center justify-center mr-3"><svg class="h-4 w-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg></div>Kamar Mandi Dalam</div>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="mt-12 bg-gray-50 rounded-2xl p-6 border border-gray-100 shadow-inner">
                    <form action="/booking/cek" method="GET" class="space-y-5">
                        <input type="hidden" name="unit_type_id" value="{{ \$unitType->id }}">
                        
                        <div class="grid grid-cols-2 gap-5">
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Check-in</label>
                                <input type="date" name="check_in" value="{{ request('check_in', \$today ?? date('Y-m-d')) }}" min="{{ \$today ?? date('Y-m-d') }}" class="w-full border-0 bg-white rounded-xl px-4 py-3 text-gray-900 font-medium focus:ring-2 focus:ring-emerald-500 shadow-sm transition" required>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Check-out</label>
                                <input type="date" name="check_out" value="{{ request('check_out', \$tomorrow ?? date('Y-m-d', strtotime('+1 day'))) }}" min="{{ \$tomorrow ?? date('Y-m-d', strtotime('+1 day')) }}" class="w-full border-0 bg-white rounded-xl px-4 py-3 text-gray-900 font-medium focus:ring-2 focus:ring-emerald-500 shadow-sm transition" required>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Jumlah Tamu</label>
                            <input type="number" name="guests" min="1" max="{{ \$unitType->capacity + 2 }}" value="{{ request('guests', 2) }}" class="w-full border-0 bg-white rounded-xl px-4 py-3 text-gray-900 font-medium focus:ring-2 focus:ring-emerald-500 shadow-sm transition" required>
                            <p class="text-xs text-gray-400 mt-2 font-medium">Kapasitas dasar {{ \$unitType->capacity }} orang. Lebih dari itu wajib sewa Extra Bed.</p>
                        </div>

                        <button type="submit" class="w-full flex justify-center items-center gap-2 py-4 px-6 rounded-xl shadow-lg shadow-emerald-500/30 font-bold text-white bg-emerald-600 hover:bg-emerald-700 transition transform hover:-translate-y-0.5 mt-4">
                            Cek Ketersediaan & Pesan
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
HTML;
file_put_contents('resources/views/public/tenda-detail.blade.php', $tendaDetail);

// 2. booking-cek.blade.php
$bookingCek = <<<HTML
<!DOCTYPE html>
<html lang="id">
<head>
    <title>Selesaikan Pesanan - {{ \$unitType->name }}</title>
$head
</head>
<body class="text-gray-800 antialiased selection:bg-emerald-500 selection:text-white">
$navbar
    <div class="max-w-4xl mx-auto px-4 py-12">
        <div class="flex items-center gap-4 mb-8">
            <div class="w-12 h-12 bg-emerald-100 text-emerald-600 rounded-full flex items-center justify-center font-bold text-xl">2</div>
            <div>
                <h1 class="text-3xl font-extrabold text-gray-900 tracking-tight">Lengkapi Detail Pemesanan</h1>
                <p class="text-gray-500 mt-1 font-medium">Langkah terakhir sebelum liburan impian Anda.</p>
            </div>
        </div>
        
        @if(session('error'))
            <div class="bg-red-50 text-red-700 p-4 rounded-xl mb-8 border border-red-200 flex items-center gap-3 font-medium">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                {{ session('error') }}
            </div>
        @endif

        @if(\$isAvailable)
            <div class="grid md:grid-cols-3 gap-8">
                <!-- Form -->
                <div class="md:col-span-2">
                    <form action="{{ route('booking.store') }}" method="POST" class="bg-white rounded-3xl shadow-xl border border-gray-100 p-8">
                        @csrf
                        <input type="hidden" name="unit_type_id" value="{{ \$unitType->id }}">
                        <input type="hidden" name="check_in" value="{{ \$request->check_in }}">
                        <input type="hidden" name="check_out" value="{{ \$request->check_out }}">
                        <input type="hidden" name="guests" value="{{ \$request->guests }}">
                        
                        <div class="bg-emerald-50 text-emerald-700 px-5 py-3 rounded-xl mb-8 border border-emerald-100 font-semibold flex items-center gap-3">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                            Kabar Baik! Tersedia {{ \$availableUnits->count() }} unit untuk tanggal tersebut.
                        </div>

                        <h3 class="font-extrabold text-lg text-gray-900 mb-5 border-b pb-2">Pilih Unit Spesifik (Opsional)</h3>
                        <div class="space-y-3 mb-8">
                            @foreach(\$availableUnits as \$unit)
                                <label class="flex items-center space-x-4 bg-gray-50 p-4 rounded-xl border border-gray-200 cursor-pointer hover:bg-emerald-50 transition hover:border-emerald-200">
                                    <input type="checkbox" name="unit_ids[]" value="{{ \$unit->id }}" class="h-5 w-5 text-emerald-600 border-gray-300 rounded focus:ring-emerald-500" {{ \$loop->first ? 'checked' : '' }}>
                                    <span class="text-base font-bold text-gray-900">{{ \$unit->name }}</span>
                                </label>
                            @endforeach
                        </div>

                        <h3 class="font-extrabold text-lg text-gray-900 mb-5 border-b pb-2">Informasi Pemesan</h3>
                        <div class="space-y-5 mb-8">
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Nama Lengkap</label>
                                <input type="text" name="customer_name" class="w-full border-0 bg-gray-50 rounded-xl px-4 py-3 text-gray-900 font-medium focus:ring-2 focus:ring-emerald-500 transition shadow-sm" required>
                            </div>
                            <div class="grid grid-cols-2 gap-5">
                                <div>
                                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Email</label>
                                    <input type="email" name="customer_email" class="w-full border-0 bg-gray-50 rounded-xl px-4 py-3 text-gray-900 font-medium focus:ring-2 focus:ring-emerald-500 transition shadow-sm" required>
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">No. WhatsApp</label>
                                    <input type="text" name="customer_phone" class="w-full border-0 bg-gray-50 rounded-xl px-4 py-3 text-gray-900 font-medium focus:ring-2 focus:ring-emerald-500 transition shadow-sm" required>
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-4 px-6 rounded-xl transition shadow-lg shadow-emerald-500/30 text-lg flex justify-center items-center gap-2">
                            Konfirmasi & Lanjut Pembayaran
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                        </button>
                    </form>
                </div>

                <!-- Summary Card -->
                <div class="md:col-span-1">
                    <div class="bg-gray-900 text-white rounded-3xl p-6 shadow-2xl sticky top-6">
                        <h3 class="font-bold text-lg mb-4 border-b border-gray-700 pb-3">Ringkasan Pesanan</h3>
                        
                        <div class="mb-4">
                            <p class="text-gray-400 text-xs font-bold uppercase tracking-wider mb-1">Tipe Tenda</p>
                            <p class="font-semibold text-lg text-emerald-400">{{ \$unitType->name }}</p>
                        </div>
                        
                        <div class="mb-4">
                            <p class="text-gray-400 text-xs font-bold uppercase tracking-wider mb-1">Jadwal Inap</p>
                            <p class="font-medium text-sm">{{ \Carbon\Carbon::parse(\$request->check_in)->format('d M Y') }} - {{ \Carbon\Carbon::parse(\$request->check_out)->format('d M Y') }}</p>
                        </div>
                        
                        <div class="mb-6">
                            <p class="text-gray-400 text-xs font-bold uppercase tracking-wider mb-1">Total Tamu</p>
                            <p class="font-medium text-sm">{{ \$request->guests }} Orang</p>
                        </div>

                        <div class="bg-white/10 rounded-xl p-4 backdrop-blur-sm">
                            <p class="text-xs text-gray-300 font-bold uppercase tracking-wider mb-1">Estimasi Total</p>
                            <p class="text-2xl font-extrabold text-white">Rp {{ number_format(\$unitType->base_price_weekday, 0, ',', '.') }}<span class="text-sm font-normal text-gray-400">/mlm</span></p>
                        </div>
                        <p class="text-xs text-gray-500 mt-4 text-center">Harga akhir beserta pajak akan dikalkulasi di halaman pembayaran.</p>
                    </div>
                </div>
            </div>
        @else
            <div class="bg-white rounded-3xl shadow-xl border border-gray-100 p-12 text-center max-w-lg mx-auto mt-12">
                <div class="w-24 h-24 bg-red-50 rounded-full flex items-center justify-center mx-auto mb-6">
                    <svg class="h-12 w-12 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                </div>
                <h3 class="text-2xl font-extrabold text-gray-900 mb-3">Tenda Sudah Penuh</h3>
                <p class="text-gray-500 mb-8 font-medium">Mohon maaf, tidak ada tenda <b>{{ \$unitType->name }}</b> yang tersedia di tanggal yang Anda pilih. Silakan cari tipe tenda lain atau ubah jadwal liburan Anda.</p>
                <a href="{{ route('home') }}" class="inline-flex items-center gap-2 bg-gray-900 text-white font-bold py-3 px-8 rounded-full hover:bg-emerald-600 transition shadow-lg">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                    Cari Tanggal Lain
                </a>
            </div>
        @endif
    </div>
</body>
</html>
HTML;
file_put_contents('resources/views/public/booking-cek.blade.php', $bookingCek);

// 3. booking-success.blade.php
$bookingSuccess = <<<HTML
<!DOCTYPE html>
<html lang="id">
<head>
    <title>Booking Berhasil - Raynad Camping</title>
$head
</head>
<body class="bg-gray-50 text-gray-800 antialiased font-['Plus_Jakarta_Sans'] min-h-screen flex flex-col">
$navbar
    <div class="flex-grow flex items-center justify-center p-4 py-12">
        <div class="bg-white rounded-3xl shadow-xl border border-gray-100 p-8 md:p-12 max-w-2xl w-full text-center relative overflow-hidden">
            <!-- Decorative background blob -->
            <div class="absolute -top-24 -right-24 w-48 h-48 bg-emerald-400 rounded-full mix-blend-multiply filter blur-3xl opacity-20"></div>
            
            <div class="relative z-10">
                <div class="w-24 h-24 bg-emerald-100 rounded-full flex items-center justify-center mx-auto mb-6 border-4 border-white shadow-lg">
                    <svg class="h-12 w-12 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                </div>
                
                <h1 class="text-3xl font-extrabold text-gray-900 mb-2">Booking Berhasil Dibuat!</h1>
                <p class="text-gray-500 mb-8 font-medium">Terima kasih, {{ \$booking->customer->name }}. Kode booking Anda adalah:</p>
                
                <div class="bg-gray-900 text-white py-4 px-8 rounded-2xl inline-block mb-10 shadow-xl transform hover:scale-105 transition cursor-default">
                    <span class="text-4xl font-extrabold tracking-widest font-mono text-emerald-400">{{ \$booking->code }}</span>
                </div>
                
                <div class="bg-emerald-50 rounded-2xl p-6 text-left mb-8 border border-emerald-100">
                    <h3 class="font-bold text-gray-900 mb-4 border-b border-emerald-200 pb-2">Instruksi Pembayaran</h3>
                    <p class="text-sm text-gray-700 mb-4 leading-relaxed font-medium">
                        Total yang harus dibayar: <strong class="text-emerald-700 text-lg">Rp {{ number_format(\$booking->total, 0, ',', '.') }}</strong>
                    </p>
                    <p class="text-sm text-gray-600 leading-relaxed">
                        Silakan lakukan pembayaran agar booking Anda otomatis terkonfirmasi. Karena ini mode infrastruktur payment, jika gateway belum aktif secara penuh, Anda dapat mengonfirmasi via WhatsApp.
                    </p>
                </div>
                
                <div class="flex flex-col sm:flex-row gap-4 justify-center">
                    <button onclick="alert('Midtrans Snap Pop-up akan muncul di sini!')" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-3 px-8 rounded-full transition shadow-lg shadow-emerald-500/30 flex items-center justify-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path></svg>
                        Bayar Sekarang
                    </button>
                    <a href="/" class="bg-white border-2 border-gray-200 text-gray-700 font-bold py-3 px-8 rounded-full hover:bg-gray-50 hover:border-gray-300 transition text-center">
                        Kembali ke Beranda
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
HTML;
file_put_contents('resources/views/public/booking-success.blade.php', $bookingSuccess);

echo "Booking pages updated successfully.\n";
