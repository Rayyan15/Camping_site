<?php

namespace App\Filament\Resources\Bookings\Schemas;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Services\BookingStatusTransition;
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
                                ->dehydrated(false),
                            Select::make('customer_id')
                                ->label('Pelanggan')
                                ->relationship('customer', 'name')
                                ->disabled()
                                ->dehydrated(false),
                        ]),
                    ]),

                Section::make('Detail Menginap')
                    ->schema([
                        Grid::make(3)->schema([
                            DatePicker::make('check_in')
                                ->label('Check In')
                                ->disabled()
                                ->dehydrated(false),
                            DatePicker::make('check_out')
                                ->label('Check Out')
                                ->disabled()
                                ->dehydrated(false),
                            TextInput::make('guests')
                                ->label('Jumlah Tamu')
                                ->disabled()
                                ->dehydrated(false)
                                ->numeric(),
                            Select::make('status')
                                ->label('Status Booking')
                                ->options(fn (?Booking $record): array => $record?->status
                                    ? app(BookingStatusTransition::class)->optionsFor($record->status)
                                    : [BookingStatus::PendingPayment->value => BookingStatus::PendingPayment->getLabel()])
                                ->required(),
                            DateTimePicker::make('hold_expires_at')
                                ->label('Batas Waktu Hold')
                                ->disabled()
                                ->dehydrated(false),
                        ]),
                    ]),

                Section::make('Rincian Biaya')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('subtotal')
                                ->prefix('Rp')
                                ->disabled()
                                ->dehydrated(false)
                                ->numeric(),
                            TextInput::make('tax')
                                ->label('Pajak (Tax)')
                                ->prefix('Rp')
                                ->disabled()
                                ->dehydrated(false)
                                ->numeric(),
                            TextInput::make('total')
                                ->label('Total Akhir')
                                ->prefix('Rp')
                                ->disabled()
                                ->dehydrated(false)
                                ->numeric(),
                            TextInput::make('paid_amount')
                                ->label('Sudah Dibayar')
                                ->prefix('Rp')
                                ->disabled()
                                ->dehydrated(false)
                                ->numeric(),
                            Textarea::make('notes')
                                ->label('Catatan Tambahan')
                                ->default(null)
                                ->columnSpanFull(),
                        ]),
                    ]),
            ]);
    }
}
