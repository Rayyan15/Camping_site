<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property array<int, string>|null $facilities
 */
class UnitType extends Model
{
    protected $fillable = [
        'name', 'slug', 'description', 'capacity', 'max_extra_guests',
        'facilities', 'base_price_weekday', 'base_price_weekend',
    ];

    protected function casts(): array
    {
        return [
            'facilities' => 'array',
            'capacity' => 'integer',
            'max_extra_guests' => 'integer',
            'base_price_weekday' => 'integer',
            'base_price_weekend' => 'integer',
        ];
    }

    /** @return HasMany<Unit, $this> */
    public function units(): HasMany
    {
        return $this->hasMany(Unit::class);
    }

    /** @return HasMany<UnitTypePhoto, $this> */
    public function photos(): HasMany
    {
        return $this->hasMany(UnitTypePhoto::class);
    }

    /** @return HasMany<SpecialPrice, $this> */
    public function specialPrices(): HasMany
    {
        return $this->hasMany(SpecialPrice::class);
    }

    /**
     * Owners type facilities freely, so "grill" and "Grill" would show up as two catalog filters.
     *
     * @param  array<int, string>|null  $facilities
     * @return array<int, string>
     */
    public static function normalizeFacilities(?array $facilities): array
    {
        return collect($facilities ?? [])
            ->map(fn (string $facility): string => ucfirst(trim($facility)))
            ->filter()
            ->unique(fn (string $facility): string => mb_strtolower($facility))
            ->values()
            ->all();
    }
}
