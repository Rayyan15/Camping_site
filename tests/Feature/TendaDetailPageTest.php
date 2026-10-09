<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Unit;
use App\Models\UnitType;
use App\Models\UnitTypePhoto;
use App\Services\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TendaDetailPageTest extends TestCase
{
    use RefreshDatabase;

    private UnitType $unitType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-12-01 10:00:00');

        $this->unitType = UnitType::create([
            'name' => 'Dome', 'slug' => 'dome', 'capacity' => 2,
            'description' => 'Tenda dome dengan teras kayu.',
            'facilities' => ['Kasur queen', 'Teras kayu'],
            'base_price_weekday' => 350000, 'base_price_weekend' => 450000,
        ]);
        Unit::create(['unit_type_id' => $this->unitType->id, 'code' => 'D1', 'status' => 'active']);
        Unit::create(['unit_type_id' => $this->unitType->id, 'code' => 'D2', 'status' => 'active']);

        $other = UnitType::create([
            'name' => 'Kabin', 'slug' => 'kabin', 'capacity' => 4,
            'base_price_weekday' => 600000, 'base_price_weekend' => 750000,
        ]);
        Unit::create(['unit_type_id' => $other->id, 'code' => 'K1', 'status' => 'active']);

        foreach ([1, 2] as $order) {
            UnitTypePhoto::create(['unit_type_id' => $this->unitType->id, 'path' => "seed/dome-{$order}.jpg", 'sort_order' => $order]);
        }
    }

    public function test_key_elements_are_present(): void
    {
        $response = $this->get(route('tenda.show', ['slug' => 'dome']))->assertOk();

        $response->assertSee('<h1', false);
        $response->assertSeeText('Dome');
        $response->assertSeeText('Rp 350.000');
        $response->assertSeeText('Rp 450.000');
        $response->assertSeeText('Hingga 2 orang');
        $response->assertSeeText('2 unit');
        $response->assertSeeText('Kasur queen');
        $response->assertSeeText('Tenda dome dengan teras kayu.');
        $response->assertSee('data-avail-cal', false);
        $response->assertSee('data-endpoint="'.route('tenda.ketersediaan', ['slug' => 'dome']).'"', false);
        $response->assertSee('data-gallery', false);
    }

    public function test_availability_form_keeps_its_contract_with_the_booking_check(): void
    {
        $response = $this->get(route('tenda.show', ['slug' => 'dome']))->assertOk();

        $response->assertSee('action="'.route('booking.cek').'"', false);
        $response->assertSee('name="unit_type_id" value="'.$this->unitType->id.'"', false);
        $response->assertSee('name="check_in"', false);
        $response->assertSee('name="check_out"', false);
        $response->assertSee('name="guests"', false);
    }

    public function test_dates_and_guests_from_the_landing_page_prefill_the_form(): void
    {
        $response = $this->get(route('tenda.show', ['slug' => 'dome', 'check_in' => '2026-12-10', 'check_out' => '2026-12-12', 'guests' => 3]))->assertOk();

        $response->assertSee('value="2026-12-10"', false);
        $response->assertSee('value="2026-12-12"', false);
        $response->assertSee('value="3"', false);
    }

    public function test_the_form_falls_back_to_today_and_tomorrow_without_javascript(): void
    {
        $response = $this->get(route('tenda.show', ['slug' => 'dome']))->assertOk();

        $response->assertSee('value="2026-12-01"', false);
        $response->assertSee('value="2026-12-02"', false);
        $response->assertSee('<noscript>', false);
    }

    public function test_other_types_are_real_links_that_carry_the_chosen_stay(): void
    {
        $stay = ['check_in' => '2026-12-10', 'check_out' => '2026-12-12', 'guests' => 2];
        $response = $this->get(route('tenda.show', ['slug' => 'dome'] + $stay))->assertOk();

        $response->assertSee('href="'.e(route('tenda.show', ['slug' => 'kabin'] + $stay)).'"', false);
        $response->assertSeeText('Mulai Rp 600.000 per malam');
    }

    public function test_the_current_type_is_not_listed_among_the_others(): void
    {
        $html = $this->get(route('tenda.show', ['slug' => 'dome']))->getContent();
        $rail = substr($html, strpos($html, 'judul-tipe-lain'));

        $this->assertStringNotContainsString(route('tenda.show', ['slug' => 'dome']), $rail);
    }

    public function test_breadcrumb_and_back_link_point_to_the_landing_page(): void
    {
        $response = $this->get(route('tenda.show', ['slug' => 'dome']))->assertOk();

        $response->assertSee('aria-label="Jejak halaman"', false);
        $response->assertSee('href="'.route('home').'#tenda"', false);
        $response->assertSee('aria-current="page"', false);
    }

    public function test_every_in_page_anchor_has_a_target(): void
    {
        $html = $this->get(route('tenda.show', ['slug' => 'dome']))->getContent();

        preg_match_all('/href="#([^"]+)"/', $html, $links);
        foreach (array_unique($links[1]) as $target) {
            $this->assertMatchesRegularExpression('/id="'.preg_quote($target, '/').'"/', $html, "Dead anchor #{$target}");
        }
    }

    public function test_page_has_no_personal_data_or_booking_internals(): void
    {
        $customer = Customer::create(['name' => 'Budi Rahasia', 'phone' => '081299990000']);
        $booking = app(BookingService::class)->createBooking($customer->id, '2026-12-10', '2026-12-12', 2, [Unit::first()->id]);

        $html = $this->get(route('tenda.show', ['slug' => 'dome']))->getContent();

        $this->assertStringNotContainsString('Budi Rahasia', $html);
        $this->assertStringNotContainsString('081299990000', $html);
        $this->assertStringNotContainsString($booking->code, $html);
        $this->assertStringNotContainsString($booking->access_token, $html);
    }

    public function test_seo_meta_and_breadcrumb_structured_data_are_kept(): void
    {
        $response = $this->get(route('tenda.show', ['slug' => 'dome']))->assertOk();

        $response->assertSee('<title>Dome - ', false);
        $response->assertSee('property="og:image"', false);
        $response->assertSee('rel="canonical"', false);
        $response->assertSee('"@type":"BreadcrumbList"', false);
    }

    public function test_gallery_thumbnails_are_labelled_links_and_the_main_photo_has_alt_text(): void
    {
        $response = $this->get(route('tenda.show', ['slug' => 'dome']))->assertOk();

        $response->assertSee('aria-label="Lihat foto 1 dari 2"', false);
        $response->assertSee('aria-label="Lihat foto 2 dari 2"', false);
        $response->assertSee('alt="Dome tenda, foto 1"', false);
    }

    public function test_a_type_without_photos_or_description_still_renders(): void
    {
        $bare = UnitType::create(['name' => 'Polos', 'slug' => 'polos', 'capacity' => 2, 'base_price_weekday' => 100000, 'base_price_weekend' => 120000]);

        $this->get(route('tenda.show', ['slug' => $bare->slug]))
            ->assertOk()
            ->assertSeeText('Deskripsi tipe ini belum ditulis pengelola')
            ->assertSeeText('Daftar fasilitas tipe ini belum diisi pengelola');
    }

    public function test_unknown_slug_is_not_found(): void
    {
        $this->get(route('tenda.show', ['slug' => 'tidak-ada']))->assertNotFound();
    }
}
