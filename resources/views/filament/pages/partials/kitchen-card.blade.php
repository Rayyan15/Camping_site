@php
    use App\Enums\OrderStatus;
    use App\Models\Order;

    $status = $order->statusEnum();
    $waiting = (int) $order->created_at->diffInMinutes(now());
    $isLate = $status === OrderStatus::Baru && $waiting >= $lateAfterMinutes;
    $place = $order->diningSpot?->name ?? ($order->booking ? 'Booking '.$order->booking->code : 'Di kasir');
    $next = $status->next();
    $canSettle = $showActions && $canProcess && ! $order->isPaid() && ! $order->bill_to_booking;
@endphp

<article class="kq-card" data-late="{{ $isLate ? 'true' : 'false' }}" wire:key="order-{{ $order->id }}">
    <div class="kq-top">
        <span class="kq-code">{{ $order->code }}</span>
        <span class="kq-age" title="Sejak pesanan masuk">{{ $waiting }} mnt</span>
    </div>
    <p class="kq-who">{{ $order->displayName() }} &middot; {{ $place }}</p>

    <ul class="kq-items">
        @foreach($order->items as $line)
            <li>
                <strong>{{ $line->qty }}x</strong> {{ $line->menuItem->name }}
                @if($line->notes)
                    <span class="kq-note">Catatan: {{ $line->notes }}</span>
                @endif
            </li>
        @endforeach
    </ul>

    <div class="kq-meta">
        <x-filament::badge size="sm" :color="match ($order->source) { Order::SOURCE_PREORDER => 'info', Order::SOURCE_QR => 'primary', default => 'gray' }">{{ $sourceLabels[$order->source] }}</x-filament::badge>
        @if($order->scheduled_at)
            <x-filament::badge size="sm" color="warning">Saji {{ $order->scheduled_at->format('d/m H:i') }}</x-filament::badge>
        @endif
        @if($order->bill_to_booking)
            <x-filament::badge size="sm" color="info">Tagih ke booking</x-filament::badge>
        @elseif($order->isPaid())
            <x-filament::badge size="sm" color="success">Lunas</x-filament::badge>
        @else
            <x-filament::badge size="sm" color="danger">Belum bayar {{ $rupiah($order->total) }}</x-filament::badge>
        @endif
    </div>

    @if($showActions && $canProcess)
        <div class="kq-actions">
            @if($next)
                <x-filament::button size="sm" wire:click="advance({{ $order->id }})" wire:loading.attr="disabled">
                    Tandai {{ strtolower($next->label()) }}
                </x-filament::button>
            @endif
            @if($canSettle)
                <x-filament::button size="sm" color="gray" wire:click="markPaid({{ $order->id }}, 'cash')" wire:confirm="Catat pembayaran tunai {{ $rupiah($order->total) }}?">Tunai</x-filament::button>
                <x-filament::button size="sm" color="gray" wire:click="markPaid({{ $order->id }}, 'manual')" wire:confirm="Catat pembayaran QRIS {{ $rupiah($order->total) }}?">QRIS</x-filament::button>
            @endif
        </div>
    @endif
</article>
