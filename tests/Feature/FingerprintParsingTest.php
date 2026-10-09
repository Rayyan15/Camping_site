<?php

namespace Tests\Feature;

use App\Services\FileFingerprintLogSource;
use App\Support\FingerprintLogEntry;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class FingerprintParsingTest extends TestCase
{
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

    /**
     * @return array{0: array<int, FingerprintLogEntry>, 1: FileFingerprintLogSource}
     */
    private function read(string $contents): array
    {
        file_put_contents($this->file, $contents);
        $source = new FileFingerprintLogSource($this->file);
        $entries = iterator_to_array($source->entries(
            CarbonImmutable::parse('2026-10-01 00:00:00', 'Asia/Jakarta'),
            CarbonImmutable::parse('2026-10-31 23:59:59', 'Asia/Jakarta'),
        ), false);

        return [$entries, $source];
    }

    public function test_csv_with_header_is_parsed(): void
    {
        [$entries, $source] = $this->read("fingerprint_id,datetime\n101,2026-10-05 07:58:10\n102,2026-10-05 08:03:00\n");

        $this->assertCount(2, $entries);
        $this->assertSame('101', $entries[0]->fingerprintId);
        $this->assertSame('2026-10-05 07:58:10', $entries[0]->punchedAt->format('Y-m-d H:i:s'));
        $this->assertSame(0, $source->malformedLines());
    }

    public function test_zkteco_attlog_tab_separated_is_parsed(): void
    {
        [$entries, $source] = $this->read("101\t2026-10-05 07:58:10\t1\t1\t0\t0\n   7\t2026-10-05 17:02:00\t1\t1\t0\t0\n");

        $this->assertCount(2, $entries);
        $this->assertSame('7', $entries[1]->fingerprintId);
        $this->assertSame(0, $source->malformedLines());
    }

    public function test_malformed_lines_are_skipped_and_counted_without_crashing(): void
    {
        [$entries, $source] = $this->read("101,2026-10-05 07:58:10\nnot a log line\n103,not-a-date\n,2026-10-05 08:00:00\n\n104,2026-10-06 08:00:00\n");

        $this->assertCount(2, $entries);
        $this->assertSame(3, $source->malformedLines());
    }

    public function test_times_are_read_in_jakarta_and_filtered_by_range(): void
    {
        [$entries] = $this->read("101,2026-09-30 23:59:59\n101,2026-10-05 07:00:00\n101,2026-11-01 00:00:00\n");

        $this->assertCount(1, $entries);
        $this->assertSame('+07:00', $entries[0]->punchedAt->format('P'));
    }

    public function test_missing_file_raises_a_clear_error(): void
    {
        $this->expectException(\RuntimeException::class);

        iterator_to_array((new FileFingerprintLogSource('does-not-exist.csv'))->entries(now()->subDay(), now()));
    }
}
