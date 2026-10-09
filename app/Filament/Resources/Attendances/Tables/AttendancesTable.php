<?php

namespace App\Filament\Resources\Attendances\Tables;

use App\Enums\AttendanceSource;
use App\Enums\AttendanceStatus;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AttendancesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('employee.name')
                    ->label('Karyawan')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('date')
                    ->label('Tanggal')
                    ->date('d M Y')
                    ->sortable(),
                TextColumn::make('clock_in')
                    ->label('Masuk')
                    ->time('H:i')
                    ->placeholder('Tidak ada'),
                TextColumn::make('clock_out')
                    ->label('Keluar')
                    ->time('H:i')
                    ->placeholder('Tidak ada'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
                TextColumn::make('source')
                    ->label('Sumber')
                    ->badge()
                    ->color(fn (AttendanceSource $state) => $state === AttendanceSource::Fingerprint ? 'primary' : 'gray'),
                TextColumn::make('note')
                    ->label('Alasan')
                    ->limit(40)
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('employee_id')
                    ->label('Karyawan')
                    ->relationship('employee', 'name')
                    ->searchable()
                    ->preload()
                    ->visible(fn () => auth()->user()?->can('view_any_attendance') ?? false),
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(AttendanceStatus::class),
                Filter::make('date_range')
                    ->label('Rentang tanggal')
                    ->schema([
                        DatePicker::make('from')->label('Dari tanggal')->native(false),
                        DatePicker::make('until')->label('Sampai tanggal')->native(false),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn (Builder $q, string $date) => $q->where('date', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $q, string $date) => $q->where('date', '<=', $date))),
            ])
            ->recordActions([
                EditAction::make()->visible(fn () => auth()->user()?->can('update_attendance') ?? false),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('date', 'desc')
            ->emptyStateHeading('Belum ada catatan absensi')
            ->emptyStateDescription('Impor log fingerprint dari tombol di atas tabel, atau catat izin, sakit, atau alpa secara manual.')
            ->emptyStateIcon('heroicon-o-finger-print')
            ->striped();
    }
}
