<?php

namespace Tests\Feature;

use App\Enums\ReportType;
use App\Filament\Pages\Laporan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ReportFixtures;
use Tests\TestCase;

class ReportAccessTest extends TestCase
{
    use RefreshDatabase;
    use ReportFixtures;

    private const QUERY = ['periode' => 'bulan_ini'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-15 10:00:00');
    }

    public function test_only_the_owner_holds_the_report_permission(): void
    {
        $this->assertTrue($this->makeUser(User::ROLE_OWNER)->can('view_reports'));
        $this->assertFalse($this->makeUser(User::ROLE_FRONT_OFFICE)->can('view_reports'));
        $this->assertFalse($this->makeUser(User::ROLE_CASHIER)->can('view_reports'));
    }

    public function test_owner_reaches_the_page(): void
    {
        $this->actingAs($this->makeUser(User::ROLE_OWNER));

        $this->assertTrue(Laporan::canAccess());
        $this->get('/admin/laporan')->assertOk()->assertSee('Jenis laporan');
    }

    #[DataProvider('staffRoles')]
    public function test_staff_cannot_reach_the_page(string $role): void
    {
        $this->actingAs($this->makeUser($role));

        $this->assertFalse(Laporan::canAccess());
        $this->get('/admin/laporan')->assertForbidden();
    }

    /** @return array<string, array{string}> */
    public static function staffRoles(): array
    {
        return [
            'front office' => [User::ROLE_FRONT_OFFICE],
            'kasir' => [User::ROLE_CASHIER],
        ];
    }

    public function test_page_shows_the_report_and_switches_type_and_period(): void
    {
        $this->seedOctober();
        $this->actingAs($this->makeUser(User::ROLE_OWNER));

        Livewire::test(Laporan::class)
            ->assertSee('B-1')
            ->assertSee('Jumlah booking')
            ->set('data.type', ReportType::Occupancy->value)
            ->assertSee('Okupansi (%)')
            ->assertSee('malam terisi dibagi malam tersedia')
            ->set('data.preset', 'kustom')
            ->set('data.from', '2026-10-20')
            ->set('data.to', '2026-10-01')
            ->assertSee('Tanggal akhir tidak boleh sebelum tanggal awal.');
    }

    public function test_export_urls_are_forbidden_for_staff_and_redirect_guests(): void
    {
        foreach ([User::ROLE_FRONT_OFFICE, User::ROLE_CASHIER] as $role) {
            $this->actingAs($this->makeUser($role));

            foreach (['excel', 'pdf'] as $format) {
                $this->get(route("admin.reports.{$format}", ['type' => 'booking'] + self::QUERY))->assertForbidden();
            }
        }

        auth()->logout();
        $this->get(route('admin.reports.excel', ['type' => 'booking'] + self::QUERY))->assertRedirect();
    }

    public function test_invalid_input_is_rejected(): void
    {
        $this->actingAs($this->makeUser(User::ROLE_OWNER));

        $this->get(route('admin.reports.pdf', ['type' => 'booking', 'periode' => 'tahun_ini']))->assertSessionHasErrors('periode');
        $this->get(route('admin.reports.pdf', ['type' => 'booking', 'periode' => 'kustom', 'dari' => '2026-10-10', 'sampai' => '2026-10-01']))->assertStatus(422);
        $this->get('/admin/laporan/tidak-ada/pdf?periode=bulan_ini')->assertNotFound();
    }
}
