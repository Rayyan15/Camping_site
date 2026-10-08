<?php

namespace App\Filament\Resources\DiningSpots\Schemas;

use App\Models\DiningSpot;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class DiningSpotForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Nama titik')->placeholder('Meja 1')->required()->maxLength(80),
            Select::make('type')
                ->label('Jenis')
                ->options([
                    DiningSpot::TYPE_TABLE => 'Meja',
                    DiningSpot::TYPE_TENT => 'Tenda',
                ])
                ->required()
                ->native(false),
            Select::make('unit_id')
                ->label('Unit tenda terkait')
                ->helperText('Isi untuk QR tenda agar tamu bisa menagihkan pesanan ke booking-nya.')
                ->relationship('unit', 'code')
                ->searchable()
                ->preload(),
        ]);
    }
}
