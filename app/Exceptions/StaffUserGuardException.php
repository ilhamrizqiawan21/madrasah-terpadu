<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Perubahan akun staf yang ditolak karena akan mengunci admin keluar dari sistem.
 * Pesan siap ditampilkan ke pengguna.
 */
final class StaffUserGuardException extends RuntimeException
{
    public static function cannotDeactivateSelf(): self
    {
        return new self('Anda tidak dapat menonaktifkan akun Anda sendiri.');
    }

    public static function cannotRemoveOwnAdminRole(): self
    {
        return new self('Anda tidak dapat melepas peran Admin dari akun Anda sendiri.');
    }

    public static function cannotDeleteSelf(): self
    {
        return new self('Anda tidak dapat menghapus akun Anda sendiri.');
    }

    public static function lastActiveAdmin(): self
    {
        return new self('Akun ini adalah admin aktif terakhir. Tambahkan admin lain terlebih dahulu.');
    }
}
