<?php

namespace App\Exceptions;

use RuntimeException;

class ReferencedSetupRecordException extends RuntimeException
{
    public static function shift(): self
    {
        return new self('Shift ini masih dipakai karyawan sehingga tidak bisa dihapus. Pindahkan karyawannya ke shift lain dulu.');
    }

    public static function criteria(): self
    {
        return new self('Kriteria ini sudah punya skor penilaian sehingga tidak bisa dihapus. Nonaktifkan saja.');
    }
}
