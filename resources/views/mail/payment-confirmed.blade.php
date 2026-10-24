<x-mail::message>
# {{ __('Pembayaran dikonfirmasi') }}

{{ __('Halo :name, pembayaran booking :code sudah dikonfirmasi admin.', ['name' => $booking->customer->name, 'code' => $booking->code]) }}

@include('mail.partials.booking-summary')

{{ __('Simpan email ini sebagai bukti konfirmasi. Saat tiba di lokasi, tunjukkan email ini kepada petugas.') }}

<x-mail::button :url="$statusUrl">
{{ __('Lihat status booking') }}
</x-mail::button>
</x-mail::message>
