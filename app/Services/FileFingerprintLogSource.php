<?php

namespace App\Services;

use App\Contracts\FingerprintLogSource;
use App\Support\FingerprintLogEntry;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Reads a log file from private storage. Two layouts are accepted per line:
 * CSV "fingerprint_id,datetime" (an optional header line is skipped) and the tab separated
 * ZKTeco attlog.dat "user id <tab> datetime <tab> ...". Unreadable lines are counted, never fatal.
 */
class FileFingerprintLogSource implements FingerprintLogSource
{
    private const DATETIME_PATTERN = '/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}(:\d{2})?$/';

    private int $malformedLines = 0;

    public function __construct(
        private readonly string $path,
        private readonly string $disk = 'local',
    ) {}

    public function malformedLines(): int
    {
        return $this->malformedLines;
    }

    public function entries(CarbonInterface $from, CarbonInterface $to): iterable
    {
        $this->malformedLines = 0;

        $stream = $this->openStream();

        try {
            $lineNumber = 0;
            while (($line = fgets($stream)) !== false) {
                $lineNumber++;
                $entry = $this->parseLine($line, $lineNumber === 1);

                if ($entry === null) {
                    continue;
                }
                if ($entry->punchedAt->lt($from) || $entry->punchedAt->gt($to)) {
                    continue;
                }

                yield $entry;
            }
        } finally {
            fclose($stream);
        }
    }

    /** @return resource */
    private function openStream()
    {
        $stream = is_file($this->path)
            ? fopen($this->path, 'rb')
            : $this->openFromDisk();

        if (! is_resource($stream)) {
            throw new \RuntimeException("Fingerprint log file not found: {$this->path}");
        }

        return $stream;
    }

    /** @return resource|false */
    private function openFromDisk()
    {
        $disk = Storage::disk($this->disk);

        return $disk->exists($this->path) ? $disk->readStream($this->path) : false;
    }

    private function parseLine(string $line, bool $isFirstLine): ?FingerprintLogEntry
    {
        $line = trim(preg_replace('/^\xEF\xBB\xBF/', '', $line));

        if ($line === '') {
            return null;
        }

        $fields = str_contains($line, "\t") ? preg_split('/\t+/', $line) : str_getcsv($line);
        $fingerprintId = trim((string) ($fields[0] ?? ''));
        $rawTime = trim((string) ($fields[1] ?? ''));

        if ($fingerprintId === '' || ! preg_match(self::DATETIME_PATTERN, $rawTime)) {
            return $this->rejectLine($isFirstLine);
        }

        try {
            $punchedAt = CarbonImmutable::parse($rawTime, config('app.timezone'));
        } catch (Throwable) {
            return $this->rejectLine($isFirstLine);
        }

        return new FingerprintLogEntry($fingerprintId, $punchedAt);
    }

    /** A header such as "fingerprint_id,datetime" on line one is expected, not damage. */
    private function rejectLine(bool $isFirstLine): null
    {
        if (! $isFirstLine) {
            $this->malformedLines++;
        }

        return null;
    }
}
