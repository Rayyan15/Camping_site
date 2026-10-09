<?php

namespace App\Filament\Resources\SpecialPrices\Schemas;

use App\Models\SpecialPrice;
use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

class SpecialPriceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Harga khusus per tanggal')
                ->description('Harga ini menggantikan harga weekday atau weekend untuk satu malam yang dimulai pada tanggal tersebut.')
                ->columns(2)
                ->schema([
                    Select::make('unit_type_id')
                        ->label('Tipe Tenda')
                        ->relationship('unitType', 'name')
                        ->searchable()
                        ->preload()
                        ->required(),
                    ...self::dateAndPriceFields(fn (Get $get) => $get('unit_type_id')),
                ]),
        ]);
    }

    /**
     * Shared with the relation manager on UnitType, which supplies the unit type from its owner record.
     *
     * @param  callable(Get): mixed  $unitTypeId
     * @return array<int, mixed>
     */
    public static function dateAndPriceFields(callable $unitTypeId): array
    {
        return [
            DatePicker::make('date')
                ->label('Tanggal malam')
                ->native(false)
                ->displayFormat('d M Y')
                ->required()
                ->minDate(fn (string $operation) => $operation === 'create' ? today() : null)
                // The date column is stored as a datetime, so the built-in unique rule would never match.
                ->rule(fn (Get $get, ?Model $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($unitTypeId, $get, $record) {
                    $exists = SpecialPrice::where('unit_type_id', $unitTypeId($get))
                        ->whereDate('date', $value)
                        ->when($record, fn ($query) => $query->whereKeyNot($record->getKey()))
                        ->exists();

                    if ($exists) {
                        $fail('Harga khusus untuk tipe tenda dan tanggal ini sudah ada. Ubah data yang sudah ada.');
                    }
                })
                ->validationMessages(['after_or_equal' => 'Tanggal tidak boleh di masa lalu.']),
            TextInput::make('price')
                ->label('Harga per malam')
                ->required()
                ->integer()
                ->minValue(1)
                ->prefix('Rp')
                ->validationMessages(['min' => 'Harga harus lebih dari 0.']),
            Textarea::make('note')
                ->label('Catatan')
                ->placeholder('Contoh: malam tahun baru')
                ->maxLength(255)
                ->columnSpanFull(),
        ];
    }
}
