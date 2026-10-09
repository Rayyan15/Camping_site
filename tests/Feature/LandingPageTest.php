<?php

namespace Tests\Feature;

use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\RefundPolicy;
use App\Models\UnitType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingPageTest extends TestCase
{
    use RefreshDatabase;

    private function seedCatalog(): void
    {
        UnitType::create([
            'name' => 'Dome', 'slug' => 'dome', 'capacity' => 4,
            'base_price_weekday' => 650000, 'base_price_weekend' => 800000,
        ]);
        $category = MenuCategory::create(['name' => 'Minuman', 'sort_order' => 1]);
        MenuItem::create(['category_id' => $category->id, 'name' => 'Kopi Tubruk', 'price' => 12000, 'is_available' => true, 'sort_order' => 1]);
        MenuItem::create(['category_id' => $category->id, 'name' => 'Teh Habis', 'price' => 8000, 'is_available' => false, 'sort_order' => 2]);
    }

    public function test_page_links_staff_to_the_admin_login(): void
    {
        $this->get(route('home'))->assertOk()->assertSee(route('filament.admin.auth.login'), false);
    }

    public function test_every_in_page_anchor_has_a_target(): void
    {
        $html = $this->get(route('home'))->getContent();

        preg_match_all('/href="[^"#]*#([a-z0-9-]+)"/', $html, $anchors);

        foreach (array_unique($anchors[1]) as $id) {
            $this->assertStringContainsString('id="'.$id.'"', $html, "Anchor #{$id} points at nothing");
        }
    }

    public function test_the_four_phases_are_present(): void
    {
        $html = $this->get(route('home'))->getContent();

        foreach (['sore', 'petang', 'malam', 'subuh'] as $phase) {
            $this->assertStringContainsString('data-phase="'.$phase.'"', $html);
        }
    }

    public function test_menu_shows_only_available_items_with_real_prices(): void
    {
        $this->seedCatalog();

        $this->get(route('home'))
            ->assertSee('Kopi Tubruk')
            ->assertSee('Rp 12.000')
            ->assertDontSee('Teh Habis');
    }

    public function test_refund_rule_is_read_from_the_policy_table(): void
    {
        RefundPolicy::create(['min_days_before' => 10, 'percent' => 80]);
        RefundPolicy::create(['min_days_before' => 0, 'percent' => 0]);

        $this->get(route('home'))
            ->assertSee('10 hari atau lebih sebelum check-in: dikembalikan 80%.')
            ->assertSee('Kurang dari 10 hari sebelum check-in: tidak ada pengembalian.');
    }

    public function test_page_makes_no_promise_about_check_in_hours(): void
    {
        $this->get(route('home'))->assertSee('Jam check-in dan check-out ditentukan pengelola');
    }

    public function test_contact_block_is_hidden_until_configured(): void
    {
        config(['site.address' => null, 'site.maps_url' => null, 'site.whatsapp_number' => null, 'site.instagram_url' => null]);

        $this->get(route('home'))->assertDontSee('Buka di peta')->assertDontSee('Kirim pesan');
    }

    public function test_headline_and_search_form_render_without_javascript(): void
    {
        $this->get(route('home'))
            ->assertSee('Satu')
            ->assertSee('kanvas.')
            ->assertSee('id="cari"', false);
    }
}
