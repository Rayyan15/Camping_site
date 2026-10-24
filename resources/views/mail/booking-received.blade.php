<x-mail::message>
# {{ __('Booking diterima') }}

{{ __('Halo :name, booking Anda di :site sudah kami terima.', ['name' => $booking->customer->name, 'site' => config('site.name')]) }}

@include('mail.partials.booking-summary')

@if($booking->hold_expires_at)
{{ __('Tenda kami tahan sampai :time WIB. Setelah itu tenda dilepas untuk tamu lain.', ['time' => $booking->hold_expires_at->locale(app()->getLocale())->translatedFormat('j F Y, H:i')]) }}
@endif

@if($bank->isComplete())
{{ __('Transfer sebesar :amount ke rekening :bank :number atas nama :holder, lalu unggah bukti transfernya di halaman pembayaran.', ['amount' => 'Rp '.number_format($booking->total - $booking->paid_amount, 0, ',', '.'), 'bank' => $bank->bankName, 'number' => $bank->accountNumber, 'holder' => $bank->accountHolder]) }}
@else
{{ __('Nomor rekening belum diatur. Hubungi admin lewat halaman pembayaran untuk mendapat nomor rekening.') }}
@endif

<x-mail::button :url="$checkoutUrl">
{{ __('Unggah bukti transfer') }}
</x-mail::button>

{{ __('Simpan email ini. Saat tiba di lokasi, tunjukkan email konfirmasi pembayaran kepada petugas.') }}
</x-mail::message>
