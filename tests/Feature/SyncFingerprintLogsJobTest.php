<?php

namespace Tests\Feature;

use App\Jobs\SyncFingerprintLogs;
use App\Models\Attendance;
use App\Models\Employee;
use App\Services\AttendanceSyncService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class SyncFingerprintLogsJobTest extends TestCase
{
    use RefreshDatabase;

    private string $file;

    protected function setUp(): void
    {
        parent::setUp();
        $this->file = tempnam(sys_get_temp_dir(), 'fp');
    }

    protected function tearDown(): void
    {
        @unlink($this->file);
        parent::tearDown();
    }

    public function test_job_is_queued_and_does_nothing_without_a_configured_path(): void
    {
        config(['attendance.source_path' => null]);
        Log::spy();

        $this->assertInstanceOf(ShouldQueue::class, new SyncFingerprintLogs);
        (new SyncFingerprintLogs)->handle(app(AttendanceSyncService::class));

        Log::shouldHaveReceived('info')->once();
        $this->assertSame(0, Attendance::count());
    }

    public function test_job_imports_recent_punches_and_can_run_twice(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-11 20:00:00', 'Asia/Jakarta'));
        Employee::create(['name' => 'Rina', 'fingerprint_id' => '1']);
        file_put_contents($this->file, "1\t2026-10-10 08:00:00\t1\n1\t2026-10-10 17:00:00\t1\n1\t2026-08-01 08:00:00\t1\n");
        config(['attendance.source_path' => $this->file]);

        (new SyncFingerprintLogs)->handle(app(AttendanceSyncService::class));
        (new SyncFingerprintLogs)->handle(app(AttendanceSyncService::class));

        $this->assertSame(1, Attendance::count());
        $this->assertSame('17:00:00', Attendance::first()->clock_out);
    }
}
