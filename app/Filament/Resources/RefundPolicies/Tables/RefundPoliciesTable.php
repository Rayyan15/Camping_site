<?php

namespace App\Filament\Resources\RefundPolicies\Tables;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RefundPoliciesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('min_days_before')
                    ->label('Batal minimal')
                    ->formatStateUsing(fn (int $state): string => $state === 0 ? 'Kurang dari tier lain' : $state.' hari sebelum check-in'),
                TextColumn::make('percent')
                    ->label('Refund')
                    ->suffix('%'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->emptyStateHeading('Belum ada aturan refund')
            ->emptyStateDescription('Tanpa tier, setiap pembatalan dihitung refund 0%. Tambahkan tier pertama.')
            ->emptyStateIcon('heroicon-o-receipt-refund')
            ->defaultSort('min_days_before', 'desc');
    }
}
