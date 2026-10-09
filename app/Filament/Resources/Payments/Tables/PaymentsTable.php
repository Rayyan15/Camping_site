<?php

namespace App\Filament\Resources\Payments\Tables;

use App\Filament\Resources\Payments\PaymentLabels;
use App\Models\Payment;
use App\Models\User;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PaymentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('payable'))
            ->columns([
                TextColumn::make('paid_at')
                    ->label('Waktu bayar')
                    ->dateTime('d M Y H:i')
                    ->placeholder('Belum dibayar')
                    ->sortable(),
                TextColumn::make('payable_code')
                    ->label('Kode')
                    ->state(fn (Payment $record): string => PaymentLabels::payableCode($record))
                    ->description(fn (Payment $record): string => PaymentLabels::payableType($record))
                    ->weight('bold'),
                TextColumn::make('payer')
                    ->label('Atas nama')
                    ->state(fn (Payment $record): string => PaymentLabels::payerName($record)),
                TextColumn::make('direction')
                    ->label('Arah')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => PaymentLabels::direction($state))
                    ->color(fn ($state): string => PaymentLabels::directionColor($state)),
                TextColumn::make('method')
                    ->label('Metode')
                    ->formatStateUsing(fn ($state): string => PaymentLabels::method($state)),
                TextColumn::make('amount')
                    ->label('Nominal')
                    ->money('IDR', locale: 'id', decimalPlaces: 0)
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => PaymentLabels::status($state))
                    ->color(fn ($state): string => PaymentLabels::statusColor($state)),
                TextColumn::make('attention')
                    ->label('Catatan')
                    ->state(fn (Payment $record): ?string => PaymentLabels::attentionNote($record))
                    ->wrap()
                    ->color('warning')
                    ->toggleable(),
                TextColumn::make('recorded_by')
                    ->label('Dicatat oleh')
                    ->formatStateUsing(fn (?int $state): string => $state ? self::staffName($state) : 'Sistem')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('method')->label('Metode')->options(PaymentLabels::methods()),
                SelectFilter::make('status')->label('Status')->options(PaymentLabels::statuses()),
                SelectFilter::make('direction')->label('Arah')->options(PaymentLabels::directions()),
                Filter::make('paid_range')
                    ->label('Tanggal bayar')
                    ->schema([
                        DatePicker::make('from')->label('Dari tanggal'),
                        DatePicker::make('until')->label('Sampai tanggal'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $q, string $date) => $q->whereDate('paid_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $q, string $date) => $q->whereDate('paid_at', '<=', $date))),
                Filter::make('needs_review')
                    ->label('Perlu ditinjau')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('review_reason')),
            ])
            ->recordActions([
                ViewAction::make()->label('Detail'),
            ])
            ->emptyStateHeading('Belum ada pembayaran')
            ->emptyStateDescription('Pembayaran dari tamu dan pengembalian dana akan muncul di sini.')
            ->emptyStateIcon('heroicon-o-credit-card')
            ->striped()
            ->defaultSort('created_at', 'desc');
    }

    /** One lookup per request, so the toggleable column never causes a query per row. */
    private static function staffName(int $userId): string
    {
        static $names = null;
        $names ??= User::pluck('name', 'id');

        return $names[$userId] ?? 'Akun dihapus';
    }
}
