<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Application roles. Values are stored in spatie/laravel-permission's
 * `roles.name` column, so renaming a case requires a data migration.
 * See docs/PRD.md §3 for what each role may do.
 */
enum Role: string
{
    case Admin = 'admin';
    case Treasurer = 'bendahara';
    case Teacher = 'guru';
    case Counselor = 'bk';
    case UnitHead = 'kepala_unit';
    case GateOfficer = 'petugas_gerbang';
    case CanteenCashier = 'kasir_kantin';
    case Guardian = 'wali';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Treasurer => 'Bendahara',
            self::Teacher => 'Guru',
            self::Counselor => 'Guru BK',
            self::UnitHead => 'Kepala Unit',
            self::GateOfficer => 'Petugas Gerbang',
            self::CanteenCashier => 'Kasir Kantin',
            self::Guardian => 'Wali Murid',
        };
    }

    /**
     * Roles that work inside the staff area. Guardians use the guardian portal.
     *
     * @return list<self>
     */
    public static function staff(): array
    {
        return [
            self::Admin,
            self::Treasurer,
            self::Teacher,
            self::Counselor,
            self::UnitHead,
            self::GateOfficer,
            self::CanteenCashier,
        ];
    }
}
