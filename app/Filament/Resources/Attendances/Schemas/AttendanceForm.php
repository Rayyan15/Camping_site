<?php

namespace App\Filament\Resources\Attendances\Schemas;

use App\Enums\AttendanceStatus;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Unique;

class AttendanceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('employee_id')
                    ->label('Karyawan')
                    ->relationship('employee', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                DatePicker::make('date')
                    ->label('Tanggal')
                    ->native(false)
                    ->required()
                    ->unique(
                        ignoreRecord: true,
                        modifyRuleUsing: fn (Unique $rule, Get $get) => $rule->where('employee_id', $get('employee_id')),
                    )
                    ->validationMessages(['unique' => 'Karyawan ini sudah punya catatan absensi pada tanggal tersebut.']),
                Select::make('status')
                    ->label('Status')
                    ->options(AttendanceStatus::class)
                    ->required()
                    ->live(),
                TextInput::make('note')
                    ->label('Alasan')
                    ->maxLength(255)
                    ->required(function (Get $get): bool {
                        $status = $get('status');
                        $status = $status instanceof AttendanceStatus ? $status : AttendanceStatus::tryFrom((string) $status);

                        return $status?->isManualAbsenceType() ?? false;
                    })
                    ->helperText('Wajib diisi untuk izin, sakit, dan alpa.'),
                TimePicker::make('clock_in')
                    ->label('Jam masuk')
                    ->seconds(false),
                TimePicker::make('clock_out')
                    ->label('Jam keluar')
                    ->seconds(false),
            ])
            ->columns(2);
    }
}
