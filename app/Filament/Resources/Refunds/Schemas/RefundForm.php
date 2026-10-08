<?php

namespace App\Filament\Resources\Refunds\Schemas;

use App\Enums\BookingStatus;
use App\Models\Booking;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class RefundForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Pengajuan pengembalian dana')
                    ->description('Nominal dihitung otomatis dari kebijakan refund dan jumlah yang sudah dibayar.')
                    ->schema([
                        Select::make('booking_id')
                            ->label('Booking lunas')
                            ->options(fn () => Booking::where('status', BookingStatus::Paid)->orderBy('code')->pluck('code', 'id'))
                            ->searchable()
                            ->required(),
                        Textarea::make('reason')
                            ->label('Alasan pembatalan')
                            ->required()
                            ->minLength(5)
                            ->maxLength(500),
                    ]),
            ]);
    }
}
