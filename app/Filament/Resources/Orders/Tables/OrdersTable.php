<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Exceptions\OrderBillingException;
use App\Models\Order;
use App\Services\OrderService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OrdersTable
{
    /**
     * Methods a cashier may record on the spot.
     *
     * @return array<int, PaymentMethod>
     */
    public static function cashierMethods(): array
    {
        return [PaymentMethod::Cash, PaymentMethod::Manual, PaymentMethod::Transfer];
    }

    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                // Related fields share a cell as a second line so all columns fit without sideways scrolling.
                TextColumn::make('code')
                    ->label('Pesanan')
                    ->weight('bold')
                    ->description(fn (Order $record) => $record->customer_name ?: '-')
                    ->searchable(query: fn (Builder $query, string $search) => $query->where(fn (Builder $inner) => $inner
                        ->where('code', 'like', "%{$search}%")
                        ->orWhere('customer_name', 'like', "%{$search}%")
                        ->orWhereHas('booking', fn (Builder $booking) => $booking->where('code', 'like', "%{$search}%")))),
                TextColumn::make('source')
                    ->label('Sumber')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        Order::SOURCE_PREORDER => 'Pre-order',
                        Order::SOURCE_QR => 'QR',
                        default => 'Walk-in',
                    })
                    ->description(fn (Order $record) => $record->diningSpot?->name ?? ($record->booking ? 'Booking '.$record->booking->code : null)),
                TextColumn::make('scheduled_at')->label('Waktu saji')->dateTime('d/m/y H:i')->placeholder('-')->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => OrderStatus::from($state)->label())
                    ->color(fn (string $state) => match (OrderStatus::from($state)) {
                        OrderStatus::Baru => 'danger',
                        OrderStatus::Diproses => 'warning',
                        OrderStatus::Siap, OrderStatus::Diantar => 'info',
                        OrderStatus::Selesai => 'success',
                        OrderStatus::Dibatalkan => 'gray',
                    }),
                TextColumn::make('total')->label('Total')->money('IDR', locale: 'id', decimalPlaces: 0)->sortable(),
                TextColumn::make('payment_status')
                    ->label('Pembayaran')
                    ->badge()
                    ->formatStateUsing(fn (string $state, Order $record) => match (true) {
                        $state === Order::PAYMENT_PAID => 'Lunas',
                        $record->bill_to_booking => 'Tagih ke booking',
                        default => 'Belum lunas',
                    })
                    ->color(fn (string $state, Order $record) => match (true) {
                        $state === Order::PAYMENT_PAID => 'success',
                        $record->bill_to_booking => 'gray',
                        default => 'danger',
                    }),
                TextColumn::make('created_at')->label('Dibuat')->dateTime('d/m/y H:i')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('source')->label('Sumber')->options([
                    Order::SOURCE_PREORDER => 'Pre-order',
                    Order::SOURCE_QR => 'QR',
                    Order::SOURCE_WALKIN => 'Walk-in',
                ]),
                SelectFilter::make('status')->label('Status')->options(
                    collect(OrderStatus::cases())->mapWithKeys(fn (OrderStatus $status) => [$status->value => $status->label()])->all(),
                ),
            ])
            ->recordActions([
                Action::make('markPaid')
                    ->label('Terima bayar')
                    ->icon('heroicon-o-banknotes')
                    ->visible(fn (Order $record) => ! $record->isPaid()
                        && ! $record->bill_to_booking
                        && auth()->user()?->can('record_order_payment'))
                    ->schema([
                        Select::make('method')
                            ->label('Metode')
                            ->options([
                                PaymentMethod::Cash->value => 'Tunai',
                                PaymentMethod::Manual->value => 'QRIS',
                                PaymentMethod::Transfer->value => 'Transfer',
                            ])
                            ->required(),
                    ])
                    ->action(function (Order $record, array $data) {
                        abort_unless(auth()->user()?->can('record_order_payment'), 403);

                        try {
                            app(OrderService::class)->markPaidAtCashier($record, PaymentMethod::from($data['method']), auth()->id());
                            Notification::make()->title('Pembayaran dicatat')->success()->send();
                        } catch (OrderBillingException $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();
                        }
                    }),
                EditAction::make(),
            ])
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['diningSpot', 'booking']))
            ->emptyStateHeading('Belum ada pesanan')
            ->striped()
            ->defaultSort('created_at', 'desc');
    }
}
