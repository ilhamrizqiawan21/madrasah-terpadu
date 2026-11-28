<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Zona waktu penyimpanan kolom tanggal (config app.timezone = WITA).
 *
 * Laravel menulis dan membandingkan DateTime tanpa konversi zona, jadi batas kueri dan nilai yang
 * disimpan wajib berada di zona ini. Jangan memakai ->utc() untuk kolom basis data.
 */
final class AppTimezone
{
    public static function name(): string
    {
        return (string) config('app.timezone');
    }
}
