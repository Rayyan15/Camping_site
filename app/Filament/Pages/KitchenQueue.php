<?php

namespace App\Filament\Pages;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Exceptions\InvalidOrderTransitionException;
use App\Exceptions\OrderBillingException;
use App\Filament\Resources\Orders\Tables\OrdersTable;
use App\Models\Order;
use App\Services\KitchenQueueService;
use App\Services\OrderService;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class KitchenQueue extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFire;

    protected static string|UnitEnum|null $navigationGroup = 'Operasional';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Antrian Dapur';

    protected static ?string $title = 'Antrian Dapur';

    protected static ?string $slug = 'antrian-dapur';

    // A new order waiting longer than this is flagged so the kitchen sees it first.
    private const LATE_AFTER_MINUTES = 10;

    protected string $view = 'filament.pages.kitchen-queue';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('view_kitchen_queue') ?? false;
    }

    public static function getNavigationBadge(): ?string
    {
        $active = app(KitchenQueueService::class)->activeCount();

        return $active > 0 ? (string) $active : null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'board' => app(KitchenQueueService::class)->board(),
            'columns' => array_filter(OrderStatus::cases(), fn (OrderStatus $status) => $status !== OrderStatus::Selesai),
            'lateAfterMinutes' => self::LATE_AFTER_MINUTES,
            'canProcess' => auth()->user()->can('process_orders'),
        ];
    }

    /**
     * The card sends the status it moves to, so a repeated click on a stale card is refused
     * instead of advancing a second step.
     */
    public function advance(int $orderId, ?string $to = null): void
    {
        abort_unless(auth()->user()->can('process_orders'), 403);

        $order = Order::findOrFail($orderId);
        $target = $to === null ? $order->statusEnum()->next() : OrderStatus::tryFrom($to);

        if ($target === null) {
            Notification::make()->title('Status pesanan tidak valid')->danger()->send();

            return;
        }

        try {
            app(OrderService::class)->updateStatus($order, $target);
        } catch (InvalidOrderTransitionException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();
        }
    }

    public function markPaid(int $orderId, string $method): void
    {
        abort_unless(auth()->user()->can('record_order_payment'), 403);

        $paymentMethod = PaymentMethod::tryFrom($method);

        if (! in_array($paymentMethod, OrdersTable::cashierMethods(), true)) {
            Notification::make()->title('Metode pembayaran tidak valid')->danger()->send();

            return;
        }

        try {
            app(OrderService::class)->markPaidAtCashier(
                Order::findOrFail($orderId),
                $paymentMethod,
                auth()->id(),
            );

            Notification::make()->title('Pembayaran dicatat')->success()->send();
        } catch (OrderBillingException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();
        }
    }
}
