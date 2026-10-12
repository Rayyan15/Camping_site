<x-mail::message>
# Pembayaran dikonfirmasi

Halo {{ $booking->customer->name }}, pembayaran booking {{ $booking->code }} sudah dikonfirmasi admin.

@include('mail.partials.booking-summary')

Simpan email ini sebagai bukti konfirmasi. Saat tiba di lokasi, tunjukkan email ini kepada petugas.

<x-mail::button :url="$statusUrl">
Lihat status booking
</x-mail::button>
</x-mail::message>
