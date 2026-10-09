<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Unit;
use App\Models\UnitType;
use App\Services\BookingService;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * Proves row locking across real database connections. It needs MySQL (InnoDB) because SQLite
 * ignores lockForUpdate, and it commits for real, so it cannot sit inside RefreshDatabase's
 * wrapping transaction.
 *
 * Run with: php artisan test --configuration=phpunit.mysql.xml --filter=BookingConcurrencyMysqlTest
 */
class BookingConcurrencyMysqlTest extends TestCase
{
    use DatabaseTruncation;

    private const CHILD_TIMEOUT_SECONDS = 60;

    private const LOCK_WAIT_POLL_SECONDS = 15;

    private string $childScript = '';

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('Needs MySQL: SQLite does not enforce row locks across connections.');
        }
    }

    protected function tearDown(): void
    {
        if ($this->childScript !== '' && is_file($this->childScript)) {
            unlink($this->childScript);
        }

        if (DB::connection()->getDriverName() === 'mysql') {
            $this->wipeCommittedData();
        }

        parent::tearDown();
    }

    public function test_second_process_gets_unavailable_when_the_only_unit_is_taken(): void
    {
        $type = $this->makeType(units: 1);

        $result = $this->raceChildAgainstOpenCheckout($type);

        $this->assertSame('UNAVAILABLE', $result);
        $this->assertSame(1, Booking::count());
        $this->assertSame(1, DB::table('booking_units')->count());
    }

    public function test_race_on_a_type_with_spare_units_never_double_books(): void
    {
        $type = $this->makeType(units: 2);

        $result = $this->raceChildAgainstOpenCheckout($type);

        // BOOKED (other unit) is the ideal outcome. UNAVAILABLE is tolerated: under REPEATABLE READ the
        // loser's availability subquery reads a snapshot taken before the winner committed, so it can
        // lock the taken unit and then be rejected even though a spare remains. No double booking either way.
        $this->assertContains($result, ['BOOKED', 'UNAVAILABLE']);
        $unitIds = DB::table('booking_units')->pluck('unit_id');
        $this->assertSame($unitIds->unique()->count(), $unitIds->count());
        $this->assertSame($result === 'BOOKED' ? 2 : 1, Booking::count());
    }

    /**
     * Limit: the parent holds its transaction open while the child checkout blocks on the unit
     * lock, so the order is forced (parent first) and the test never depends on scheduling luck.
     * It proves isolation between two connections, not behaviour under heavy parallel load.
     */
    private function raceChildAgainstOpenCheckout(UnitType $type): string
    {
        $payload = $this->checkoutPayload($type);
        $child = null;

        DB::beginTransaction();

        try {
            app(BookingService::class)->createFromCheckout($payload);

            $child = $this->startChildCheckout($payload);
            $this->waitUntilChildIsBlocked($child);
        } finally {
            DB::commit();
        }

        $child->wait();
        $this->assertNotSame(1, $child->getExitCode(), 'Child crashed: '.$child->getErrorOutput().$child->getOutput());

        return trim($child->getOutput());
    }

    /**
     * @return array<string, mixed>
     */
    private function checkoutPayload(UnitType $type): array
    {
        return [
            'customer_name' => 'Budi',
            'customer_email' => 'budi@example.com',
            'customer_phone' => '0812',
            'check_in' => now()->addDays(10)->toDateString(),
            'check_out' => now()->addDays(12)->toDateString(),
            'guests' => 2,
            'unit_type_id' => $type->id,
            'quantity' => 1,
        ];
    }

    private function makeType(int $units): UnitType
    {
        $type = UnitType::create([
            'name' => 'Dome', 'slug' => 'dome', 'capacity' => 4,
            'base_price_weekday' => 500000, 'base_price_weekend' => 700000,
        ]);

        for ($i = 1; $i <= $units; $i++) {
            Unit::create(['unit_type_id' => $type->id, 'code' => sprintf('D-%02d', $i), 'status' => Unit::STATUS_ACTIVE]);
        }

        return $type;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function startChildCheckout(array $payload): Process
    {
        $this->childScript = tempnam(sys_get_temp_dir(), 'checkout_child_').'.php';
        file_put_contents($this->childScript, $this->childScriptSource());

        $connection = config('database.connections.mysql');

        $process = new Process(
            [PHP_BINARY, $this->childScript, base_path(), json_encode($payload, JSON_THROW_ON_ERROR)],
            base_path(),
            [
                'APP_ENV' => 'testing',
                'DB_CONNECTION' => 'mysql',
                'DB_HOST' => $connection['host'],
                'DB_PORT' => $connection['port'],
                'DB_DATABASE' => $connection['database'],
                'DB_USERNAME' => $connection['username'],
                'DB_PASSWORD' => $connection['password'],
                'DB_URL' => '',
                'CACHE_STORE' => 'array',
                'QUEUE_CONNECTION' => 'sync',
                'SESSION_DRIVER' => 'array',
            ],
        );
        $process->setTimeout(self::CHILD_TIMEOUT_SECONDS);
        $process->start();

        return $process;
    }

    private function waitUntilChildIsBlocked(Process $child): void
    {
        $deadline = microtime(true) + self::LOCK_WAIT_POLL_SECONDS;

        while ($child->isRunning() && microtime(true) < $deadline) {
            $waiting = DB::selectOne("select count(*) as total from information_schema.innodb_trx where trx_state = 'LOCK WAIT'");

            if ((int) $waiting->total > 0) {
                return;
            }

            usleep(100_000);
        }
    }

    private function childScriptSource(): string
    {
        return <<<'PHP'
<?php

use App\Exceptions\UnitUnavailableException;
use App\Services\BookingService;
use Illuminate\Contracts\Console\Kernel;

require $argv[1].'/vendor/autoload.php';

$app = require $argv[1].'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

try {
    app(BookingService::class)->createFromCheckout(json_decode($argv[2], true, 512, JSON_THROW_ON_ERROR));
    echo 'BOOKED';
} catch (UnitUnavailableException) {
    echo 'UNAVAILABLE';
}
PHP;
    }

    private function wipeCommittedData(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        foreach (Schema::getTableListing() as $table) {
            if ($table !== 'migrations') {
                DB::table($table)->truncate();
            }
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
}
