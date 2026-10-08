<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingUnit;
use App\Models\Customer;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Unit;
use App\Models\UnitType;
use App\Services\InvoiceService;
use App\Services\WhatsAppShareLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeBooking(string $code, string $status): Booking
    {
        $type = UnitType::firstOrCreate(['slug' => 'dome'], [
            'name' => 'Dome', 'description' => 'Dome', 'capacity' => 4,
            'facilities' => [], 'base_price_weekday' => 500000, 'base_price_weekend' => 600000,
        ]);
        $unit = Unit::create(['unit_type_id' => $type->id, 'code' => 'D-'.$code, 'status' => 'active']);
        $customer = Customer::create(['name' => 'Budi Santoso', 'phone' => '0812-3456-7890']);

        $booking = Booking::create([
            'code' => $code, 'customer_id' => $customer->id,
            'check_in' => '2026-11-01', 'check_out' => '2026-11-03', 'guests' => 2,
            'status' => $status, 'subtotal' => 1000000, 'tax' => 110000,
            'total' => 1110000, 'paid_amount' => 1110000,
        ]);
        BookingUnit::create([
            'booking_id' => $booking->id, 'unit_id' => $unit->id,
            'check_in' => '2026-11-01', 'check_out' => '2026-11-03',
            'price_per_night' => 500000, 'nights' => 2, 'subtotal' => 1000000,
        ]);

        return $booking;
    }

    public function test_preorder_food_is_not_added_on_top_of_booking_total(): void
    {
        $booking = $this->makeBooking('BK-FOOD', 'paid');
        $category = MenuCategory::create(['name' => 'Minuman', 'sort_order' => 1]);
        $coffee = MenuItem::create(['category_id' => $category->id, 'name' => 'Kopi', 'price' => 12000, 'is_available' => true]);
        $booking->orders()->create([
            'code' => 'ORD-1', 'source' => Order::SOURCE_PREORDER, 'status' => 'baru',
            'total' => 24000, 'payment_status' => 'unpaid', 'bill_to_booking' => true,
        ])->items()->create(['menu_item_id' => $coffee->id, 'qty' => 2, 'price' => 12000]);

        $data = (new InvoiceService)->invoiceData($booking);

        $this->assertCount(1, $data['foodLines']);
        $this->assertSame($booking->total, $data['grandTotal']);
        $this->assertSame($booking->subtotal, $data['subtotal']);
        $this->assertTrue($data['isSettled']);
    }

    public function test_invoice_number_is_assigned_once_and_unique(): void
    {
        $service = new InvoiceService;
        $first = $this->makeBooking('BK-1', 'paid');
        $second = $this->makeBooking('BK-2', 'paid');

        $number = $service->ensureInvoiceNumber($first);

        $this->assertMatchesRegularExpression('/^INV-\d{6}-0001$/', $number);
        $this->assertSame($number, $service->ensureInvoiceNumber($first->fresh()));
        $this->assertNotSame($number, $service->ensureInvoiceNumber($second));
        $this->assertStringEndsWith('-0002', $second->fresh()->invoice_number);
    }

    public function test_paid_booking_downloads_pdf(): void
    {
        $this->makeBooking('BK-PAID', 'paid');

        $response = $this->get(route('booking.invoice', 'BK-PAID'));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('invoice-BK-PAID.pdf', $response->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_rendered_pdf_is_not_empty(): void
    {
        $booking = $this->makeBooking('BK-FILE', 'checked_out');
        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'invoice-test.pdf';

        file_put_contents($path, (new InvoiceService)->render($booking)->output());

        $this->assertGreaterThan(1000, filesize($path));
        unlink($path);
    }

    public function test_pending_booking_is_forbidden(): void
    {
        $this->makeBooking('BK-PEND', 'pending_payment');

        $this->get(route('booking.invoice', 'BK-PEND'))->assertForbidden();
    }

    public function test_unknown_code_is_not_found(): void
    {
        $this->get(route('booking.invoice', 'NOPE'))->assertNotFound();
    }

    public function test_whatsapp_links_carry_booking_details(): void
    {
        $booking = $this->makeBooking('BK-WA', 'paid');
        $links = new WhatsAppShareLink;

        $share = $links->forBooking($booking);
        $this->assertStringStartsWith('https://wa.me/?text=', $share);
        $text = rawurldecode(substr($share, strlen('https://wa.me/?text=')));
        $this->assertStringContainsString('BK-WA', $text);
        $this->assertStringContainsString('1 x Dome', $text);
        $this->assertStringContainsString('Rp 1.110.000', $text);
        $this->assertStringContainsString(route('booking.status', 'BK-WA'), $text);

        $this->assertStringStartsWith('https://wa.me/6281234567890?text=', $links->forCustomer($booking));
    }

    public function test_customer_link_is_null_without_phone(): void
    {
        $booking = $this->makeBooking('BK-NP', 'paid');
        $booking->customer->update(['phone' => '']);

        $this->assertNull((new WhatsAppShareLink)->forCustomer($booking->fresh()));
    }
}
