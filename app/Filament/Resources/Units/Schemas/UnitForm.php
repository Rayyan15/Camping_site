<?php

namespace App\Filament\Resources\Units\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UnitForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Data Tenda')
                    ->schema([
                        Grid::make(2)->schema([
                            Select::make('unit_type_id')
                                ->label('Tipe Tenda')
                                ->relationship('unitType', 'name')
                                ->required(),
                            TextInput::make('code')
                                ->label('Nomor/Kode Tenda')
                                ->required(),
                            Select::make('status')
                                ->label('Status')
                                ->options([
                                    'active' => 'Aktif / Tersedia',
                                    'maintenance' => 'Perawatan (Maintenance)',
                                    'inactive' => 'Tidak Aktif',
                                ])
                                ->required()
                                ->default('active')
                                ->columnSpanFull(),
                        ]),
                    ]),
            ]);
    }
}
