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
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->label('Kode pesanan')->searchable()->weight('bold'),
                TextColumn::make('customer_name')->label('Pemesan')->searchable()->placeholder('-'),
                TextColumn::make('source')
                    ->label('Sumber')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        Order::SOURCE_PREORDER => 'Pre-order',
                        Order::SOURCE_QR => 'QR',
                        default => 'Walk-in',
                    }),
                TextColumn::make('diningSpot.name')->label('Lokasi')->placeholder('-'),
                TextColumn::make('booking.code')->label('Booking')->searchable()->placeholder('-'),
                TextColumn::make('scheduled_at')->label('Waktu saji')->dateTime('d M Y, H:i')->placeholder('-')->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => OrderStatus::from($state)->label())
                    ->color(fn (string $state) => match (OrderStatus::from($state)) {
                        OrderStatus::Baru => 'danger',
                        OrderStatus::Diproses => 'warning',
                        OrderStatus::Siap, OrderStatus::Diantar => 'info',
                        OrderStatus::Selesai => 'success',
                    }),
                TextColumn::make('total')->label('Total')->money('IDR', locale: 'id', decimalPlaces: 0)->sortable(),
                TextColumn::make('payment_status')
                    ->label('Pembayaran')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => $state === Order::PAYMENT_PAID ? 'Lunas' : 'Belum lunas')
                    ->color(fn (string $state) => $state === Order::PAYMENT_PAID ? 'success' : 'danger'),
                IconColumn::make('bill_to_booking')->label('Gabung bill')->boolean(),
                TextColumn::make('created_at')->label('Dibuat')->dateTime('d M Y, H:i')->sortable()->toggleable(isToggledHiddenByDefault: true),
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
                        && auth()->user()?->can('process_orders'))
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
                        try {
                            app(OrderService::class)->markPaidAtCashier($record, PaymentMethod::from($data['method']), auth()->id());
                            Notification::make()->title('Pembayaran dicatat')->success()->send();
                        } catch (OrderBillingException $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();
                        }
                    }),
                EditAction::make(),
            ])
            ->emptyStateHeading('Belum ada pesanan')
            ->striped()
            ->defaultSort('created_at', 'desc');
    }
}
