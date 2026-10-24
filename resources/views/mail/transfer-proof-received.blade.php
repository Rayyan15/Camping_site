<x-mail::message>
# {{ __('Bukti transfer diterima') }}

{{ __('Halo :name, bukti transfer untuk booking :code sudah masuk. Admin akan mencocokkannya dengan mutasi rekening.', ['name' => $booking->customer->name, 'code' => $booking->code]) }}

@include('mail.partials.booking-summary')

{{ __('Tenda tetap kami tahan selama bukti diperiksa. Anda akan menerima email lagi setelah pembayaran dikonfirmasi atau bila bukti perlu dikirim ulang. Cek email Anda secara berkala.') }}

<x-mail::button :url="$statusUrl">
{{ __('Lihat status booking') }}
</x-mail::button>
</x-mail::message>
