<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Perubahan akun wali yang ditolak. Pesan siap ditampilkan ke pengguna.
 */
final class GuardianAccountException extends RuntimeException
{
    public static function phoneHeldByArchivedAccount(): self
    {
        return new self('Nomor HP ini milik akun yang sudah diarsipkan. Hubungi pengelola sistem untuk memulihkannya.');
    }

    public static function sharedStaffAccount(): self
    {
        return new self('Akun ini juga akun staf. Reset kata sandi atau nonaktifkan lewat menu Pengguna staf.');
    }
}
