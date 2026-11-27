<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

final class AcademicYearGuardException extends RuntimeException
{
    public static function cannotDeleteActive(): self
    {
        return new self('Tahun ajaran yang sedang aktif tidak dapat dihapus. Aktifkan tahun ajaran lain lebih dulu.');
    }

    public static function usedByClassrooms(): self
    {
        return new self('Tahun ajaran ini sudah dipakai rombel sehingga tidak dapat dihapus.');
    }
}
