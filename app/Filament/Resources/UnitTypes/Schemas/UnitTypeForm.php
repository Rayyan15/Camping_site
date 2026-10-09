<?php

namespace App\Filament\Resources\UnitTypes\Schemas;

use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class UnitTypeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Utama')
                    ->description('Kelola detail tipe tenda')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('name')
                                ->label('Nama Tenda')
                                ->required()
                                ->live(onBlur: true)
                                ->afterStateUpdated(function (Get $get, Set $set, ?string $old, ?string $state): void {
                                    $currentSlug = $get('slug');

                                    if (blank($currentSlug) || $currentSlug === Str::slug((string) $old)) {
                                        $set('slug', Str::slug((string) $state));
                                    }
                                }),
                            TextInput::make('slug')
                                ->label('Slug (URL)')
                                ->required()
                                ->alphaDash()
                                ->unique(ignoreRecord: true)
                                ->validationMessages([
                                    'unique' => 'Slug sudah dipakai tipe tenda lain.',
                                    'alpha_dash' => 'Slug hanya boleh berisi huruf, angka, strip, dan garis bawah.',
                                ]),
                            TextInput::make('capacity')
                                ->label('Kapasitas (Orang)')
                                ->required()
                                ->numeric(),
                            TagsInput::make('facilities')
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

                Section::make('Harga')
                    ->schema([
                        Grid::make(2)->schema([
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
                        ]),
                    ]),
            ]);
    }
}
