<x-mail::message>
# {{ __('Jadwal booking diubah') }}

{{ __('Halo :name, tanggal menginap untuk booking :code sudah diubah.', ['name' => $booking->customer->name, 'code' => $booking->code]) }}

@include('mail.partials.booking-summary')

@if($outstanding > 0)
{{ __('Sisa tagihan :amount. Transfer selisihnya lalu kirim bukti ke admin lewat WhatsApp.', ['amount' => 'Rp '.number_format($outstanding, 0, ',', '.')]) }}
@endif

@if($credit > 0)
{{ __('Kelebihan bayar :amount akan dikembalikan oleh admin.', ['amount' => 'Rp '.number_format($credit, 0, ',', '.')]) }}
@endif

{{ __('Jadwal ulang hanya bisa dilakukan satu kali. Simpan email ini dan tunjukkan kepada petugas saat tiba di lokasi.') }}

<x-mail::button :url="$statusUrl">
{{ __('Lihat status booking') }}
</x-mail::button>
</x-mail::message>
