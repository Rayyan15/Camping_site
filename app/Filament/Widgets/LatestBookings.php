<?php

namespace App\Filament\Widgets;

use App\Models\Booking;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class LatestBookings extends BaseWidget
{
    protected static ?int $sort = 3;
    
    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Booking::query()->latest()->limit(5)
            )
            ->heading('Booking Terbaru')
            ->columns([
                Tables\Columns\TextColumn::make('customer.name')
                    ->label('CUSTOMER')
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('code')
                    ->label('KODE')
                    ->searchable(),
                Tables\Columns\TextColumn::make('check_in')
                    ->label('CHECK-IN')
                    ->date('d M Y'),
                Tables\Columns\TextColumn::make('total')
                    ->label('TOTAL')
                    ->money('IDR', locale: 'id'),
                Tables\Columns\TextColumn::make('status')
                    ->label('STATUS')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'confirmed' => 'success',
                        'cancelled' => 'danger',
                        'completed' => 'gray',
                        default => 'gray',
                    }),
            ])
            ->emptyStateHeading('Belum ada booking')
            ->emptyStateDescription('Daftar booking terbaru akan muncul di sini.')
            ->emptyStateIcon('heroicon-o-bookmark-slash')
            ->striped()
            ->paginated(false);
    }
}
