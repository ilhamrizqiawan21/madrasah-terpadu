<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Tipe penugasan pegawai (form pegawai aplikasi lama).
 */
enum StaffAssignmentType: string
{
    case Teacher = 'guru';
    case Staff = 'staff';
    case UnitAdmin = 'admin_unit';
    case Musyrif = 'musyrif';
    case Security = 'keamanan';
    case Cleaning = 'kebersihan';

    public function label(): string
    {
        return match ($this) {
            self::Teacher => 'Guru / Tenaga Pendidik',
            self::Staff => 'Staf / Tata Usaha',
            self::UnitAdmin => 'Admin Unit',
            self::Musyrif => 'Musyrif / Pembina Asrama',
            self::Security => 'Keamanan',
            self::Cleaning => 'Kebersihan',
        };
    }
}
