<?php

namespace App\Notifications;

use App\Filament\Pages\KitchenQueue;
use App\Models\Order;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Notifications\Notification;

/** Stored in the database channel in the shape Filament's bell renders. */
class NewOrderNotification extends Notification
{
    public function __construct(private readonly Order $order) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $this->order->loadMissing('items.menuItem', 'diningSpot');

        $items = $this->order->items
            ->map(fn ($item) => $item->qty.'x '.($item->menuItem->name ?? '-'))
            ->implode(', ');
        $place = $this->order->diningSpot->name ?? 'Walk-in';

        return FilamentNotification::make()
            ->title("Pesanan baru {$this->order->code}")
            ->body("{$place}, {$this->order->customer_name}: {$items}")
            ->info()
            ->actions([
                Action::make('open')
                    ->label('Buka antrian dapur')
                    ->url(KitchenQueue::getUrl()),
            ])
            ->getDatabaseMessage();
    }
}
