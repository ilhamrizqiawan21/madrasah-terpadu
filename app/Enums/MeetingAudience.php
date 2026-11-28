<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Sasaran peserta rapat (form rapat aplikasi lama).
 */
enum MeetingAudience: string
{
    case AllStaff = 'all_staff';
    case Teachers = 'teachers';
    case Homeroom = 'homeroom';
    case Administration = 'administration';
    case Musyrif = 'musyrif';

    public function label(): string
    {
        return match ($this) {
            self::AllStaff => 'Semua guru & staf',
            self::Teachers => 'Seluruh guru pengampu',
            self::Homeroom => 'Seluruh wali kelas',
            self::Administration => 'Staf tata usaha & operator',
            self::Musyrif => 'Pengurus asrama / musyrif',
        };
    }
}
