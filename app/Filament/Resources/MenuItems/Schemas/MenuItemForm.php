<?php

namespace App\Filament\Resources\MenuItems\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class MenuItemForm
{
    private const PHOTO_MAX_KB = 2048;

    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Menu')
                ->columns(2)
                ->schema([
                    TextInput::make('name')->label('Nama menu')->required()->maxLength(120),
                    Select::make('category_id')
                        ->label('Kategori')
                        ->relationship('category', 'name')
                        ->searchable()
                        ->preload()
                        ->required(),
                    Textarea::make('description')->label('Deskripsi')->rows(3)->maxLength(300)->columnSpanFull(),
                    TextInput::make('price')->label('Harga')->numeric()->prefix('Rp')->minValue(0)->required(),
                    TextInput::make('sort_order')->label('Urutan di kategori')->numeric()->default(0)->required(),
                    FileUpload::make('photo')
                        ->label('Foto')
                        ->image()
                        ->disk('public')
                        ->directory('menu')
                        ->maxSize(self::PHOTO_MAX_KB)
                        ->helperText('JPG atau PNG, maksimal 2 MB.')
                        ->columnSpanFull(),
                    Toggle::make('is_available')->label('Tersedia')->default(true),
                ]),
        ]);
    }
}
