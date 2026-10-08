<?php

namespace App\Filament\Widgets;

use App\Models\Booking;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class LatestBookings extends BaseWidget
{
    private const STATUS_LABELS = [
        'pending_payment' => 'Menunggu Bayar',
        'paid' => 'Lunas',
        'checked_in' => 'Check-in',
        'checked_out' => 'Check-out',
        'expired' => 'Kedaluwarsa',
        'cancelled' => 'Dibatalkan',
        'refunded' => 'Dikembalikan',
    ];

    protected static ?int $sort = 5;

    public static function canView(): bool
    {
        return (bool) auth()->user()?->can('view_occupancy_dashboard');
    }

    protected int|string|array $columnSpan = 2;

    public function table(Table $table): Table
    {
        return $table
            ->query(Booking::query()->with('customer')->latest()->limit(5))
            ->heading('Booking Terbaru')
            ->emptyStateHeading('Belum ada booking')
            ->columns([
                TextColumn::make('customer.name')
                    ->label('Pelanggan')
                    ->description(fn (Booking $record): string => 'Check-in: '.$record->check_in->translatedFormat('d M Y'))
                    ->weight('bold'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => self::STATUS_LABELS[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        'pending_payment' => 'warning',
                        'paid', 'checked_in' => 'success',
                        'cancelled', 'expired', 'refunded' => 'danger',
                        default => 'gray',
                    }),
            ])
            ->paginated(false);
    }
}
