<?php

namespace App\Filament\Resources\Employees\Schemas;

use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class EmployeeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Data Karyawan')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Nama')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('position')
                            ->label('Jabatan')
                            ->maxLength(255)
                            ->default(null),
                        Select::make('shift_id')
                            ->label('Shift')
                            ->relationship('shift', 'name')
                            ->searchable()
                            ->preload()
                            ->placeholder('Belum ditentukan'),
                    ]),
                Section::make('Absensi dan Akun')
                    ->description('ID fingerprint dipakai untuk mencocokkan data mesin absensi. Akun login hanya diisi untuk karyawan yang punya akses ke panel.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('fingerprint_id')
                            ->label('ID Fingerprint')
                            ->maxLength(64)
                            ->default(null)
                            ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? trim($state) : null)
                            ->unique(ignoreRecord: true)
                            ->validationMessages([
                                'unique' => 'ID fingerprint sudah dipakai karyawan lain.',
                            ]),
                        Select::make('user_id')
                            ->label('Akun Login (opsional)')
                            ->relationship('user', 'name', fn (Builder $query) => $query->role(User::PANEL_ROLES))
                            ->searchable()
                            ->preload()
                            ->placeholder('Tanpa akun login')
                            ->unique(ignoreRecord: true)
                            ->validationMessages([
                                'unique' => 'Akun ini sudah terhubung ke karyawan lain.',
                            ]),
                    ]),
            ]);
    }
}
