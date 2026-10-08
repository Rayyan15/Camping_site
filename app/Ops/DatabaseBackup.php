<?php

namespace App\Ops;

use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

class DatabaseBackup
{
    private const SUPPORTED_DRIVERS = ['mysql', 'mariadb'];

    private const COPY_CHUNK_BYTES = 1048576;

    private const OWNER_ONLY_PERMISSIONS = 0600;

    private const DIRECTORY_PERMISSIONS = 0750;

    /**
     * Dumps the default connection to a gzip file in the backup directory and returns its path.
     *
     * @throws BackupFailedException
     */
    public function create(): string
    {
        $connection = $this->connectionConfig();
        $directory = config('backup.directory');
        $this->ensureDirectory($directory);

        $target = $directory.DIRECTORY_SEPARATOR.$connection['database'].'-'.now()->format(BackupPruner::FILE_NAME_FORMAT).'.sql.gz';
        $optionsFile = $this->writeSecureTempFile($this->clientOptions($connection));
        $dumpFile = $this->writeSecureTempFile('');

        try {
            $this->dump($optionsFile, $dumpFile, $connection['database']);
            $this->compress($dumpFile, $target);
        } finally {
            @unlink($optionsFile);
            @unlink($dumpFile);
        }

        return $target;
    }

    /**
     * Copies a finished backup to the configured off-server disk.
     *
     * @throws BackupFailedException
     */
    public function copyOffsite(string $localPath, string $diskName): string
    {
        $remotePath = trim((string) config('backup.disk_directory'), '/').'/'.basename($localPath);
        $stream = fopen($localPath, 'rb');

        try {
            $stored = Storage::disk($diskName)->writeStream($remotePath, $stream);
        } finally {
            fclose($stream);
        }

        if ($stored === false) {
            throw BackupFailedException::offsiteFailed($diskName);
        }

        return $remotePath;
    }

    /**
     * @return array<string, mixed>
     */
    private function connectionConfig(): array
    {
        $connection = config('database.connections.'.config('database.default'));

        if (! in_array($connection['driver'] ?? null, self::SUPPORTED_DRIVERS, true)) {
            throw BackupFailedException::unsupportedDriver((string) ($connection['driver'] ?? 'unknown'));
        }

        return $connection;
    }

    /**
     * Credentials go through an option file so they never appear in the process list.
     *
     * @param  array<string, mixed>  $connection
     */
    private function clientOptions(array $connection): string
    {
        $lines = ['[client]'];
        $values = [
            'user' => $connection['username'] ?? '',
            'password' => $connection['password'] ?? '',
            'host' => $connection['host'] ?? '127.0.0.1',
            'port' => $connection['port'] ?? 3306,
        ];

        foreach ($values as $key => $value) {
            $lines[] = $key.'="'.addcslashes((string) $value, '\\"').'"';
        }

        return implode("\n", $lines)."\n";
    }

    private function dump(string $optionsFile, string $dumpFile, string $database): void
    {
        $result = Process::timeout(config('backup.timeout_seconds'))->run([
            config('backup.mysqldump_binary'),
            '--defaults-extra-file='.$optionsFile,
            '--single-transaction',
            '--quick',
            '--routines',
            '--triggers',
            '--no-tablespaces',
            '--result-file='.$dumpFile,
            $database,
        ]);

        if ($result->failed()) {
            throw BackupFailedException::dumpFailed(trim($result->errorOutput()) ?: 'exit code '.$result->exitCode());
        }

        if (! is_file($dumpFile) || filesize($dumpFile) === 0) {
            throw BackupFailedException::unreadableDump();
        }
    }

    private function compress(string $source, string $target): void
    {
        $input = fopen($source, 'rb');
        $output = gzopen($target, 'wb9');

        while (! feof($input)) {
            gzwrite($output, fread($input, self::COPY_CHUNK_BYTES));
        }

        fclose($input);
        gzclose($output);
        chmod($target, self::OWNER_ONLY_PERMISSIONS);
    }

    private function writeSecureTempFile(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'bkp');
        chmod($path, self::OWNER_ONLY_PERMISSIONS);
        file_put_contents($path, $contents);

        return $path;
    }

    private function ensureDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            mkdir($directory, self::DIRECTORY_PERMISSIONS, true);
        }
    }
}
