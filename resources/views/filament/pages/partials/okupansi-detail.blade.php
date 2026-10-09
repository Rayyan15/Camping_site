@php
    $status = $cell['status'];
@endphp

<div class="space-y-4 text-sm">
    <p class="font-semibold">
        <span class="oc-tag oc-{{ $status->value }}"><span class="oc-label">{{ $status->label() }}</span></span>
        <span class="ms-2">{{ $unit_type }}, unit {{ $unit_code }}</span>
    </p>

    @if($booking)
        <dl class="oc-dl">
            <dt class="">Kode booking</dt>
            <dd class="font-semibold tabular-nums">{{ $booking->code }}</dd>

            <dt class="">Tamu</dt>
            <dd>{{ $guest ?? 'Tanpa nama' }}</dd>

            <dt class="">Status booking</dt>
            <dd>{{ $booking->status->getLabel() }}</dd>

            <dt class="">Check-in</dt>
            <dd>{{ $booking->check_in->translatedFormat('j F Y') }}</dd>

            <dt class="">Check-out</dt>
            <dd>{{ $booking->check_out->translatedFormat('j F Y') }}</dd>

            <dt class="">Jumlah tamu</dt>
            <dd>{{ $booking->guests }} orang</dd>

            <dt class="">Unit</dt>
            <dd>{{ $booking->bookingUnits->map(fn ($line) => $line->unit?->code)->filter()->join(', ') }}</dd>
        </dl>

        @if($editUrl)
            <x-filament::button tag="a" :href="$editUrl" color="primary" icon="heroicon-m-pencil-square">
                Buka booking
            </x-filament::button>
        @endif
    @elseif($block)
        <dl class="oc-dl">
            <dt class="">Alasan</dt>
            <dd>{{ $block->reason ?: 'Tidak dicatat' }}</dd>

            <dt class="">Periode</dt>
            <dd>{{ $block->start_date->translatedFormat('j F Y') }} sampai {{ $block->end_date->translatedFormat('j F Y') }}</dd>
        </dl>
    @else
        <p>{{ $status->description() }}.</p>
    @endif
</div>
