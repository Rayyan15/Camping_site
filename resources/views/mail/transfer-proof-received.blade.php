<x-mail::message>
# Bukti transfer diterima

Halo {{ $booking->customer->name }}, bukti transfer untuk booking {{ $booking->code }} sudah masuk. Admin akan mencocokkannya dengan mutasi rekening.

@include('mail.partials.booking-summary')

Tenda tetap kami tahan selama bukti diperiksa. Anda akan menerima email lagi setelah pembayaran dikonfirmasi atau bila bukti perlu dikirim ulang. Cek email Anda secara berkala.

<x-mail::button :url="$statusUrl">
Lihat status booking
</x-mail::button>
</x-mail::message>
