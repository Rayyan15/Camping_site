<?php

namespace App\Filament\Resources\Bookings\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BookingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Pelanggan & Kode')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('code')
                                ->label('Kode Booking')
                                ->disabled()
                                ->required(),
                            Select::make('customer_id')
                                ->label('Pelanggan')
                                ->relationship('customer', 'name')
                                ->searchable()
                                ->required(),
                        ]),
                    ]),

                Section::make('Detail Menginap')
                    ->schema([
                        Grid::make(3)->schema([
                            DatePicker::make('check_in')
                                ->label('Check In')
                                ->required(),
                            DatePicker::make('check_out')
                                ->label('Check Out')
                                ->required(),
                            TextInput::make('guests')
                                ->label('Jumlah Tamu')
                                ->required()
                                ->numeric(),
                            Select::make('status')
                                ->label('Status Booking')
                                ->options([
                                    'pending_payment' => 'Menunggu Pembayaran',
                                    'confirmed' => 'Terkonfirmasi (Sudah Bayar)',
                                    'cancelled' => 'Dibatalkan',
                                    'completed' => 'Selesai / Check Out',
                                ])
                                ->required()
                                ->default('pending_payment'),
                            DateTimePicker::make('hold_expires_at')
                                ->label('Batas Waktu Hold')
                                ->disabled(),
                        ]),
                    ]),

                Section::make('Rincian Biaya')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('subtotal')
                                ->prefix('Rp')
                                ->disabled()
                                ->numeric(),
                            TextInput::make('tax')
                                ->label('Pajak (Tax)')
                                ->prefix('Rp')
                                ->disabled()
                                ->numeric()
                                ->default(0),
                            TextInput::make('total')
                                ->label('Total Akhir')
                                ->prefix('Rp')
                                ->disabled()
                                ->numeric(),
                            TextInput::make('paid_amount')
                                ->label('Sudah Dibayar')
                                ->prefix('Rp')
                                ->disabled()
                                ->numeric()
                                ->default(0),
                            Textarea::make('notes')
                                ->label('Catatan Tambahan')
                                ->default(null)
                                ->columnSpanFull(),
                        ]),
                    ]),
            ]);
    }
}
