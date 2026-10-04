<?php
namespace App\Filament\Widgets;
use App\Models\Booking;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
class LatestBookings extends BaseWidget
{
    protected static ?int $sort = 5;
    protected int | string | array $columnSpan = 2;
    public function table(Table $table): Table
    {
        return $table
            ->query(Booking::query()->latest()->limit(5))
            ->heading('Daftar Tugas')
            ->columns([
                Tables\Columns\TextColumn::make('customer.name')
                    ->label('Tugas / Pelanggan')
                    ->description(fn (Booking $record): string => 'Jadwal: ' . $record->check_in->format('M d, Y'))
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('status')
                    ->label('')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning', 'confirmed' => 'success', 'cancelled' => 'danger', default => 'gray',
                    }),
            ])
            ->paginated(false);
    }
}