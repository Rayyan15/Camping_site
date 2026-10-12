<x-mail::message>
# Booking diterima

Halo {{ $booking->customer->name }}, booking Anda di {{ config('site.name') }} sudah kami terima.

@include('mail.partials.booking-summary')

@if($booking->hold_expires_at)
Tenda kami tahan sampai {{ $booking->hold_expires_at->locale('id')->translatedFormat('j F Y, H:i') }} WIB. Setelah itu tenda dilepas untuk tamu lain.
@endif

@if($bank->isComplete())
Transfer sebesar {{ 'Rp '.number_format($booking->total - $booking->paid_amount, 0, ',', '.') }} ke rekening {{ $bank->bankName }} {{ $bank->accountNumber }} atas nama {{ $bank->accountHolder }}, lalu unggah bukti transfernya di halaman pembayaran.
@else
Nomor rekening belum diatur. Hubungi admin lewat halaman pembayaran untuk mendapat nomor rekening.
@endif

<x-mail::button :url="$checkoutUrl">
Unggah bukti transfer
</x-mail::button>

Simpan email ini. Saat tiba di lokasi, tunjukkan email konfirmasi pembayaran kepada petugas.
</x-mail::message>
