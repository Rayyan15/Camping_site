<x-mail::message>
# Bukti transfer ditolak

Halo {{ $booking->customer->name }}, bukti transfer untuk booking {{ $booking->code }} belum bisa kami terima.

Alasan dari admin: {{ $reason }}

@include('mail.partials.booking-summary')

Silakan unggah ulang bukti transfer yang benar di halaman pembayaran. Tenda ditahan {{ config('booking.hold_minutes') }} menit sejak bukti ditolak.

<x-mail::button :url="$checkoutUrl">
Unggah ulang bukti
</x-mail::button>
</x-mail::message>
