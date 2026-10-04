<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Raynad Camping - Luxury Lodge & Tent Booking</title>
    @vite('resources/css/app.css')
    <!-- Google Fonts: Plus Jakarta Sans for a premium modern look -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: #FAFAFA;
        }
        .hero-bg {
            background-image: linear-gradient(to bottom, rgba(0, 0, 0, 0.3), rgba(0, 0, 0, 0.7)), url('https://images.unsplash.com/photo-1504280114156-f6c70a8d6dc6?q=80&w=2000&auto=format&fit=crop');
            background-size: cover;
            background-position: center;
        }
        .glass-panel {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
        }
    </style>
</head>
<body class="text-gray-800 antialiased selection:bg-emerald-500 selection:text-white">

    <!-- Navbar -->
    <nav class="absolute top-0 left-0 right-0 z-50 flex items-center justify-between px-8 py-6 text-white w-full max-w-7xl mx-auto">
        <div class="flex items-center gap-2">
            <svg class="w-8 h-8 text-emerald-400" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2L2 22h20L12 2zm0 4.5l6.5 13h-13L12 6.5z"/></svg>
            <span class="text-2xl font-bold tracking-tight">Raynad</span>
        </div>
        <div class="hidden md:flex items-center gap-8 font-medium text-sm">
            <a href="#" class="hover:text-emerald-300 transition">Destinations</a>
            <a href="#" class="hover:text-emerald-300 transition">Experiences</a>
            <a href="#" class="hover:text-emerald-300 transition">Gallery</a>
            <a href="#" class="hover:text-emerald-300 transition">Contact</a>
        </div>
        <div>
            <a href="/admin" class="bg-white/20 hover:bg-white/30 backdrop-blur-md text-white px-5 py-2.5 rounded-full text-sm font-semibold transition border border-white/30">
                Staff Login
            </a>
        </div>
    </nav>

    <!-- Hero Section -->
    <header class="hero-bg h-[85vh] relative flex flex-col items-center justify-center text-center px-4">
        <h1 class="text-5xl md:text-7xl font-extrabold text-white leading-tight max-w-4xl tracking-tight mb-6">
            Find Your Perfect <br><span class="text-emerald-400 font-serif italic font-normal">Wild Retreat</span>
        </h1>
        <p class="text-lg md:text-xl text-gray-200 max-w-2xl mb-12 font-light">
            Escape the ordinary. Experience luxury lodging in the heart of nature, tailored for your ultimate relaxation.
        </p>
    </header>

    <!-- Floating Booking Bar -->
    <div class="max-w-5xl mx-auto px-4 sm:px-6 relative -mt-16 z-20">
        <div class="glass-panel rounded-3xl shadow-2xl p-4 md:p-8 border border-gray-100">
            <form action="{{ route('home') }}" method="GET" class="flex flex-col md:flex-row gap-4 items-end">
                <div class="w-full md:w-1/3">
                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Check In</label>
                    <input type="date" name="check_in" value="{{ request('check_in', date('Y-m-d')) }}" class="w-full border-0 bg-gray-50 rounded-xl px-4 py-3.5 text-gray-900 font-medium focus:ring-2 focus:ring-emerald-500 transition">
                </div>
                <div class="w-full md:w-1/3">
                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Check Out</label>
                    <input type="date" name="check_out" value="{{ request('check_out', date('Y-m-d', strtotime('+1 day'))) }}" class="w-full border-0 bg-gray-50 rounded-xl px-4 py-3.5 text-gray-900 font-medium focus:ring-2 focus:ring-emerald-500 transition">
                </div>
                <div class="w-full md:w-1/4">
                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Guests</label>
                    <div class="relative">
                        <input type="number" name="guests" value="{{ request('guests', 2) }}" min="1" class="w-full border-0 bg-gray-50 rounded-xl px-4 py-3.5 text-gray-900 font-medium focus:ring-2 focus:ring-emerald-500 transition">
                        <div class="absolute right-4 top-3.5 text-gray-400">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                        </div>
                    </div>
                </div>
                <div class="w-full md:w-1/4">
                    <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-3.5 px-6 rounded-xl transition shadow-lg shadow-emerald-500/30 flex items-center justify-center gap-2">
                        <span>Check Availability</span>
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                    </button>
                </div>
            </form>
        </div>
    </div>

    @if(request()->has('check_in'))
    <!-- Booking Results Section -->
    <section class="max-w-7xl mx-auto px-4 sm:px-6 mt-12 mb-8">
        <div class="bg-emerald-50 border border-emerald-100 rounded-2xl p-6 flex flex-col sm:flex-row items-center justify-between">
            <div>
                <h3 class="text-emerald-800 font-bold text-lg">Tersedia {{ count($unitTypes) }} Tipe Tenda</h3>
                <p class="text-emerald-600 text-sm mt-1">Untuk tanggal {{ request('check_in') }} s/d {{ request('check_out') }}, {{ request('guests') }} Tamu</p>
            </div>
            <a href="/" class="text-emerald-700 font-medium text-sm hover:underline mt-4 sm:mt-0">Reset Pencarian</a>
        </div>
    </section>
    @endif

    <!-- Discover Section -->
    <section class="max-w-7xl mx-auto px-4 sm:px-6 py-24">
        <div class="flex justify-between items-end mb-12">
            <div>
                <h4 class="text-emerald-600 font-bold tracking-widest uppercase text-sm mb-2">Our Accommodations</h4>
                <h2 class="text-4xl font-extrabold text-gray-900 tracking-tight">Exclusive Lodges</h2>
            </div>
            <div class="hidden sm:block">
                <button class="flex items-center gap-2 text-gray-600 hover:text-emerald-600 font-medium transition">
                    View all lodges <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            @forelse($unitTypes as $type)
                <!-- Card -->
                <div class="group bg-white rounded-3xl overflow-hidden border border-gray-100 shadow-sm hover:shadow-2xl transition duration-300 ease-in-out transform hover:-translate-y-1">
                    <div class="relative h-64 overflow-hidden">
                        @if($type->photos->count() > 0)
                            <img src="{{ Storage::url($type->photos->first()->photo_path) }}" alt="{{ $type->name }}" class="w-full h-full object-cover group-hover:scale-105 transition duration-500">
                        @else
                            <img src="https://images.unsplash.com/photo-1523987355523-c7b5b0dd90a7?q=80&w=800&auto=format&fit=crop" alt="Lodge" class="w-full h-full object-cover group-hover:scale-105 transition duration-500">
                        @endif
                        
                        <div class="absolute top-4 right-4 bg-white/90 backdrop-blur text-gray-900 text-xs font-bold px-3 py-1.5 rounded-full flex items-center gap-1">
                            <svg class="w-3.5 h-3.5 text-emerald-500" fill="currentColor" viewBox="0 0 20 20"><path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z"></path></svg>
                            Up to {{ $type->capacity }} Guests
                        </div>
                    </div>
                    
                    <div class="p-6">
                        <div class="flex justify-between items-start mb-2">
                            <h3 class="text-2xl font-bold text-gray-900 group-hover:text-emerald-600 transition">{{ $type->name }}</h3>
                        </div>
                        
                        <p class="text-gray-500 text-sm mb-6 line-clamp-2">
                            {{ $type->description ?? 'Nikmati pengalaman menginap tak terlupakan dengan fasilitas lengkap di tengah keindahan alam.' }}
                        </p>
                        
                        <div class="flex flex-wrap gap-2 mb-6">
                            @if($type->facilities)
                                @foreach(array_slice($type->facilities, 0, 3) as $facility)
                                    <span class="text-xs bg-gray-50 text-gray-600 px-2.5 py-1 rounded-md border border-gray-100">{{ $facility }}</span>
                                @endforeach
                                @if(count($type->facilities) > 3)
                                    <span class="text-xs bg-gray-50 text-gray-600 px-2.5 py-1 rounded-md border border-gray-100">+{{ count($type->facilities) - 3 }}</span>
                                @endif
                            @endif
                        </div>
                        
                        <div class="flex items-center justify-between pt-4 border-t border-gray-50">
                            <div>
                                <span class="text-xs text-gray-400 uppercase tracking-wider font-semibold">Mulai dari</span>
                                <div class="text-xl font-extrabold text-emerald-600">
                                    Rp {{ number_format($type->base_price_weekday, 0, ',', '.') }}<span class="text-sm text-gray-400 font-normal">/mlm</span>
                                </div>
                            </div>
                            
                            @if(request()->has('check_in'))
                                <a href="{{ route('tenda.show', ['slug' => $type->slug, 'check_in' => request('check_in'), 'check_out' => request('check_out'), 'guests' => request('guests')]) }}" class="bg-gray-900 hover:bg-emerald-600 text-white w-12 h-12 flex items-center justify-center rounded-full transition shadow-md hover:shadow-emerald-500/30">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                                </a>
                            @else
                                <a href="{{ route('tenda.show', $type->slug) }}" class="bg-gray-900 hover:bg-emerald-600 text-white w-12 h-12 flex items-center justify-center rounded-full transition shadow-md hover:shadow-emerald-500/30">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-span-full py-12 text-center bg-white rounded-3xl border border-gray-100">
                    <div class="w-16 h-16 bg-gray-50 rounded-full flex items-center justify-center mx-auto mb-4 text-gray-400">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-800">Tidak ada tenda tersedia</h3>
                    <p class="text-gray-500 mt-2">Maaf, tidak ada tenda yang sesuai dengan kriteria pencarian Anda.</p>
                </div>
            @endforelse
        </div>
    </section>

    <!-- Features Section -->
    <section class="bg-emerald-900 text-white py-24 relative overflow-hidden">
        <!-- Pattern background -->
        <div class="absolute inset-0 opacity-10" style="background-image: radial-gradient(circle at 2px 2px, white 1px, transparent 0); background-size: 32px 32px;"></div>
        
        <div class="max-w-7xl mx-auto px-4 sm:px-6 relative z-10">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-16 items-center">
                <div>
                    <h4 class="text-emerald-400 font-bold tracking-widest uppercase text-sm mb-2">Why Raynad?</h4>
                    <h2 class="text-4xl font-extrabold mb-6 leading-tight">Elevate your camping experience</h2>
                    <p class="text-emerald-100/80 mb-8 text-lg font-light leading-relaxed">
                        We blend the raw beauty of nature with the uncompromising comfort of a luxury resort. Wake up to bird songs, sleep under the stars, but never sacrifice a good night's rest.
                    </p>
                    
                    <div class="grid grid-cols-2 gap-6">
                        <div>
                            <div class="w-12 h-12 bg-white/10 rounded-xl flex items-center justify-center mb-4 text-emerald-400 backdrop-blur-sm">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.14 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0"></path></svg>
                            </div>
                            <h4 class="font-bold mb-1">High-Speed WiFi</h4>
                            <p class="text-sm text-emerald-100/60">Stay connected even in the wild.</p>
                        </div>
                        <div>
                            <div class="w-12 h-12 bg-white/10 rounded-xl flex items-center justify-center mb-4 text-emerald-400 backdrop-blur-sm">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 18.657A8 8 0 016.343 7.343S7 9 9 10c0-2 .5-5 2.986-7C14 5 16.09 5.777 17.656 7.343A7.975 7.975 0 0120 13a7.975 7.975 0 01-2.343 5.657z"></path></svg>
                            </div>
                            <h4 class="font-bold mb-1">Private Bonfire</h4>
                            <p class="text-sm text-emerald-100/60">Cozy evenings by your own fire.</p>
                        </div>
                    </div>
                </div>
                
                <div class="relative">
                    <div class="rounded-3xl overflow-hidden shadow-2xl relative z-10">
                        <img src="https://images.unsplash.com/photo-1533873984035-25970ab07461?q=80&w=1000&auto=format&fit=crop" alt="Experience" class="w-full h-auto">
                    </div>
                    <!-- Decorative element -->
                    <div class="absolute -bottom-8 -left-8 w-64 h-64 bg-emerald-500 rounded-full mix-blend-multiply filter blur-3xl opacity-50 z-0"></div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-gray-950 text-gray-400 py-12 border-t border-gray-900">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 flex flex-col md:flex-row justify-between items-center gap-6">
            <div class="flex items-center gap-2 text-white">
                <svg class="w-6 h-6 text-emerald-500" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2L2 22h20L12 2zm0 4.5l6.5 13h-13L12 6.5z"/></svg>
                <span class="text-xl font-bold tracking-tight">Raynad</span>
            </div>
            <p class="text-sm">© {{ date('Y') }} Raynad Camping. All rights reserved.</p>
            <div class="flex gap-4">
                <a href="#" class="hover:text-white transition">Privacy</a>
                <a href="#" class="hover:text-white transition">Terms</a>
                <a href="/admin" class="hover:text-emerald-400 transition font-medium">Admin Portal</a>
            </div>
        </div>
    </footer>
</body>
</html>
