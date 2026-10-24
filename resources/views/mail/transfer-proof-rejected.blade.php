<x-mail::message>
# {{ __('Bukti transfer ditolak') }}

{{ __('Halo :name, bukti transfer untuk booking :code belum bisa kami terima.', ['name' => $booking->customer->name, 'code' => $booking->code]) }}

{{ __('Alasan dari admin: :reason', ['reason' => $reason]) }}

@include('mail.partials.booking-summary')

{{ __('Silakan unggah ulang bukti transfer yang benar di halaman pembayaran. Tenda ditahan :minutes menit sejak bukti ditolak.', ['minutes' => config('booking.hold_minutes')]) }}

<x-mail::button :url="$checkoutUrl">
{{ __('Unggah ulang bukti') }}
</x-mail::button>
</x-mail::message>
