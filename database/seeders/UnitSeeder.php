<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Unit;
use App\Support\AttendanceHours;
use Illuminate\Database\Seeder;

/**
 * Unit dasar yayasan (PRD §4.1). Idempoten: unit yang sudah ada tidak diubah,
 * sehingga jam presensi yang sudah diatur admin tidak tertimpa saat seeder diulang.
 */
class UnitSeeder extends Seeder
{
    public function run(): void
    {
        $units = [
            'MI' => 'Madrasah Ibtidaiyah',
            'RA' => 'Raudhatul Athfal',
            'TPQ' => "Taman Pendidikan Al-Qur'an",
        ];

        foreach ($units as $code => $name) {
            Unit::query()->firstOrCreate(['code' => $code], [
                'name' => $name,
                'attendance_settings' => AttendanceHours::defaults(),
                'is_active' => true,
            ]);
        }
    }
}
