<?php

namespace Tests\Feature;

use App\Models\MenuItem;
use App\Models\UnitType;
use App\Models\UnitTypePhoto;
use Database\Seeders\MenuSeeder;
use Database\Seeders\UnitTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SeededPhotosTest extends TestCase
{
    use RefreshDatabase;

    private const PHOTOS_PER_UNIT_TYPE = 3;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->seed([UnitTypeSeeder::class, MenuSeeder::class]);
    }

    public function test_every_unit_type_has_photos_with_existing_files(): void
    {
        $this->assertGreaterThan(0, UnitType::count());

        foreach (UnitType::with('photos')->get() as $unitType) {
            $this->assertCount(self::PHOTOS_PER_UNIT_TYPE, $unitType->photos, $unitType->name);

            foreach ($unitType->photos as $photo) {
                Storage::disk('public')->assertExists($photo->path);
            }
        }
    }

    public function test_every_menu_item_has_a_photo_file(): void
    {
        $this->assertGreaterThan(0, MenuItem::count());

        foreach (MenuItem::all() as $item) {
            $this->assertNotNull($item->photo, $item->name);
            Storage::disk('public')->assertExists($item->photo);
        }
    }

    public function test_seeding_twice_does_not_duplicate_rows_or_files(): void
    {
        $photoRows = UnitTypePhoto::count();
        $files = count(Storage::disk('public')->allFiles());

        $this->seed([UnitTypeSeeder::class, MenuSeeder::class]);

        $this->assertSame($photoRows, UnitTypePhoto::count());
        $this->assertSame($files, count(Storage::disk('public')->allFiles()));
    }

    public function test_unit_type_page_lists_every_photo_url(): void
    {
        $unitType = UnitType::with('photos')->where('slug', 'pancar')->firstOrFail();

        $response = $this->get(route('tenda.show', ['slug' => $unitType->slug]))->assertOk();

        foreach ($unitType->photos as $photo) {
            $response->assertSee($photo->url, false);
        }
    }
}
