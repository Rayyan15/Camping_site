<!DOCTYPE html>
<html lang="id">
<head>
    <title>Selesaikan Pesanan - {{ $unitType->name }}</title>
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

        @if($isAvailable)
            <div class="grid md:grid-cols-3 gap-8">
                <!-- Form -->
                <div class="md:col-span-2">
                    <form action="{{ route('booking.store') }}" method="POST" class="bg-white rounded-3xl shadow-xl border border-gray-100 p-8">
                        @csrf
                        <input type="hidden" name="unit_type_id" value="{{ $unitType->id }}">
                        <input type="hidden" name="check_in" value="{{ $request->check_in }}">
                        <input type="hidden" name="check_out" value="{{ $request->check_out }}">
                        <input type="hidden" name="guests" value="{{ $request->guests }}">
                        
                        <div class="bg-emerald-50 text-emerald-700 px-5 py-3 rounded-xl mb-8 border border-emerald-100 font-semibold flex items-center gap-3">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                            Kabar Baik! Tersedia {{ $availableUnits->count() }} unit untuk tanggal tersebut.
                        </div>

                        <h3 class="font-extrabold text-lg text-gray-900 mb-5 border-b pb-2">Pilih Unit Spesifik (Opsional)</h3>
                        <div class="space-y-3 mb-8">
                            @foreach($availableUnits as $unit)
                                <label class="flex items-center space-x-4 bg-gray-50 p-4 rounded-xl border border-gray-200 cursor-pointer hover:bg-emerald-50 transition hover:border-emerald-200">
                                    <input type="checkbox" name="unit_ids[]" value="{{ $unit->id }}" class="h-5 w-5 text-emerald-600 border-gray-300 rounded focus:ring-emerald-500" {{ $loop->first ? 'checked' : '' }}>
                                    <span class="text-base font-bold text-gray-900">{{ $unit->name }}</span>
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
                            <p class="font-semibold text-lg text-emerald-400">{{ $unitType->name }}</p>
                        </div>
                        
                        <div class="mb-4">
                            <p class="text-gray-400 text-xs font-bold uppercase tracking-wider mb-1">Jadwal Inap</p>
                            <p class="font-medium text-sm">{{ \Carbon\Carbon::parse($request->check_in)->format('d M Y') }} - {{ \Carbon\Carbon::parse($request->check_out)->format('d M Y') }}</p>
                        </div>
                        
                        <div class="mb-6">
                            <p class="text-gray-400 text-xs font-bold uppercase tracking-wider mb-1">Total Tamu</p>
                            <p class="font-medium text-sm">{{ $request->guests }} Orang</p>
                        </div>

                        <div class="bg-white/10 rounded-xl p-4 backdrop-blur-sm">
                            <p class="text-xs text-gray-300 font-bold uppercase tracking-wider mb-1">Estimasi Total</p>
                            <p class="text-2xl font-extrabold text-white">Rp {{ number_format($unitType->base_price_weekday, 0, ',', '.') }}<span class="text-sm font-normal text-gray-400">/mlm</span></p>
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
                <p class="text-gray-500 mb-8 font-medium">Mohon maaf, tidak ada tenda <b>{{ $unitType->name }}</b> yang tersedia di tanggal yang Anda pilih. Silakan cari tipe tenda lain atau ubah jadwal liburan Anda.</p>
                <a href="{{ route('home') }}" class="inline-flex items-center gap-2 bg-gray-900 text-white font-bold py-3 px-8 rounded-full hover:bg-emerald-600 transition shadow-lg">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                    Cari Tanggal Lain
                </a>
            </div>
        @endif
    </div>
</body>
</html>