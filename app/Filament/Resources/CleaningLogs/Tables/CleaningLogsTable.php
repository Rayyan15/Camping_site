<?php

namespace App\Filament\Resources\CleaningLogs\Tables;

use App\Enums\CleaningLogStatus;
use App\Exceptions\InvalidCleaningLogReviewException;
use App\Models\CleaningLog;
use App\Services\CleaningLogReviewService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CleaningLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('photo_path')
                    ->label('Foto')
                    ->square(),
                TextColumn::make('unit.code')
                    ->label('Tenda')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('employee.name')
                    ->label('Karyawan')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('cleaned_at')
                    ->label('Waktu Selesai')
                    ->dateTime('d M Y, H:i')
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(CleaningLogStatus::class),
            ])
            ->recordActions([
                self::approveAction(),
                self::rejectAction(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('Belum ada laporan kebersihan')
            ->emptyStateDescription('Laporan muncul setelah karyawan mengirim foto tenda yang sudah dibersihkan.')
            ->emptyStateIcon('heroicon-o-inbox')
            ->striped()
            ->defaultSort('created_at', 'desc');
    }

    private static function approveAction(): Action
    {
        return Action::make('approve')
            ->label('Setujui')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->authorize('review')
            ->visible(fn (CleaningLog $record): bool => $record->status === CleaningLogStatus::Pending)
            ->requiresConfirmation()
            ->modalHeading('Setujui laporan kebersihan')
            ->modalDescription(fn (CleaningLog $record): string => "Tenda {$record->unit?->code} sudah dicek dan sesuai standar.")
            ->modalSubmitActionLabel('Setujui')
            ->action(fn (CleaningLog $record) => self::review(fn (CleaningLogReviewService $service) => $service->approve($record), 'Laporan disetujui.'));
    }

    private static function rejectAction(): Action
    {
        return Action::make('reject')
            ->label('Minta bersihkan ulang')
            ->icon('heroicon-o-arrow-path')
            ->color('danger')
            ->authorize('review')
            ->visible(fn (CleaningLog $record): bool => $record->status === CleaningLogStatus::Pending)
            ->modalHeading('Minta tenda dibersihkan ulang')
            ->modalSubmitActionLabel('Kirim')
            ->schema([
                Textarea::make('reason')
                    ->label('Yang perlu diperbaiki')
                    ->required()
                    ->maxLength(500),
            ])
            ->action(fn (CleaningLog $record, array $data) => self::review(
                fn (CleaningLogReviewService $service) => $service->reject($record, $data['reason']),
                'Laporan dikembalikan untuk dibersihkan ulang.',
            ));
    }

    private static function review(callable $decision, string $successMessage): void
    {
        try {
            $decision(app(CleaningLogReviewService::class));
        } catch (InvalidCleaningLogReviewException $e) {
            Notification::make()->title($e->getMessage())->warning()->send();

            return;
        }

        Notification::make()->title($successMessage)->success()->send();
    }
}
