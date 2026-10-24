@php
    $money = fn (int $amount) => 'Rp '.number_format($amount, 0, ',', '.');
    $date = fn ($day) => $day->locale(app()->getLocale())->translatedFormat('j F Y');
    $units = $booking->bookingUnits
        ->groupBy(fn ($line) => $line->unit?->unitType?->name ?? 'Unit')
        ->map(fn ($lines, $name) => $lines->count().' x '.$name)
        ->implode(', ');
@endphp
- {{ __('Kode booking') }}: **{{ $booking->code }}**
- {{ __('Tanggal') }}: {{ __(':from sampai :to', ['from' => $date($booking->check_in), 'to' => $date($booking->check_out)]) }}
@if($units !== '')
- {{ __('Tenda') }}: {{ $units }}
@endif
- {{ __('Total') }}: {{ $money($booking->total) }}
