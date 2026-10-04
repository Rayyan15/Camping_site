<?php

namespace App\Filament\Resources\Orders\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class OrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Forms\Components\Section::make('Data Pesanan Makanan/Layanan')
                    ->schema([
                        \Filament\Forms\Components\Grid::make(2)->schema([
                            TextInput::make('code')
                                ->label('Kode Pesanan')
                                ->disabled()
                                ->required(),
                            \Filament\Forms\Components\Select::make('source')
                                ->label('Sumber Pesanan')
                                ->options([
                                    'pre_order' => 'Pre-Order (Saat Booking)',
                                    'resto' => 'Restoran',
                                    'tenda' => 'Layanan Antar ke Tenda'
                                ])
                                ->required(),
                            \Filament\Forms\Components\Select::make('booking_id')
                                ->label('Terkait Booking')
                                ->relationship('booking', 'code')
                                ->searchable()
                                ->default(null),
                            \Filament\Forms\Components\Select::make('dining_spot_id')
                                ->label('Tempat Makan')
                                ->relationship('diningSpot', 'name')
                                ->default(null),
                            DateTimePicker::make('scheduled_at')
                                ->label('Waktu Diantar/Disajikan'),
                            \Filament\Forms\Components\Select::make('status')
                                ->label('Status Pesanan')
                                ->options([
                                    'baru' => 'Pesanan Baru',
                                    'diproses' => 'Sedang Diproses',
                                    'diantar' => 'Sedang Diantar',
                                    'selesai' => 'Selesai'
                                ])
                                ->required()
                                ->default('baru'),
                            TextInput::make('total')
                                ->label('Total')
                                ->prefix('Rp')
                                ->required()
                                ->numeric(),
                            \Filament\Forms\Components\Select::make('payment_status')
                                ->label('Status Pembayaran')
                                ->options([
                                    'unpaid' => 'Belum Lunas',
                                    'paid' => 'Lunas'
                                ])
                                ->required()
                                ->default('unpaid'),
                            Toggle::make('bill_to_booking')
                                ->label('Tagihkan ke Billing Tenda')
                                ->required(),
                        ])
                    ])
            ]);
    }
}
