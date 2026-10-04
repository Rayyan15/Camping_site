<!DOCTYPE html>
<html lang="id">
<head>
    <title>Booking Berhasil - Raynad Camping</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #FAFAFA; }
        .glass-panel { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); }
    </style>
</head>
<body class="bg-gray-50 text-gray-800 antialiased font-['Plus_Jakarta_Sans'] min-h-screen flex flex-col">
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
    <div class="flex-grow flex items-center justify-center p-4 py-12">
        <div class="bg-white rounded-3xl shadow-xl border border-gray-100 p-8 md:p-12 max-w-2xl w-full text-center relative overflow-hidden">
            <!-- Decorative background blob -->
            <div class="absolute -top-24 -right-24 w-48 h-48 bg-emerald-400 rounded-full mix-blend-multiply filter blur-3xl opacity-20"></div>
            
            <div class="relative z-10">
                <div class="w-24 h-24 bg-emerald-100 rounded-full flex items-center justify-center mx-auto mb-6 border-4 border-white shadow-lg">
                    <svg class="h-12 w-12 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                </div>
                
                <h1 class="text-3xl font-extrabold text-gray-900 mb-2">Booking Berhasil Dibuat!</h1>
                <p class="text-gray-500 mb-8 font-medium">Terima kasih, {{ $booking->customer->name }}. Kode booking Anda adalah:</p>
                
                <div class="bg-gray-900 text-white py-4 px-8 rounded-2xl inline-block mb-10 shadow-xl transform hover:scale-105 transition cursor-default">
                    <span class="text-4xl font-extrabold tracking-widest font-mono text-emerald-400">{{ $booking->code }}</span>
                </div>
                
                <div class="bg-emerald-50 rounded-2xl p-6 text-left mb-8 border border-emerald-100">
                    <h3 class="font-bold text-gray-900 mb-4 border-b border-emerald-200 pb-2">Instruksi Pembayaran</h3>
                    <p class="text-sm text-gray-700 mb-4 leading-relaxed font-medium">
                        Total yang harus dibayar: <strong class="text-emerald-700 text-lg">Rp {{ number_format($booking->total, 0, ',', '.') }}</strong>
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