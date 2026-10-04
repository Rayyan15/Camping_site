<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cek Ketersediaan - {{ $unitType->name }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50 text-gray-800 antialiased font-['Inter']">
    <div class="max-w-3xl mx-auto px-4 py-10">
        <h1 class="text-2xl font-bold text-gray-900 mb-6">Hasil Cek Ketersediaan</h1>
        
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-6">
            <h2 class="text-lg font-bold">{{ $unitType->name }}</h2>
            <p class="text-sm text-gray-600">Check-in: {{ $request->check_in }} | Check-out: {{ $request->check_out }} | Tamu: {{ $request->guests }}</p>
        </div>

        @if(session('error'))
            <div class="bg-red-50 text-red-700 p-4 rounded-md mb-6 border border-red-200">
                {{ session('error') }}
            </div>
        @endif

        @if($isAvailable)
            <div class="bg-green-50 text-green-700 p-4 rounded-md mb-6 border border-green-200 font-medium">
                Tersedia {{ $availableUnits->count() }} unit untuk tanggal tersebut!
            </div>
            
            <form action="{{ route('booking.store') }}" method="POST" class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                @csrf
                <input type="hidden" name="unit_type_id" value="{{ $unitType->id }}">
                <input type="hidden" name="check_in" value="{{ $request->check_in }}">
                <input type="hidden" name="check_out" value="{{ $request->check_out }}">
                <input type="hidden" name="guests" value="{{ $request->guests }}">
                
                <h3 class="font-bold text-gray-900 mb-4">Pilih Unit (Opsional / Otomatis)</h3>
                <div class="space-y-2 mb-6">
                    @foreach($availableUnits as $unit)
                        <label class="flex items-center space-x-3 bg-gray-50 p-3 rounded-lg border border-gray-200">
                            <input type="checkbox" name="unit_ids[]" value="{{ $unit->id }}" class="h-4 w-4 text-teal-600 border-gray-300 rounded focus:ring-teal-500" {{ $loop->first ? 'checked' : '' }}>
                            <span class="text-sm font-medium text-gray-900">{{ $unit->name }}</span>
                        </label>
                    @endforeach
                </div>

                <h3 class="font-bold text-gray-900 mb-4">Data Pemesan</h3>
                <div class="space-y-4 mb-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Nama Lengkap</label>
                        <input type="text" name="customer_name" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-teal-500 focus:ring-teal-500 sm:text-sm p-2 border" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Email</label>
                        <input type="email" name="customer_email" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-teal-500 focus:ring-teal-500 sm:text-sm p-2 border" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">No. WhatsApp</label>
                        <input type="text" name="customer_phone" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-teal-500 focus:ring-teal-500 sm:text-sm p-2 border" required>
                    </div>
                </div>

                <button type="submit" class="w-full bg-teal-600 hover:bg-teal-700 text-white font-medium py-3 px-4 rounded-md transition shadow-sm">
                    Lanjutkan ke Pembayaran
                </button>
            </form>
        @else
            <div class="bg-red-50 text-red-700 p-6 rounded-xl mb-6 border border-red-200 text-center">
                <svg class="mx-auto h-12 w-12 text-red-400 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <h3 class="text-lg font-bold mb-2">Mohon Maaf, Unit Penuh</h3>
                <p>Tidak ada tenda tipe ini yang tersedia di tanggal tersebut. Silakan cari tipe tenda lain atau ubah tanggal.</p>
                <a href="{{ route('home') }}" class="mt-4 inline-block bg-white text-red-700 font-medium py-2 px-4 border border-red-300 rounded-md hover:bg-red-50 transition">Kembali</a>
            </div>
        @endif
    </div>
</body>
</html>
