<x-mail::message>
# Jadwal booking diubah

Halo {{ $booking->customer->name }}, tanggal menginap untuk booking {{ $booking->code }} sudah diubah.

@include('mail.partials.booking-summary')

@if($outstanding > 0)
Sisa tagihan Rp {{ number_format($outstanding, 0, ',', '.') }}. Transfer selisihnya lalu kirim bukti ke admin lewat WhatsApp.
@endif

@if($credit > 0)
Kelebihan bayar Rp {{ number_format($credit, 0, ',', '.') }} akan dikembalikan oleh admin.
@endif

Jadwal ulang hanya bisa dilakukan satu kali. Simpan email ini dan tunjukkan kepada petugas saat tiba di lokasi.

<x-mail::button :url="$statusUrl">
Lihat status booking
</x-mail::button>
</x-mail::message>
