<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\OrderStatus;
use App\Exceptions\MenuItemUnavailableException;
use App\Models\MenuItem;
use App\Models\Order;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Finished-goods stock per menu item, set by the owner. Remaining stock is derived from orders
 * instead of a running counter, so a cancelled order or an expired booking returns its portions
 * without any extra bookkeeping.
 */
class MenuStockService
{
    /**
     * Remaining portions per menu item id. Items without a stock count are left out (unlimited).
     *
     * @param  array<int, int>|null  $menuItemIds  null for every counted item
     * @return array<int, int>
     */
    public function remaining(?array $menuItemIds = null): array
    {
        $items = MenuItem::whereNotNull('stock')
            ->when($menuItemIds !== null, fn ($query) => $query->whereIn('id', $menuItemIds))
            ->get(['id', 'stock', 'stock_counted_at']);

        $used = $this->usedSince($items)->pluck('used', 'menu_item_id');

        return $items->mapWithKeys(fn (MenuItem $item) => [
            $item->id => max(0, $item->stock - (int) ($used[$item->id] ?? 0)),
        ])->all();
    }

    public function remainingFor(MenuItem $item): ?int
    {
        return $this->remaining([$item->id])[$item->id] ?? null;
    }

    /**
     * Call inside the order transaction, before the order lines are written.
     *
     * @param  array<int, array{menu_item_id: int, name: string, qty: int}>  $lines
     *
     * @throws MenuItemUnavailableException
     */
    public function assertEnough(array $lines): void
    {
        $wanted = [];
        foreach ($lines as $line) {
            $wanted[$line['menu_item_id']] = ($wanted[$line['menu_item_id']] ?? 0) + $line['qty'];
        }

        // Exact on MySQL through this row lock and on SQLite through IMMEDIATE transactions (config/database.php).
        MenuItem::whereIn('id', array_keys($wanted))->lockForUpdate()->get(['id']);
        $remaining = $this->remaining(array_keys($wanted));

        foreach ($lines as $line) {
            $left = $remaining[$line['menu_item_id']] ?? null;
            if ($left !== null && $wanted[$line['menu_item_id']] > $left) {
                throw MenuItemUnavailableException::notEnoughStock($line['name'], $left);
            }
        }
    }

    /**
     * Owner says this many portions can still be sold now. Pre-orders not served yet stay counted after the
     * recount, so the stored count adds them back to keep the sellable figure exactly what the owner typed.
     * Null stops counting.
     */
    public function setRemaining(MenuItem $item, ?int $portions): void
    {
        $stock = $portions === null ? null : $portions + $this->reservedFor($item);
        $item->update(['stock' => $stock, 'stock_counted_at' => $portions === null ? null : now()]);
    }

    /** Portions held by pre-orders that are neither served nor cancelled. */
    public function reservedFor(MenuItem $item): int
    {
        return (int) $this->liveOrderLines()
            ->where('order_items.menu_item_id', $item->id)
            ->where('orders.source', Order::SOURCE_PREORDER)
            ->whereNull('orders.served_at')
            ->sum('order_items.qty');
    }

    public function add(MenuItem $item, int $portions): void
    {
        DB::transaction(function () use ($item, $portions) {
            $locked = MenuItem::whereKey($item->id)->lockForUpdate()->firstOrFail();
            $this->setRemaining($locked, ($this->remainingFor($locked) ?? 0) + $portions);
            $item->refresh();
        });
    }

    /**
     * @param  Collection<int, MenuItem>  $items
     * @return Collection<int, \stdClass>
     */
    private function usedSince(Collection $items): Collection
    {
        if ($items->isEmpty()) {
            return collect();
        }

        return $this->liveOrderLines()
            ->join('menu_items', 'menu_items.id', '=', 'order_items.menu_item_id')
            ->whereIn('order_items.menu_item_id', $items->pluck('id')->all())
            // A recount covers what is on the shelf, which still includes pre-orders not served at that moment, so
            // those stay counted until served after the recount (setRemaining adds them back into the count).
            ->where(fn (Builder $counted) => $counted
                ->whereColumn('orders.created_at', '>=', 'menu_items.stock_counted_at')
                ->orWhere(fn (Builder $unserved) => $unserved
                    ->where('orders.source', Order::SOURCE_PREORDER)
                    ->where(fn (Builder $open) => $open
                        ->whereNull('orders.served_at')
                        ->orWhereColumn('orders.served_at', '>=', 'menu_items.stock_counted_at'))))
            ->groupBy('order_items.menu_item_id')
            ->selectRaw('order_items.menu_item_id, SUM(order_items.qty) as used')
            ->get();
    }

    /** Order lines that still hold portions: not cancelled and not on a lapsed or cancelled booking. */
    private function liveOrderLines(): Builder
    {
        return DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.status', '!=', OrderStatus::Dibatalkan->value)
            // Served food was eaten, so a booking cancelled or refunded afterwards does not put it back on the shelf.
            ->where(fn (Builder $query) => $query
                ->whereNull('orders.booking_id')
                ->orWhereNotNull('orders.served_at')
                ->orWhereNotIn('orders.booking_id', fn (Builder $dead) => $dead->select('id')->from('bookings')
                    ->whereIn('status', array_map(fn (BookingStatus $status) => $status->value, [BookingStatus::Expired, BookingStatus::Cancelled, BookingStatus::Refunded]))));
    }
}
