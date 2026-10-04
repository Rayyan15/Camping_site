<!DOCTYPE html>
<html lang="id">
<head>
    <title>Detail {{ $unitType->name }} - Raynad Camping</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #FAFAFA; }
        .glass-panel { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); }
    </style>
</head>
<body class="text-gray-800 antialiased selection:bg-emerald-500 selection:text-white">
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
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div class="bg-white rounded-3xl shadow-xl border border-gray-100 overflow-hidden lg:flex">
            <!-- Galeri Foto -->
            <div class="lg:w-1/2 relative">
                @if($unitType->photos && $unitType->photos->count() > 0)
                    <img src="{{ asset('storage/'.$unitType->photos->first()->path) }}" alt="{{ $unitType->name }}" class="w-full h-full object-cover min-h-[500px]">
                @else
                    <img src="https://images.unsplash.com/photo-1523987355523-c7b5b0dd90a7?w=800&q=80" alt="Placeholder Tenda" class="w-full h-full object-cover min-h-[500px]">
                @endif
                <div class="absolute top-6 right-6 bg-white/90 backdrop-blur text-gray-900 text-xs font-bold px-4 py-2 rounded-full flex items-center gap-1 shadow-lg">
                    <svg class="w-4 h-4 text-emerald-500" fill="currentColor" viewBox="0 0 20 20"><path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z"></path></svg>
                    Maks. {{ $unitType->capacity }} Orang
                </div>
            </div>

            <!-- Detail Info & Form Booking -->
            <div class="p-10 lg:w-1/2 flex flex-col justify-between bg-white">
                <div>
                    <div class="flex flex-col sm:flex-row justify-between items-start gap-4">
                        <div>
                            <h1 class="text-4xl font-extrabold text-gray-900 tracking-tight">{{ $unitType->name }}</h1>
                            <p class="text-emerald-600 font-semibold mt-2 text-sm uppercase tracking-wider">Premium Lodge</p>
                        </div>
                        <div class="sm:text-right">
                            <p class="text-xs text-gray-400 uppercase tracking-widest font-bold mb-1">Mulai Dari</p>
                            <p class="text-3xl font-extrabold text-emerald-600">Rp {{ number_format($unitType->base_price_weekday, 0, ',', '.') }}<span class="text-sm text-gray-400 font-normal">/mlm</span></p>
                        </div>
                    </div>

                    <div class="mt-8 text-gray-600 font-light leading-relaxed">
                        <p>{{ $unitType->description ?? 'Nikmati pengalaman menginap nyaman di tenda eksklusif dengan pemandangan alam memukau. Cocok untuk keluarga dan pasangan yang mendambakan ketenangan tanpa mengorbankan kemewahan.' }}</p>
                    </div>

                    <div class="mt-10">
                        <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider mb-4 border-b pb-2">Fasilitas Termasuk</h3>
                        <div class="grid grid-cols-2 gap-4">
                            @if(is_array($unitType->facilities))
                                @foreach($unitType->facilities as $fasilitas)
                                <div class="flex items-center text-sm font-medium text-gray-700">
                                    <div class="w-8 h-8 rounded-full bg-emerald-50 flex items-center justify-center mr-3">
                                        <svg class="h-4 w-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    </div>
                                    {{ $fasilitas }}
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
                        <input type="hidden" name="unit_type_id" value="{{ $unitType->id }}">
                        
                        <div class="grid grid-cols-2 gap-5">
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Check-in</label>
                                <input type="date" name="check_in" value="{{ request('check_in', $today ?? date('Y-m-d')) }}" min="{{ $today ?? date('Y-m-d') }}" class="w-full border-0 bg-white rounded-xl px-4 py-3 text-gray-900 font-medium focus:ring-2 focus:ring-emerald-500 shadow-sm transition" required>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Check-out</label>
                                <input type="date" name="check_out" value="{{ request('check_out', $tomorrow ?? date('Y-m-d', strtotime('+1 day'))) }}" min="{{ $tomorrow ?? date('Y-m-d', strtotime('+1 day')) }}" class="w-full border-0 bg-white rounded-xl px-4 py-3 text-gray-900 font-medium focus:ring-2 focus:ring-emerald-500 shadow-sm transition" required>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Jumlah Tamu</label>
                            <input type="number" name="guests" min="1" max="{{ $unitType->capacity + 2 }}" value="{{ request('guests', 2) }}" class="w-full border-0 bg-white rounded-xl px-4 py-3 text-gray-900 font-medium focus:ring-2 focus:ring-emerald-500 shadow-sm transition" required>
                            <p class="text-xs text-gray-400 mt-2 font-medium">Kapasitas dasar {{ $unitType->capacity }} orang. Lebih dari itu wajib sewa Extra Bed.</p>
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