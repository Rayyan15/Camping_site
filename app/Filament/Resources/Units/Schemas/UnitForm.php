<?php

namespace App\Filament\Resources\Units\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class UnitForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Forms\Components\Section::make('Data Tenda')
                    ->schema([
                        \Filament\Forms\Components\Grid::make(2)->schema([
                            \Filament\Forms\Components\Select::make('unit_type_id')
                                ->label('Tipe Tenda')
                                ->relationship('unitType', 'name')
                                ->required(),
                            TextInput::make('code')
                                ->label('Nomor/Kode Tenda')
                                ->required(),
                            \Filament\Forms\Components\Select::make('status')
                                ->label('Status')
                                ->options([
                                    'active' => 'Aktif / Tersedia',
                                    'maintenance' => 'Perawatan (Maintenance)',
                                    'inactive' => 'Tidak Aktif'
                                ])
                                ->required()
                                ->default('active')
                                ->columnSpanFull(),
                        ])
                    ])
            ]);
    }
}
