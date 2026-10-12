@php
    $money = fn (int $amount) => 'Rp '.number_format($amount, 0, ',', '.');
    $units = $booking->bookingUnits
        ->groupBy(fn ($line) => $line->unit?->unitType?->name ?? 'Unit')
        ->map(fn ($lines, $name) => $lines->count().' x '.$name)
        ->implode(', ');
@endphp
- Kode booking: **{{ $booking->code }}**
- Tanggal: {{ $booking->check_in->locale('id')->translatedFormat('j F Y') }} sampai {{ $booking->check_out->locale('id')->translatedFormat('j F Y') }}
@if($units !== '')
- Tenda: {{ $units }}
@endif
- Total: {{ $money($booking->total) }}
