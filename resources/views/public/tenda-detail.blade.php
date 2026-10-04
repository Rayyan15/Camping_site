<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail {{ $unitType->name }} - Raynad Camping</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="bg-gray-50 text-gray-800 antialiased font-['Inter']">

    <!-- Navbar Minimalis -->
    <nav class="bg-white shadow-sm w-full z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16 items-center">
                <a href="/" class="flex items-center text-teal-800 hover:text-teal-900 transition">
                    <svg class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    <span class="font-semibold text-sm">Kembali ke Beranda</span>
                </a>
                <span class="font-bold text-lg text-teal-800">Raynad Camping</span>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden lg:flex">
            <!-- Galeri Foto (Sederhana 1 foto dulu) -->
            <div class="lg:w-1/2 bg-gray-200">
                @if($unitType->photos && $unitType->photos->count() > 0)
                    <img src="{{ asset('storage/'.$unitType->photos->first()->path) }}" alt="{{ $unitType->name }}" class="w-full h-full object-cover min-h-[400px]">
                @else
                    <img src="https://images.unsplash.com/photo-1523987355523-c7b5b0dd90a7?w=800&q=80" alt="Placeholder Tenda" class="w-full h-full object-cover min-h-[400px]">
                @endif
            </div>

            <!-- Detail Info & Form Booking -->
            <div class="p-8 lg:w-1/2 flex flex-col justify-between">
                <div>
                    <div class="flex justify-between items-start">
                        <div>
                            <h1 class="text-3xl font-extrabold text-gray-900">{{ $unitType->name }}</h1>
                            <p class="text-sm text-gray-500 mt-1">Kapasitas Maksimal: {{ $unitType->capacity }} Orang</p>
                        </div>
                        <div class="text-right">
                            <p class="text-sm text-gray-500 uppercase tracking-wide font-semibold">Harga per malam</p>
                            <p class="text-2xl font-bold text-teal-700">Rp {{ number_format($unitType->base_price_weekday, 0, ',', '.') }}</p>
                        </div>
                    </div>

                    <div class="mt-8 prose prose-teal text-gray-600">
                        <p>{{ $unitType->description ?? 'Nikmati pengalaman menginap nyaman di tenda eksklusif dengan pemandangan alam memukau. Cocok untuk keluarga dan pasangan.' }}</p>
                    </div>

                    <div class="mt-8">
                        <h3 class="text-lg font-bold text-gray-900 mb-4">Fasilitas Termasuk:</h3>
                        <div class="grid grid-cols-2 gap-3">
                            @if(is_array($unitType->facilities))
                                @foreach($unitType->facilities as $fasilitas)
                                <div class="flex items-center text-sm text-gray-700">
                                    <svg class="h-5 w-5 text-teal-500 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    {{ $fasilitas }}
                                </div>
                                @endforeach
                            @else
                                <div class="flex items-center text-sm text-gray-700"><svg class="h-5 w-5 text-teal-500 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>Kasur Busa & Bantal</div>
                                <div class="flex items-center text-sm text-gray-700"><svg class="h-5 w-5 text-teal-500 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>Selimut Hangat</div>
                                <div class="flex items-center text-sm text-gray-700"><svg class="h-5 w-5 text-teal-500 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>Stop Kontak Listrik</div>
                                <div class="flex items-center text-sm text-gray-700"><svg class="h-5 w-5 text-teal-500 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>Lampu Penerangan</div>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="mt-10 bg-gray-50 rounded-xl p-6 border border-gray-200">
                    <h3 class="text-base font-bold text-gray-900 mb-4">Pesan Tenda Ini</h3>
                    <form action="/booking/cek" method="GET" class="space-y-4">
                        <input type="hidden" name="unit_type_id" value="{{ $unitType->id }}">
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Check-in</label>
                                <input type="date" name="check_in" value="{{ $today }}" min="{{ $today }}" class="w-full border-gray-300 rounded-md shadow-sm focus:ring-teal-500 focus:border-teal-500 sm:text-sm px-4 py-2 border" required>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Check-out</label>
                                <input type="date" name="check_out" value="{{ $tomorrow }}" min="{{ $tomorrow }}" class="w-full border-gray-300 rounded-md shadow-sm focus:ring-teal-500 focus:border-teal-500 sm:text-sm px-4 py-2 border" required>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Jumlah Tamu</label>
                            <input type="number" name="guests" min="1" max="{{ $unitType->capacity + 2 }}" value="2" class="w-full border-gray-300 rounded-md shadow-sm focus:ring-teal-500 focus:border-teal-500 sm:text-sm px-4 py-2 border" required>
                            <p class="text-xs text-gray-500 mt-1">Kapasitas dasar {{ $unitType->capacity }} orang. Lebih dari itu wajib sewa Extra Bed.</p>
                        </div>

                        <button type="submit" class="w-full flex justify-center py-3 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-teal-600 hover:bg-teal-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-teal-500 transition mt-6">
                            Cek Ketersediaan & Booking
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
