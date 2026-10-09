<?php

namespace App\Filament\Resources\RefundPolicies\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class RefundPolicyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Tingkat refund')
                    ->description('Tamu yang membatalkan mendapat persentase dari tier dengan batas hari terbesar yang masih terpenuhi. Halaman publik dan hitungan refund membaca tabel ini langsung.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('min_days_before')
                            ->label('Minimal hari sebelum check-in')
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->maxValue(365)
                            ->suffix('hari')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->validationMessages(['unique' => 'Sudah ada tier untuk jumlah hari ini.']),
                        TextInput::make('percent')
                            ->label('Refund')
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->maxValue(100)
                            ->suffix('%')
                            ->required(),
                    ]),
            ]);
    }
}
