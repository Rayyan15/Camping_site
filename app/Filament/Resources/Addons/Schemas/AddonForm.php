<?php

namespace App\Filament\Resources\Addons\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class AddonForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Forms\Components\Section::make('Layanan Tambahan (Addon)')
                    ->schema([
                        \Filament\Forms\Components\Grid::make(2)->schema([
                            TextInput::make('name')
                                ->label('Nama Layanan')
                                ->required(),
                            TextInput::make('price')
                                ->label('Harga')
                                ->required()
                                ->numeric()
                                ->prefix('Rp'),
                            TextInput::make('unit')
                                ->label('Satuan (Misal: pax, porsi, unit)')
                                ->required(),
                            Toggle::make('is_active')
                                ->label('Tersedia / Aktif')
                                ->required()
                                ->default(true),
                        ])
                    ])
            ]);
    }
}
