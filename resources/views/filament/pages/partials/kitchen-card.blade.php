@php
    use App\Enums\OrderStatus;
    use App\Models\Order;

    $status = $order->statusEnum();
    $place = $order->diningSpot?->name ?? ($order->booking ? 'Booking '.$order->booking->code : 'Di kasir');
    $canSettle = $showActions && auth()->user()->can('record_order_payment') && ! $order->isPaid() && ! $order->bill_to_booking;
    $span = function (int $minutes): string {
        return match (true) {
            $minutes < 60 => $minutes.' mnt',
            $minutes < 1440 => intdiv($minutes, 60).' jam',
            default => intdiv($minutes, 1440).' hari',
        };
    };

    // A pre-order is timed against its serving slot, a walk-in or QR order against the moment it came in.
    if ($order->scheduled_at) {
        $untilServe = (int) now()->diffInMinutes($order->scheduled_at, false);
        $timeLabel = 'Saji '.$order->scheduled_at->locale('id')->translatedFormat('D j/n H:i');
        $timeHint = $untilServe > 0 ? $span($untilServe).' lagi' : 'lewat '.$span(-$untilServe);
        $tone = match (true) {
            $untilServe <= 0 => 'late',
            $untilServe <= $lateAfterMinutes => 'soon',
            default => 'calm',
        };
    } else {
        $waiting = (int) $order->created_at->diffInMinutes(now());
        $timeLabel = 'Masuk '.$order->created_at->format('H:i');
        $timeHint = $span($waiting).' lalu';
        $tone = $waiting >= $lateAfterMinutes ? 'late' : 'calm';
    }
    $isLate = $tone === 'late' && $status === OrderStatus::Baru;
@endphp

<article class="kq-card" data-tone="{{ $showActions ? $tone : 'calm' }}" wire:key="order-{{ $order->id }}">
    <p class="kq-time">
        <span class="kq-time-main">
            <svg class="kq-time-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 1 1-18 0 9 9 0 0 1 18 0z"/></svg>
            {{ $timeLabel }}
        </span>
        <span class="kq-time-hint">{{ $timeHint }}</span>
    </p>

    <p class="kq-who"><span class="kq-code">{{ $order->code }}</span><strong>{{ $order->displayName() }}</strong><span class="kq-place">{{ $place }}</span></p>

    <ul class="kq-items" aria-label="Item pesanan">
        @foreach($order->items as $line)
            <li>
                <span class="kq-qty">{{ $line->qty }}x</span>
                <span>
                    {{ $line->menuItem->name }}
                    @if($line->notes)
                        <span class="kq-note">Catatan: {{ $line->notes }}</span>
                    @endif
                </span>
            </li>
        @endforeach
    </ul>

    <div class="kq-meta">
        <x-filament::badge size="sm" :color="match ($order->source) { Order::SOURCE_PREORDER => 'info', Order::SOURCE_QR => 'primary', default => 'gray' }">{{ $sourceLabels[$order->source] }}</x-filament::badge>
        @if($order->bill_to_booking)
            <x-filament::badge size="sm" color="gray">Tagih ke booking</x-filament::badge>
        @elseif($order->isPaid())
            <x-filament::badge size="sm" color="success">Lunas</x-filament::badge>
        @else
            <x-filament::badge size="sm" color="danger">Belum bayar {{ $rupiah($order->total) }}</x-filament::badge>
        @endif
        @if($isLate)
            <x-filament::badge size="sm" color="danger">Terlambat</x-filament::badge>
        @endif
    </div>

    @if($showActions && $canProcess)
        <div class="kq-actions">
            <x-filament::button class="kq-primary" wire:click="advance({{ $order->id }}, '{{ OrderStatus::Selesai->value }}')" wire:loading.attr="disabled">
                Tandai selesai
            </x-filament::button>
            <div class="kq-secondary">
                @if($canSettle)
                    <x-filament::button size="sm" color="gray" outlined wire:click="markPaid({{ $order->id }}, 'cash')" wire:confirm="Catat pembayaran tunai {{ $rupiah($order->total) }}?">Tunai</x-filament::button>
                    <x-filament::button size="sm" color="gray" outlined wire:click="markPaid({{ $order->id }}, 'manual')" wire:confirm="Catat pembayaran QRIS {{ $rupiah($order->total) }}?">QRIS</x-filament::button>
                @endif
                @if($status->canMoveTo(OrderStatus::Dibatalkan) && ! $order->isPaid())
                    <x-filament::button size="sm" color="danger" outlined class="kq-cancel" wire:click="advance({{ $order->id }}, '{{ OrderStatus::Dibatalkan->value }}')" wire:confirm="Batalkan pesanan {{ $order->code }}? Pesanan tidak akan dimasak atau ditagih.">Batalkan</x-filament::button>
                @endif
            </div>
        </div>
    @endif
</article>
