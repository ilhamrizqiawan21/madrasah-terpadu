<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Gender;
use App\Enums\GuardianRelation;
use App\Enums\Role as RoleEnum;
use App\Enums\Semester;
use App\Enums\StudentStatus;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\Guardian;
use App\Models\Student;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Data uji lengkap untuk lingkungan lokal dan staging (T-210).
 * Dilarang berjalan di lingkungan produksi.
 */
class DemoSeeder extends Seeder
{
    private const DEFAULT_PASSWORD = 'password-uji-lokal';

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('DemoSeeder dilarang berjalan di lingkungan produksi.');
        }

        $this->call(ProductionSeeder::class);

        $staffAccounts = [
            ['username' => 'admin.uji', 'name' => 'Admin Uji', 'phone' => '081100000001', 'role' => RoleEnum::Admin],
            ['username' => 'bendahara.uji', 'name' => 'Bendahara Uji', 'phone' => '081100000002', 'role' => RoleEnum::Treasurer],
            ['username' => 'guru.uji', 'name' => 'Guru Uji', 'phone' => '081100000003', 'role' => RoleEnum::Teacher],
            ['username' => 'bk.uji', 'name' => 'BK Uji', 'phone' => '081100000004', 'role' => RoleEnum::Counselor],
            ['username' => 'gerbang.uji', 'name' => 'Petugas Gerbang Uji', 'phone' => '081100000005', 'role' => RoleEnum::GateOfficer],
            ['username' => 'kantin.uji', 'name' => 'Kasir Kantin Uji', 'phone' => '081100000006', 'role' => RoleEnum::CanteenCashier],
            ['username' => 'kepala.uji', 'name' => 'Kepala Unit Uji', 'phone' => '081100000007', 'role' => RoleEnum::UnitHead],
        ];

        $teacher = null;

        foreach ($staffAccounts as $account) {
            $user = User::query()->firstOrCreate(
                ['username' => $account['username']],
                [
                    'name' => $account['name'],
                    'phone' => $account['phone'],
                    'password' => self::DEFAULT_PASSWORD,
                    'is_active' => true,
                ],
            );

            if (! $user->hasRole($account['role']->value)) {
                $user->assignRole($account['role']->value);
            }

            if ($account['role'] === RoleEnum::Teacher) {
                $teacher = $user;
            }
        }

        $activeYear = AcademicYear::query()->where('is_active', true)->first();

        if ($activeYear === null) {
            $activeYear = AcademicYear::query()->firstOrCreate(
                ['name' => '2026/2027', 'semester' => Semester::Ganjil],
                [
                    'starts_on' => '2026-07-13',
                    'ends_on' => '2026-12-19',
                    'is_active' => true,
                ],
            );
        }

        $unitConfigs = [
            ['code' => 'MI', 'grade' => '1', 'classroom' => '1-A', 'nis_prefix' => '2601'],
            ['code' => 'RA', 'grade' => 'A', 'classroom' => 'RA-A', 'nis_prefix' => '2602'],
            ['code' => 'TPQ', 'grade' => 'Jilid 1', 'classroom' => 'TPQ-1', 'nis_prefix' => '2603'],
        ];

        $studentCounter = 1;
        $guardianCounter = 1;

        foreach ($unitConfigs as $config) {
            $unit = Unit::query()->where('code', $config['code'])->first();

            if ($unit === null) {
                continue;
            }

            $classroom = Classroom::query()->firstOrCreate(
                [
                    'unit_id' => $unit->id,
                    'academic_year_id' => $activeYear->id,
                    'name' => $config['classroom'],
                ],
                [
                    'grade' => $config['grade'],
                    'homeroom_teacher_id' => $teacher->id,
                    'is_active' => true,
                ],
            );

            for ($i = 1; $i <= 2; $i++) {
                $padded = str_pad((string) $studentCounter, 3, '0', STR_PAD_LEFT);
                $nis = $config['nis_prefix'].str_pad((string) $i, 3, '0', STR_PAD_LEFT);

                $student = Student::query()->firstOrCreate(
                    ['nis' => $nis],
                    [
                        'unit_id' => $unit->id,
                        'name' => "Siswa Uji {$padded}",
                        'nickname' => "Uji {$padded}",
                        'gender' => $studentCounter % 2 === 1 ? Gender::Male : Gender::Female,
                        'birth_place' => 'Kolaka',
                        'birth_date' => '2019-05-01',
                        'status' => StudentStatus::Active,
                    ],
                );

                Enrollment::query()->firstOrCreate(
                    [
                        'student_id' => $student->id,
                        'academic_year_id' => $activeYear->id,
                    ],
                    ['classroom_id' => $classroom->id],
                );

                $gPad = str_pad((string) $guardianCounter, 3, '0', STR_PAD_LEFT);
                $phone = '081200000'.str_pad((string) $guardianCounter, 3, '0', STR_PAD_LEFT);

                $guardianUser = User::query()->firstOrCreate(
                    ['username' => $phone],
                    [
                        'name' => "Wali Uji {$gPad}",
                        'phone' => $phone,
                        'password' => self::DEFAULT_PASSWORD,
                        'is_active' => true,
                    ],
                );

                if (! $guardianUser->hasRole(RoleEnum::Guardian->value)) {
                    $guardianUser->assignRole(RoleEnum::Guardian->value);
                }

                $guardian = Guardian::query()->firstOrCreate(
                    ['user_id' => $guardianUser->id],
                    [
                        'name' => "Wali Uji {$gPad}",
                        'phone' => $phone,
                        'relation' => GuardianRelation::Father,
                    ],
                );

                if (! $guardian->students()->where('students.id', $student->id)->exists()) {
                    $guardian->students()->attach($student->id, [
                        'relation' => GuardianRelation::Father->value,
                        'is_primary' => true,
                    ]);
                }

                $studentCounter++;
                $guardianCounter++;
            }
        }

        $this->call(LegacyMenusDemoSeeder::class);
    }
}
