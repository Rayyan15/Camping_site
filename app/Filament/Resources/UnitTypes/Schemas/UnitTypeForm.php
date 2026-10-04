<?php

namespace App\Filament\Resources\UnitTypes\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class UnitTypeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Schemas\Components\Section::make('Informasi Utama')
                    ->description('Kelola detail tipe tenda')
                    ->schema([
                        \Filament\Schemas\Components\Grid::make(2)->schema([
                            TextInput::make('name')
                                ->label('Nama Tenda')
                                ->required(),
                            TextInput::make('slug')
                                ->label('Slug (URL)')
                                ->required(),
                            TextInput::make('capacity')
                                ->label('Kapasitas (Orang)')
                                ->required()
                                ->numeric(),
                            \Filament\Forms\Components\TagsInput::make('facilities')
                                ->label('Fasilitas Utama')
                                ->placeholder('Ketik fasilitas lalu tekan Enter')
                                ->separator(',')
                                ->columnSpanFull(),
                            Textarea::make('description')
                                ->label('Deskripsi')
                                ->default(null)
                                ->columnSpanFull(),
                        ]),
                    ]),

                \Filament\Schemas\Components\Section::make('Harga')
                    ->schema([
                        \Filament\Schemas\Components\Grid::make(2)->schema([
                            TextInput::make('base_price_weekday')
                                ->label('Harga Weekday')
                                ->prefix('Rp')
                                ->required()
                                ->numeric(),
                            TextInput::make('base_price_weekend')
                                ->label('Harga Weekend')
                                ->prefix('Rp')
                                ->required()
                                ->numeric(),
                        ])
                    ]),
            ]);
    }
}
