<?php

namespace Database\Seeders;

use App\Models\Unit;
use App\Models\UnitType;
use App\Models\UnitTypePhoto;
use Database\Seeders\Support\SeedImage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class UnitTypeSeeder extends Seeder
{
    /**
     * Unit counts come from the PRD (section 3.1, 32 units in total).
     * Facilities and every price below are PLACEHOLDERS: the owner must confirm them.
     *
     * @var array<int, array{name: string, code: string, units: int, capacity: int, max_extra_guests?: int, weekday: int, weekend: int, description: string, facilities: array<int, string>}>
     */
    /** Capacities from the owner, 29 Oct 2026. Only Dome, Salak and Pancar take up to 2 extra guests. */
    private const TENT_CAPACITY = 4;

    private const EXTRA_GUESTS_ALLOWED = 2;

    private const TYPES = [
        ['name' => 'Dome', 'code' => 'DOM', 'max_extra_guests' => self::EXTRA_GUESTS_ALLOWED, 'units' => 1, 'capacity' => self::TENT_CAPACITY, 'weekday' => 650000, 'weekend' => 800000,
            'description' => 'Satu-satunya tenda berbentuk kubah di area ini, dengan ruang tengah yang lega untuk rombongan kecil.',
            'facilities' => ['Kasur', 'Listrik', 'Kamar mandi dalam', 'Teras']],
        ['name' => 'Romance', 'code' => 'ROM', 'units' => 2, 'capacity' => self::TENT_CAPACITY, 'weekday' => 550000, 'weekend' => 700000,
            'description' => 'Tenda untuk berdua dengan tata ruang yang lebih privat dan tenang.',
            'facilities' => ['Kasur', 'Listrik', 'Kamar mandi dalam']],
        ['name' => 'Snail', 'code' => 'SNL', 'units' => 2, 'capacity' => self::TENT_CAPACITY, 'weekday' => 450000, 'weekend' => 575000,
            'description' => 'Tenda dengan bentuk melengkung seperti rumah siput, cocok untuk keluarga kecil.',
            'facilities' => ['Kasur', 'Listrik', 'Teras']],
        ['name' => 'Indian', 'code' => 'IND', 'units' => 5, 'capacity' => self::TENT_CAPACITY, 'weekday' => 400000, 'weekend' => 500000,
            'description' => 'Tenda tipi berpuncak tinggi dengan ruang berdiri yang leluasa.',
            'facilities' => ['Kasur', 'Listrik', 'Teras']],
        ['name' => 'Pancar', 'code' => 'PNC', 'max_extra_guests' => self::EXTRA_GUESTS_ALLOWED, 'units' => 11, 'capacity' => self::TENT_CAPACITY, 'weekday' => 350000, 'weekend' => 450000,
            'description' => 'Tenda keluarga standar dan tipe paling banyak di area ini.',
            'facilities' => ['Kasur', 'Listrik', 'Toilet bersama']],
        ['name' => 'Safari', 'code' => 'SFR', 'units' => 6, 'capacity' => self::TENT_CAPACITY, 'weekday' => 500000, 'weekend' => 625000,
            'description' => 'Tenda kanvas besar ala safari dengan ruang untuk rombongan keluarga.',
            'facilities' => ['Kasur', 'Listrik', 'Kamar mandi dalam', 'Teras']],
        ['name' => 'Salak', 'code' => 'SLK', 'max_extra_guests' => self::EXTRA_GUESTS_ALLOWED, 'units' => 5, 'capacity' => 2, 'weekday' => 600000, 'weekend' => 750000,
            'description' => 'Tenda untuk dua orang, bisa ditambah dua tamu lewat extra bed.',
            'facilities' => ['Kasur', 'Listrik', 'Kamar mandi dalam', 'Teras']],
    ];

    /**
     * Bundled photos per type slug, cover first. Indian, Pancar and Safari use the owner's own photos (file names under database/seeders/images).
     *
     * @var array<string, array<int, string>>
     */
    private const PHOTOS = [
        'dome' => ['tents/exterior-dome-deck', 'tents/interior-dome-bed', 'scenes/forest-hammock'],
        'romance' => ['tents/exterior-canvas-night', 'tents/interior-canvas-bed', 'scenes/campfire-sunset-view'],
        'snail' => ['tents/exterior-lit-peak-tent', 'tents/interior-yurt-door', 'scenes/mountain-lake'],
        'indian' => ['tents/real/indian-1', 'tents/real/indian-2', 'tents/real/indian-3'],
        'pancar' => ['tents/real/pancar-1', 'tents/real/pancar-2', 'tents/real/pancar-3', 'tents/real/pancar-4'],
        'safari' => ['tents/real/safari-1', 'tents/real/safari-2', 'tents/real/safari-3', 'tents/real/safari-4'],
        'salak' => ['tents/exterior-dome-forest', 'tents/interior-lodge-bed', 'scenes/forest-hammock'],
    ];

    public function run(): void
    {
        foreach (self::TYPES as $type) {
            $unitType = UnitType::updateOrCreate(
                ['slug' => Str::slug($type['name'])],
                [
                    'name' => $type['name'],
                    'description' => $type['description'],
                    'capacity' => $type['capacity'],
                    'max_extra_guests' => $type['max_extra_guests'] ?? 0,
                    'facilities' => $type['facilities'],
                    'base_price_weekday' => $type['weekday'],
                    'base_price_weekend' => $type['weekend'],
                ],
            );

            $this->seedPhotos($unitType);

            foreach (range(1, $type['units']) as $number) {
                Unit::firstOrCreate(
                    ['code' => sprintf('%s-%02d', $type['code'], $number)],
                    ['unit_type_id' => $unitType->id, 'status' => Unit::STATUS_ACTIVE],
                );
            }
        }

        $this->call(AddonSeeder::class);
    }

    private function seedPhotos(UnitType $unitType): void
    {
        foreach (self::PHOTOS[$unitType->slug] ?? [] as $index => $image) {
            $sortOrder = $index + 1;
            $path = SeedImage::publish("{$image}.webp", "unit-types/{$unitType->slug}/{$sortOrder}-".basename($image).'.webp');

            UnitTypePhoto::updateOrCreate(
                ['unit_type_id' => $unitType->id, 'sort_order' => $sortOrder],
                ['path' => $path],
            );
        }
    }
}
