<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Read model for the kitchen board.
 *
 * The board is query based: a pre-order shows up the moment its booking is paid and its serve time
 * falls on today or tomorrow, so no PushPreorderToKitchen job is needed to copy anything into the queue.
 */
class KitchenQueueService
{
    /**
     * @return array<string, Collection<int, Order>> keyed by OrderStatus value, every status present
     */
    public function board(): array
    {
        $orders = $this->visibleOrders()
            ->with(['items.menuItem', 'booking.customer', 'diningSpot'])
            ->orderByRaw('COALESCE(scheduled_at, created_at) asc')
            ->get()
            ->groupBy('status');

        return collect(OrderStatus::cases())
            ->mapWithKeys(fn (OrderStatus $status) => [$status->value => $orders->get($status->value, collect())])
            ->all();
    }

    public function activeCount(): int
    {
        return $this->visibleOrders()->where('status', '!=', OrderStatus::Selesai->value)->count();
    }

    /**
     * @return Builder<Order>
     */
    private function visibleOrders(): Builder
    {
        $startOfToday = now()->startOfDay();
        $endOfTomorrow = now()->addDay()->endOfDay();

        return Order::query()->kitchenRelevant()->where(function (Builder $query) use ($startOfToday, $endOfTomorrow) {
            $query->where(function (Builder $walkInAndQr) use ($startOfToday) {
                $walkInAndQr->whereIn('source', [Order::SOURCE_QR, Order::SOURCE_WALKIN])
                    ->where('created_at', '>=', $startOfToday);
            })->orWhere(function (Builder $preorder) use ($startOfToday, $endOfTomorrow) {
                $preorder->where('source', Order::SOURCE_PREORDER)
                    ->whereBetween('scheduled_at', [$startOfToday, $endOfTomorrow]);
            });
        });
    }
}
