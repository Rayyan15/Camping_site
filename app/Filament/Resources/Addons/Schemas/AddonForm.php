<?php

namespace App\Filament\Resources\Addons\Schemas;

use App\Enums\AddonUnit;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AddonForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Layanan Tambahan (Addon)')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('name')
                                ->label('Nama Layanan')
                                ->required(),
                            TextInput::make('price')
                                ->label('Harga')
                                ->required()
                                ->numeric()
                                ->default(0)
                                ->prefix('Rp'),
                            Select::make('unit')
                                ->label('Satuan')
                                ->options(AddonUnit::options())
                                ->default(AddonUnit::PerItem->value)
                                ->helperText('Per malam dikalikan jumlah malam menginap, per item dihitung sekali.')
                                ->required(),
                            TextInput::make('extra_guests')
                                ->label('Tambahan tamu per item')
                                ->numeric()
                                ->integer()
                                ->minValue(0)
                                ->maxValue(4)
                                ->default(0)
                                ->required()
                                ->helperText('Jumlah tamu yang ditambah tiap item, misalnya 2 untuk extra bed 2 pax. Add-on dengan angka di atas 0 hanya muncul di tipe tenda yang "Maks. Tamu Tambahan per Tenda"-nya di atas 0. Isi 0 untuk add-on biasa yang muncul di semua tenda.'),
                            Toggle::make('is_active')
                                ->label('Tersedia / Aktif')
                                ->required()
                                ->default(true),
                        ]),
                    ]),
            ]);
    }
}
