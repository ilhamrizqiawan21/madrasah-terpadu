<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Status ikatan kerja pegawai (form pegawai aplikasi lama).
 */
enum EmploymentStatus: string
{
    case Gty = 'gty';
    case Gtt = 'gtt';
    case Pns = 'pns';
    case Pppk = 'pppk';
    case Pty = 'pty';
    case Ptt = 'ptt';

    public function label(): string
    {
        return match ($this) {
            self::Gty => 'GTY (Guru Tetap Yayasan)',
            self::Gtt => 'GTT (Guru Honorer)',
            self::Pns => 'PNS',
            self::Pppk => 'PPPK',
            self::Pty => 'PTY (Staf Tetap Yayasan)',
            self::Ptt => 'PTT (Staf Honorer)',
        };
    }

    public function shortLabel(): string
    {
        return strtoupper($this->value);
    }
}
