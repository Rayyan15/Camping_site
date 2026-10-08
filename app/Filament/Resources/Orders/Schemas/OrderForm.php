<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Models\Order;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Orders are created by the QR page, the booking flow, or the walk-in page, which price them from
 * the menu. Here staff can only correct who and where; money and status move through services.
 */
class OrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Pesanan')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('code')->label('Kode pesanan')->disabled(),
                            Select::make('source')
                                ->label('Sumber')
                                ->options([
                                    Order::SOURCE_PREORDER => 'Pre-order',
                                    Order::SOURCE_QR => 'QR',
                                    Order::SOURCE_WALKIN => 'Walk-in',
                                ])
                                ->disabled(),
                            TextInput::make('customer_name')->label('Nama pemesan')->maxLength(80),
                            TextInput::make('customer_phone')->label('Nomor telepon')->maxLength(32),
                            Select::make('booking_id')
                                ->label('Booking terkait')
                                ->relationship('booking', 'code')
                                ->disabled(),
                            Select::make('dining_spot_id')
                                ->label('Meja atau tenda')
                                ->relationship('diningSpot', 'name')
                                ->searchable()
                                ->preload(),
                            DateTimePicker::make('scheduled_at')->label('Waktu saji')->disabled(),
                            TextInput::make('total')->label('Total')->prefix('Rp')->numeric()->disabled(),
                            TextInput::make('status')->label('Status')->disabled(),
                            TextInput::make('payment_status')->label('Pembayaran')->disabled(),
                            Toggle::make('bill_to_booking')->label('Ditagihkan ke booking')->disabled(),
                        ]),
                    ]),
            ]);
    }
}
