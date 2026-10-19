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
                            // Codes alone look alike, so each option carries the guest, stay, and amount the operator recognises.
                            ->options(fn () => Booking::with('customer')->where('status', BookingStatus::Paid)->orderBy('check_in')->get()
                                ->mapWithKeys(fn (Booking $booking) => [$booking->id => sprintf(
                                    '%s, %s, %s, Rp %s',
                                    $booking->code,
                                    $booking->customer->name,
                                    $booking->check_in->locale('id')->translatedFormat('j M Y'),
                                    number_format($booking->total, 0, ',', '.'),
                                )]))
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
