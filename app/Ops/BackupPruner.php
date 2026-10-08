<?php

namespace App\Ops;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Filesystem\Filesystem;

/**
 * Decides which backup files are past retention. The age comes from the timestamp in the file name,
 * so the result is the same on local disks and on remote disks that do not keep modification times.
 */
class BackupPruner
{
    public const FILE_NAME_FORMAT = 'Ymd-His';

    private const FILE_NAME_PATTERN = '/-(\d{8}-\d{6})\.sql\.gz$/';

    /**
     * @param  array<int, string>  $paths
     * @return array<int, string>
     */
    public function expired(array $paths, CarbonInterface $now, int $retentionDays): array
    {
        $cutoff = CarbonImmutable::instance($now)->subDays($retentionDays);

        return array_values(array_filter($paths, function (string $path) use ($cutoff, $now): bool {
            $takenAt = $this->takenAt($path, $now->getTimezone()->getName());

            return $takenAt !== null && $takenAt->lt($cutoff);
        }));
    }

    /**
     * @return array<int, string> Paths that were deleted.
     */
    public function prune(Filesystem $disk, string $directory, CarbonInterface $now, int $retentionDays): array
    {
        $expired = $this->expired($disk->files($directory), $now, $retentionDays);

        foreach ($expired as $path) {
            $disk->delete($path);
        }

        return $expired;
    }

    private function takenAt(string $path, string $timezone): ?CarbonImmutable
    {
        if (preg_match(self::FILE_NAME_PATTERN, $path, $matches) !== 1) {
            return null;
        }

        return CarbonImmutable::createFromFormat(self::FILE_NAME_FORMAT, $matches[1], $timezone) ?: null;
    }
}
