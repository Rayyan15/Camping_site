<?php

namespace Tests\Feature;

use App\Filament\Resources\UnitTypes\Pages\EditUnitType;
use App\Filament\Resources\UnitTypes\RelationManagers\PhotosRelationManager;
use App\Models\UnitType;
use App\Models\UnitTypePhoto;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class UnitTypePhotoGalleryTest extends TestCase
{
    use RefreshDatabase;

    private UnitType $unitType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        Storage::fake('public');

        $this->unitType = UnitType::create([
            'name' => 'Dome Uji', 'slug' => 'dome-uji', 'description' => 'Tenda uji', 'capacity' => 2,
            'facilities' => [], 'base_price_weekday' => 300000, 'base_price_weekend' => 400000,
        ]);
    }

    private function loginAs(string $role): User
    {
        $user = User::create([
            'name' => $role, 'email' => $role.'@example.test', 'password' => 'secret-pass-123', 'is_active' => true,
        ]);
        $user->assignRole($role);
        $this->actingAs($user);

        return $user;
    }

    private function manager()
    {
        return Livewire::test(PhotosRelationManager::class, [
            'ownerRecord' => $this->unitType,
            'pageClass' => EditUnitType::class,
        ]);
    }

    private function addPhoto(int $order): UnitTypePhoto
    {
        $path = "unit-types/dome-uji/seed-{$order}.jpg";
        Storage::disk('public')->put($path, 'x');

        return $this->unitType->photos()->create(['path' => $path, 'sort_order' => $order]);
    }

    public function test_owner_uploads_photos_with_random_names_on_the_public_disk(): void
    {
        $this->loginAs(User::ROLE_OWNER);

        $this->manager()->callAction(TestAction::make('upload')->table(), [
            'paths' => [UploadedFile::fake()->image('tenda-asli.jpg'), UploadedFile::fake()->image('dalam.png')],
        ])->assertHasNoActionErrors();

        $photos = $this->unitType->photos()->orderBy('sort_order')->get();
        $this->assertCount(2, $photos);
        $this->assertSame([1, 2], $photos->pluck('sort_order')->all());

        foreach ($photos as $photo) {
            $this->assertStringStartsWith('unit-types/dome-uji/', $photo->path);
            $this->assertStringNotContainsString('tenda-asli', $photo->path);
            Storage::disk('public')->assertExists($photo->path);
        }
    }

    public function test_uploaded_photos_reach_the_public_tent_page(): void
    {
        $this->loginAs(User::ROLE_OWNER);
        $this->manager()->callAction(TestAction::make('upload')->table(), [
            'paths' => [UploadedFile::fake()->image('a.jpg')],
        ]);

        $photo = $this->unitType->photos()->firstOrFail();

        $this->get(route('tenda.show', ['slug' => 'dome-uji']))->assertOk()->assertSee($photo->url, false);
    }

    public function test_upload_rejects_files_that_are_not_images(): void
    {
        $this->loginAs(User::ROLE_OWNER);

        $this->manager()->callAction(TestAction::make('upload')->table(), [
            'paths' => [UploadedFile::fake()->create('virus.pdf', 10, 'application/pdf')],
        ])->assertHasActionErrors();

        $this->assertSame(0, $this->unitType->photos()->count());
    }

    public function test_upload_rejects_files_over_two_megabytes(): void
    {
        $this->loginAs(User::ROLE_OWNER);

        $this->manager()->callAction(TestAction::make('upload')->table(), [
            'paths' => [UploadedFile::fake()->image('besar.jpg')->size(2049)],
        ])->assertHasActionErrors();

        $this->assertSame(0, $this->unitType->photos()->count());
    }

    public function test_deleting_a_photo_removes_its_file(): void
    {
        $this->loginAs(User::ROLE_OWNER);
        $photo = $this->addPhoto(1);

        $this->manager()->callAction(TestAction::make('delete')->table($photo));

        $this->assertModelMissing($photo);
        Storage::disk('public')->assertMissing($photo->path);
    }

    public function test_deleting_a_model_directly_also_removes_the_file(): void
    {
        $photo = $this->addPhoto(1);

        $photo->delete();

        Storage::disk('public')->assertMissing($photo->path);
    }

    public function test_reordering_changes_the_order_seen_by_the_public_pages(): void
    {
        $this->loginAs(User::ROLE_OWNER);
        $first = $this->addPhoto(1);
        $second = $this->addPhoto(2);
        $third = $this->addPhoto(3);

        $this->manager()->call('reorderTable', [$third->getKey(), $first->getKey(), $second->getKey()]);

        $ordered = $this->unitType->fresh()->photos->sortBy('sort_order')->pluck('id')->all();
        $this->assertSame([$third->id, $first->id, $second->id], $ordered);
    }

    public function test_only_the_owner_manages_photos(): void
    {
        $this->loginAs(User::ROLE_FRONT_OFFICE);

        $this->assertFalse(PhotosRelationManager::canViewForRecord($this->unitType, EditUnitType::class));

        $this->loginAs(User::ROLE_CASHIER);
        $this->assertFalse(PhotosRelationManager::canViewForRecord($this->unitType, EditUnitType::class));

        $this->loginAs(User::ROLE_OWNER);
        $this->assertTrue(PhotosRelationManager::canViewForRecord($this->unitType, EditUnitType::class));
    }

    public function test_photo_belongs_to_its_unit_type(): void
    {
        $photo = $this->addPhoto(1);

        $this->assertTrue($photo->unitType->is($this->unitType));
    }
}
