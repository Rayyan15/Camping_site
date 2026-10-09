<?php

namespace App\Filament\Resources\Customers\RelationManagers;

use App\Filament\Resources\Bookings\BookingResource;
use App\Models\Booking;
use App\Models\Customer;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** Read-only booking history of a customer, newest stay first. */
class BookingsRelationManager extends RelationManager
{
    protected static string $relationship = 'bookings';

    protected static ?string $title = 'Riwayat booking';

    protected static ?string $modelLabel = 'booking';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return (bool) auth()->user()?->can('viewSpend', Customer::class)
            && parent::canViewForRecord($ownerRecord, $pageClass);
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('code')
            ->columns([
                TextColumn::make('code')
                    ->label('Kode')
                    ->searchable(),
                TextColumn::make('check_in')
                    ->label('Check-in')
                    ->date('d M Y')
                    ->sortable(),
                TextColumn::make('check_out')
                    ->label('Check-out')
                    ->date('d M Y'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
                TextColumn::make('total')
                    ->label('Total')
                    ->money('IDR', locale: 'id', decimalPlaces: 0),
                TextColumn::make('paid_amount')
                    ->label('Dibayar')
                    ->money('IDR', locale: 'id', decimalPlaces: 0),
            ])
            ->recordActions([
                Action::make('open')
                    ->label('Buka booking')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (Booking $record): string => BookingResource::getUrl('edit', ['record' => $record]))
                    ->visible(fn (Booking $record): bool => BookingResource::canEdit($record)),
            ])
            ->emptyStateHeading('Belum ada booking')
            ->emptyStateDescription('Booking pelanggan ini akan tampil di sini setelah dibuat.')
            ->emptyStateIcon('heroicon-o-calendar-days')
            ->striped()
            ->defaultSort('check_in', 'desc');
    }
}
