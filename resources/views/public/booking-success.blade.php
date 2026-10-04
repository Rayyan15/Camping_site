<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Sukses</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50 text-gray-800 antialiased font-['Inter'] flex items-center justify-center min-h-screen">
    <div class="max-w-md w-full bg-white rounded-2xl shadow-xl border border-gray-100 p-8 text-center">
        <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-green-100 mb-6">
            <svg class="h-8 w-8 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
        </div>
        
        <h2 class="text-2xl font-extrabold text-gray-900 mb-2">Booking Berhasil!</h2>
        <p class="text-gray-500 mb-6">Unit tenda Anda sudah kami kunci (hold) selama 15 menit. Selesaikan pembayaran untuk mengamankan pesanan ini.</p>
        
        <div class="bg-gray-50 p-4 rounded-lg border border-gray-200 mb-8 text-left">
            <p class="text-sm text-gray-500">Kode Booking</p>
            <p class="text-lg font-bold text-gray-900 mb-3">{{ $booking->code }}</p>
            
            <p class="text-sm text-gray-500">Total Pembayaran</p>
            <p class="text-2xl font-bold text-teal-700">Rp {{ number_format($booking->total, 0, ',', '.') }}</p>
        </div>

        <a href="#" class="block w-full bg-teal-600 hover:bg-teal-700 text-white font-bold py-3 px-4 rounded-md transition shadow-md mb-3">
            Bayar Sekarang (Midtrans)
        </a>
        <a href="/" class="block w-full bg-white hover:bg-gray-50 text-gray-700 font-medium py-3 px-4 rounded-md border border-gray-300 transition">
            Kembali ke Beranda
        </a>
    </div>
</body>
</html>
