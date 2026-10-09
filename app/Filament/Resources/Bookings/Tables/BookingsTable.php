<?php

namespace App\Filament\Resources\Bookings\Tables;

use App\Enums\BookingStatus;
use App\Filament\Resources\Bookings\Actions\BookingActions;
use App\Models\Booking;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BookingsTable
{
    private const MINUTES_PER_HOUR = 60;

    private const HOURS_PER_DAY = 24;

    private static function reviewAge(Booking $record): ?string
    {
        if ($record->status !== BookingStatus::NeedsReview) {
            return null;
        }

        $minutes = (int) ($record->review_started_at ?? $record->updated_at)->diffInMinutes(now());
        $hours = intdiv($minutes, self::MINUTES_PER_HOUR);

        return match (true) {
            $hours >= self::HOURS_PER_DAY * 2 => 'sudah '.intdiv($hours, self::HOURS_PER_DAY).' hari',
            $hours >= 1 => "sudah {$hours} jam",
            default => "sudah {$minutes} menit",
        };
    }

    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Kode')
                    ->searchable()
                    ->copyable()
                    ->weight('bold'),
                TextColumn::make('customer.name')
                    ->label('Pelanggan')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('check_in')
                    ->label('Check In')
                    ->date('d M Y')
                    ->sortable(),
                TextColumn::make('check_out')
                    ->label('Check Out')
                    ->date('d M Y')
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->searchable(),
                TextColumn::make('review_age')
                    ->label('Umur review')
                    ->state(fn (Booking $record): ?string => self::reviewAge($record))
                    ->placeholder('-'),
                TextColumn::make('total')
                    ->label('Total Harga')
                    ->money('IDR', locale: 'id', decimalPlaces: 0)
                    ->sortable(),
                TextColumn::make('paid_amount')
                    ->label('Dibayar')
                    ->money('IDR', locale: 'id', decimalPlaces: 0)
                    ->sortable(),
                TextColumn::make('remaining')
                    ->label('Sisa Tagihan')
                    ->state(fn (Booking $record): int => BookingActions::remainingBalance($record))
                    ->money('IDR', locale: 'id', decimalPlaces: 0),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                BookingActions::checkIn(),
                BookingActions::checkOut(),
                BookingActions::recordPayment(),
                BookingActions::resolveReview(),
                EditAction::make(),
            ])
            ->emptyStateHeading('Belum ada data')
            ->emptyStateDescription('Data akan muncul di sini setelah ditambahkan.')
            ->emptyStateIcon('heroicon-o-inbox')
            ->striped()
            ->defaultSort('created_at', 'desc');
    }
}
