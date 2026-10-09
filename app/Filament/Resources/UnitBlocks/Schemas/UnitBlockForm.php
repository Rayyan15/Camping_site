<?php

namespace App\Filament\Resources\UnitBlocks\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UnitBlockForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Blokir tenda')
                ->description('Tenda yang diblokir tidak muncul di ketersediaan dan tidak bisa dipesan pada rentang ini. Tanggal selesai ikut diblokir.')
                ->columns(2)
                ->schema([
                    Select::make('unit_id')
                        ->label('Tenda')
                        ->relationship('unit', 'code', fn ($query) => $query->with('unitType'))
                        ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->code} - {$record->unitType?->name}")
                        ->searchable()
                        ->preload()
                        ->required()
                        ->columnSpanFull(),
                    DatePicker::make('start_date')
                        ->label('Mulai tanggal')
                        ->native(false)
                        ->displayFormat('d M Y')
                        ->required(),
                    DatePicker::make('end_date')
                        ->label('Sampai tanggal')
                        ->native(false)
                        ->displayFormat('d M Y')
                        ->required()
                        ->afterOrEqual('start_date')
                        ->validationMessages(['after_or_equal' => 'Tanggal selesai tidak boleh sebelum tanggal mulai.']),
                    Textarea::make('reason')
                        ->label('Alasan')
                        ->placeholder('Contoh: perbaikan tiang, ganti terpal')
                        ->maxLength(255)
                        ->columnSpanFull(),
                ]),
        ]);
    }
}
