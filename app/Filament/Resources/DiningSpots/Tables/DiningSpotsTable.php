<?php

namespace App\Filament\Resources\DiningSpots\Tables;

use App\Filament\Resources\DiningSpots\DiningSpotResource;
use App\Models\DiningSpot;
use App\Services\DiningSpotService;
use Filament\Actions\Action;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DiningSpotsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Titik')->searchable()->weight('bold'),
                TextColumn::make('type')
                    ->label('Jenis')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => $state === DiningSpot::TYPE_TENT ? 'Tenda' : 'Meja'),
                TextColumn::make('unit.code')->label('Unit')->placeholder('-'),
                TextColumn::make('qr_url')
                    ->label('Tautan QR')
                    ->state(fn (DiningSpot $record) => $record->orderUrl())
                    ->copyable()
                    ->limit(40),
            ])
            ->recordActions([
                Action::make('print')
                    ->label('Cetak QR')
                    ->icon('heroicon-o-printer')
                    ->url(fn (DiningSpot $record) => DiningSpotResource::getUrl('print', ['spot' => $record->id]))
                    ->openUrlInNewTab(),
                Action::make('regenerate')
                    ->label('Ganti QR')
                    ->icon('heroicon-o-arrow-path')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Ganti kode QR?')
                    ->modalDescription('QR yang sudah tercetak langsung tidak berlaku. Cetak ulang dan pasang QR baru.')
                    ->action(function (DiningSpot $record) {
                        app(DiningSpotService::class)->regenerateToken($record);

                        Notification::make()->title('Kode QR diganti')->success()->send();
                    }),
                EditAction::make(),
            ])
            ->headerActions([
                Action::make('print_all')
                    ->label('Cetak semua QR')
                    ->icon('heroicon-o-printer')
                    ->url(DiningSpotResource::getUrl('print'))
                    ->openUrlInNewTab(),
            ])
            ->toolbarActions([DeleteBulkAction::make()])
            ->defaultSort('name')
            ->emptyStateHeading('Belum ada titik QR');
    }
}
