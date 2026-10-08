<?php

namespace App\Filament\Resources\ActivityLogs\Tables;

use App\Models\ActivityLog;
use App\Services\ActivityLogger;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ActivityLogsTable
{
    private const ACTION_LABELS = [
        ActivityLogger::ACTION_CREATED => 'Dibuat',
        ActivityLogger::ACTION_UPDATED => 'Diubah',
        ActivityLogger::ACTION_DELETED => 'Dihapus',
        ActivityLogger::ACTION_STATUS_CHANGED => 'Status berubah',
    ];

    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Waktu')
                    ->dateTime('d M Y H:i:s')
                    ->sortable(),
                TextColumn::make('actor_label')
                    ->label('Pelaku'),
                TextColumn::make('subject_type')
                    ->label('Objek')
                    ->formatStateUsing(fn (?string $state, ActivityLog $record): string => str($state)->replace('_', ' ')->headline().' #'.$record->subject_id),
                TextColumn::make('action')
                    ->label('Aksi')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => self::ACTION_LABELS[$state] ?? $state),
                TextColumn::make('changes')
                    ->label('Perubahan')
                    ->formatStateUsing(fn (ActivityLog $record): string => self::summarize($record))
                    ->limit(200)
                    ->wrap(),
            ])
            ->filters([
                SelectFilter::make('subject_type')
                    ->label('Objek')
                    ->options(fn (): array => collect(array_keys(config('morph_map')))
                        ->mapWithKeys(fn (string $alias): array => [$alias => (string) str($alias)->replace('_', ' ')->headline()])
                        ->all()),
                SelectFilter::make('user_id')
                    ->label('Pelaku')
                    ->relationship('user', 'name'),
                Filter::make('created_between')
                    ->label('Rentang tanggal')
                    ->schema([
                        DatePicker::make('from')->label('Dari'),
                        DatePicker::make('until')->label('Sampai'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $q, string $date) => $q->whereDate('created_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $q, string $date) => $q->whereDate('created_at', '<=', $date))),
            ])
            ->emptyStateHeading('Belum ada aktivitas tercatat')
            ->emptyStateDescription('Perubahan booking, pembayaran, refund, dan harga akan muncul di sini.')
            ->emptyStateIcon('heroicon-o-clipboard-document-list')
            ->striped()
            ->defaultSort('created_at', 'desc');
    }

    private static function summarize(ActivityLog $record): string
    {
        $old = $record->changes['old'] ?? [];
        $new = $record->changes['new'] ?? [];

        return collect($new ?: $old)
            ->map(function (mixed $value, string $field) use ($old, $new): string {
                $before = $old[$field] ?? null;
                $after = $new[$field] ?? null;

                return $before !== null && $after !== null
                    ? $field.': '.self::scalar($before).' -> '.self::scalar($after)
                    : $field.': '.self::scalar($after ?? $before);
            })
            ->implode(', ');
    }

    private static function scalar(mixed $value): string
    {
        return is_scalar($value) || $value === null ? (string) $value : json_encode($value);
    }
}
