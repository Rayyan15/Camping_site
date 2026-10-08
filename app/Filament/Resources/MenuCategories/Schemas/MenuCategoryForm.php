<?php

namespace App\Filament\Resources\MenuCategories\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class MenuCategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label('Nama kategori')
                ->required()
                ->maxLength(80),
            TextInput::make('sort_order')
                ->label('Urutan tampil')
                ->helperText('Angka kecil tampil lebih dulu di menu QR.')
                ->numeric()
                ->default(0)
                ->required(),
        ]);
    }
}
