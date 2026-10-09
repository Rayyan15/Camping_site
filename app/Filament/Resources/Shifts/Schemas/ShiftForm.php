<?php

namespace App\Filament\Resources\Shifts\Schemas;

use App\Models\Shift;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ShiftForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Jam Kerja')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Nama Shift')
                            ->placeholder('Contoh: Shift Pagi')
                            ->required()
                            ->maxLength(100)
                            ->columnSpanFull(),
                        TimePicker::make('start_time')
                            ->label('Jam Mulai')
                            ->seconds(false)
                            ->required(),
                        TimePicker::make('end_time')
                            ->label('Jam Selesai')
                            ->seconds(false)
                            ->required()
                            ->different('start_time')
                            ->validationMessages([
                                'different' => 'Jam selesai tidak boleh sama dengan jam mulai.',
                            ])
                            ->helperText('Jika jam selesai lebih awal dari jam mulai (misalnya 22:00 sampai 06:00), shift dihitung berakhir di hari berikutnya.'),
                        TextInput::make('late_tolerance_minutes')
                            ->label('Toleransi Terlambat (menit)')
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->maxValue(Shift::MAX_LATE_TOLERANCE_MINUTES)
                            ->default(0)
                            ->required()
                            ->helperText('Karyawan masih dianggap tepat waktu sampai batas menit ini setelah jam mulai.'),
                    ]),
            ]);
    }
}
