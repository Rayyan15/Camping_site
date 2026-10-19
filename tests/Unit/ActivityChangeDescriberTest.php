<?php

namespace Tests\Unit;

use App\Filament\Resources\ActivityLogs\ActivityChangeDescriber;
use App\Models\ActivityLog;
use Tests\TestCase;

class ActivityChangeDescriberTest extends TestCase
{
    public function test_a_booking_change_reads_as_plain_indonesian(): void
    {
        $log = new ActivityLog(['subject_type' => 'booking', 'subject_id' => 49, 'changes' => [
            'old' => ['status' => 'pending_payment', 'paid_amount' => 0],
            'new' => ['status' => 'paid', 'paid_amount' => 350000, 'access_token' => '[disembunyikan]', 'customer_id' => 21],
        ]]);

        $text = ActivityChangeDescriber::describe($log);

        $this->assertStringContainsString('Status diubah dari', $text);
        $this->assertStringContainsString('Sudah dibayar diubah dari Rp 0 menjadi Rp 350.000', $text);
        $this->assertStringNotContainsString('access_token', $text);
        $this->assertStringNotContainsString('customer_id', $text);
        $this->assertSame('Booking #49', ActivityChangeDescriber::subject($log));
    }
}
