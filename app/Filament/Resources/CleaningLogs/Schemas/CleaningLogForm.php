<?php

namespace App\Filament\Resources\CleaningLogs\Schemas;

use Filament\Schemas\Schema;

class CleaningLogForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Forms\Components\Section::make('Laporan Kebersihan')
                    ->description('Catatan merapihkan dan membersihkan tenda oleh OB/Karyawan')
                    ->schema([
                        \Filament\Forms\Components\Grid::make(2)->schema([
                            \Filament\Forms\Components\Select::make('unit_id')
                                ->label('Tenda')
                                ->relationship('unit', 'name')
                                ->required(),
                            \Filament\Forms\Components\Select::make('employee_id')
                                ->label('Nama Karyawan/OB')
                                ->relationship('employee', 'name')
                                ->required(),
                            \Filament\Forms\Components\DateTimePicker::make('cleaned_at')
                                ->label('Waktu Selesai Dibersihkan')
                                ->default(now())
                                ->required(),
                            \Filament\Forms\Components\Select::make('status')
                                ->label('Status Pengecekan')
                                ->options([
                                    'pending' => 'Menunggu Pengecekan',
                                    'approved' => 'Disetujui / Sesuai Standar',
                                    'rejected' => 'Perlu Dibersihkan Ulang'
                                ])
                                ->default('pending')
                                ->required(),
                            \Filament\Forms\Components\FileUpload::make('photo_path')
                                ->label('Bukti Foto Tenda Rapih')
                                ->image()
                                ->directory('cleaning_logs')
                                ->required()
                                ->columnSpanFull(),
                            \Filament\Forms\Components\Textarea::make('notes')
                                ->label('Catatan Tambahan (Bila ada)')
                                ->columnSpanFull(),
                        ])
                    ])
            ]);
    }
}
