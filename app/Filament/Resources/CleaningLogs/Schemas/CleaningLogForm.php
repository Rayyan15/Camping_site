<?php

namespace App\Filament\Resources\CleaningLogs\Schemas;

use App\Enums\CleaningLogStatus;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CleaningLogForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Laporan Kebersihan')
                    ->description('Catatan merapihkan dan membersihkan tenda oleh OB/Karyawan')
                    ->schema([
                        Grid::make(2)->schema([
                            Select::make('unit_id')
                                ->label('Tenda')
                                ->relationship('unit', 'code', fn ($query) => $query->with('unitType'))
                                ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->code} - {$record->unitType?->name}")
                                ->searchable()
                                ->preload()
                                ->required(),
                            Select::make('employee_id')
                                ->label('Nama Karyawan/OB')
                                ->relationship('employee', 'name')
                                ->required(),
                            DateTimePicker::make('cleaned_at')
                                ->label('Waktu Selesai Dibersihkan')
                                ->default(now())
                                ->required(),
                            Select::make('status')
                                ->label('Status Pengecekan')
                                ->options(CleaningLogStatus::class)
                                ->default(CleaningLogStatus::Pending)
                                ->required(),
                            FileUpload::make('photo_path')
                                ->label('Bukti Foto Tenda Rapih')
                                ->image()
                                ->directory('cleaning_logs')
                                ->required()
                                ->columnSpanFull(),
                            Textarea::make('notes')
                                ->label('Catatan Tambahan (Bila ada)')
                                ->columnSpanFull(),
                        ]),
                    ]),
            ]);
    }
}
