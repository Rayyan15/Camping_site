<?php

namespace App\Filament\Resources\SpecialPrices\Schemas;

use App\Models\SpecialPrice;
use App\Services\SpecialPriceService;
use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class SpecialPriceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Harga khusus per tanggal')
                ->description('Harga ini menggantikan harga weekday atau weekend. Isi "Sampai tanggal" untuk memberi harga yang sama ke satu minggu, satu bulan, atau rentang lain sekaligus.')
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
                ->label(fn (string $operation): string => $operation === 'create' ? 'Mulai tanggal' : 'Tanggal malam')
                ->native(false)
                ->displayFormat('d M Y')
                ->required()
                ->minDate(fn (string $operation) => $operation === 'create' ? today() : null)
                // The date column is stored as a datetime, so the built-in unique rule would never match.
                // A range overwrites its nights on purpose, so only a single night is checked for duplicates.
                ->rule(fn (Get $get, ?Model $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($unitTypeId, $get, $record) {
                    if (filled($get('date_until'))) {
                        return;
                    }

                    $exists = SpecialPrice::where('unit_type_id', $unitTypeId($get))
                        ->whereDate('date', $value)
                        ->when($record, fn ($query) => $query->whereKeyNot($record->getKey()))
                        ->exists();

                    if ($exists) {
                        $fail('Harga khusus untuk tipe tenda dan tanggal ini sudah ada. Ubah data yang sudah ada.');
                    }
                })
                ->validationMessages(['after_or_equal' => 'Tanggal tidak boleh di masa lalu.']),
            DatePicker::make('date_until')
                ->label('Sampai tanggal (opsional)')
                ->helperText('Kosongkan untuk satu malam. Malam yang sudah punya harga khusus ikut diganti.')
                ->native(false)
                ->displayFormat('d M Y')
                ->afterOrEqual('date')
                ->maxDate(fn (Get $get) => filled($get('date')) ? Carbon::parse($get('date'))->addDays(SpecialPriceService::MAX_RANGE_NIGHTS - 1) : null)
                ->visibleOn('create')
                ->dehydrated(false)
                ->validationMessages([
                    'after_or_equal' => 'Sampai tanggal harus sama atau setelah tanggal mulai.',
                    'before_or_equal' => 'Rentang maksimal '.SpecialPriceService::MAX_RANGE_NIGHTS.' malam.',
                ]),
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
