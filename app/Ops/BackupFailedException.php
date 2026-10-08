<?php

namespace App\Ops;

use RuntimeException;

class BackupFailedException extends RuntimeException
{
    public static function unsupportedDriver(string $driver): self
    {
        return new self("Backup otomatis hanya mendukung MySQL atau MariaDB, driver aktif: {$driver}.");
    }

    public static function dumpFailed(string $reason): self
    {
        return new self("mysqldump gagal: {$reason}");
    }

    public static function unreadableDump(): self
    {
        return new self('Berkas hasil mysqldump tidak ditemukan atau kosong.');
    }

    public static function offsiteFailed(string $disk): self
    {
        return new self("Salinan ke disk off-server '{$disk}' gagal.");
    }
}
