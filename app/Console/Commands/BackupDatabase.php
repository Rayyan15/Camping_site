<?php

namespace App\Console\Commands;

use App\Ops\BackupFailedException;
use App\Ops\BackupPruner;
use App\Ops\DatabaseBackup;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class BackupDatabase extends Command
{
    protected $signature = 'app:backup-database';

    protected $description = 'Dump the database to a gzip file, copy it off-server when BACKUP_DISK is set, and prune old backups.';

    public function handle(DatabaseBackup $backup, BackupPruner $pruner): int
    {
        $retentionDays = (int) config('backup.retention_days');
        $offsiteDisk = config('backup.disk');

        try {
            $path = $backup->create();
            $this->components->info('Backup dibuat: '.basename($path));

            if ($offsiteDisk) {
                $backup->copyOffsite($path, $offsiteDisk);
                $this->components->info("Salinan off-server tersimpan di disk '{$offsiteDisk}'.");
            }
        } catch (BackupFailedException $e) {
            Log::error('Database backup failed', ['error' => $e->getMessage()]);
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->pruneOldBackups($pruner, $retentionDays, $offsiteDisk);

        return self::SUCCESS;
    }

    private function pruneOldBackups(BackupPruner $pruner, int $retentionDays, ?string $offsiteDisk): void
    {
        $local = Storage::build(['driver' => 'local', 'root' => config('backup.directory')]);
        $removed = count($pruner->prune($local, '', now(), $retentionDays));

        if ($offsiteDisk) {
            $remote = Storage::disk($offsiteDisk);
            $removed += count($pruner->prune($remote, (string) config('backup.disk_directory'), now(), $retentionDays));
        }

        $this->components->info("Backup lebih lama dari {$retentionDays} hari dihapus: {$removed} berkas.");
    }
}
