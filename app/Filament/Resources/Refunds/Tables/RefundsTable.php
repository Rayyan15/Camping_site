<?php

namespace App\Filament\Resources\Refunds\Tables;

use App\Enums\RefundStatus;
use App\Exceptions\RefundException;
use App\Models\Refund;
use App\Services\RefundService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class RefundsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('booking.code')
                    ->label('Kode Booking')
                    ->searchable()
                    ->weight('bold'),
                TextColumn::make('amount')
                    ->label('Nominal')
                    ->money('IDR', locale: 'id')
                    ->sortable(),
                TextColumn::make('reason')
                    ->label('Alasan')
                    ->limit(40)
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
                TextColumn::make('created_at')
                    ->label('Diajukan')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(RefundStatus::class),
            ])
            ->recordActions([
                self::decision('approve', 'Setujui', 'success', RefundStatus::Requested)
                    ->requiresConfirmation()
                    ->modalDescription('Booking akan ditandai dikembalikan dan tenda dilepas.')
                    ->action(fn (Refund $record) => self::run(fn () => app(RefundService::class)->approve($record, auth()->id()), 'Refund disetujui')),
                self::decision('reject', 'Tolak', 'danger', RefundStatus::Requested)
                    ->schema([Textarea::make('note')->label('Alasan penolakan')->required()])
                    ->action(fn (Refund $record, array $data) => self::run(fn () => app(RefundService::class)->reject($record, auth()->id(), $data['note']), 'Refund ditolak')),
                self::decision('markPaid', 'Tandai sudah ditransfer', 'info', RefundStatus::Approved)
                    ->requiresConfirmation()
                    ->action(fn (Refund $record) => self::run(fn () => app(RefundService::class)->markPaid($record, auth()->id()), 'Refund ditandai sudah ditransfer')),
            ])
            ->emptyStateHeading('Belum ada pengajuan refund')
            ->emptyStateDescription('Pengajuan dari tamu atau operator akan muncul di sini.')
            ->emptyStateIcon('heroicon-o-inbox')
            ->striped()
            ->defaultSort('created_at', 'desc');
    }

    private static function decision(string $name, string $label, string $color, RefundStatus $visibleFrom): Action
    {
        return Action::make($name)
            ->label($label)
            ->color($color)
            ->visible(fn (Refund $record): bool => (bool) auth()->user()?->can('approve_refund') && $record->status === $visibleFrom);
    }

    private static function run(callable $transition, string $successTitle): void
    {
        try {
            $transition();
        } catch (RefundException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();

            return;
        }

        Notification::make()->title($successTitle)->success()->send();
    }
}
