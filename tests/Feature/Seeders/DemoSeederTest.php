<?php

declare(strict_types=1);

use App\Enums\Role as RoleEnum;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\Guardian;
use App\Models\Student;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;

test('DemoSeeder menolak berjalan di lingkungan production', function (): void {
    $originalEnv = app()->environment();
    app()->detectEnvironment(fn (): string => 'production');

    try {
        expect(fn () => app(DemoSeeder::class)->run())
            ->toThrow(RuntimeException::class, 'produksi');
    } finally {
        app()->detectEnvironment(fn (): string => is_string($originalEnv) ? $originalEnv : 'testing');
    }

    expect(Student::query()->count())->toBe(0)
        ->and(User::query()->count())->toBe(0);
});

test('DemoSeeder membuat unit, tahun ajaran aktif, rombel, siswa, wali, dan staf berpola uji', function (): void {
    $this->seed(DemoSeeder::class);

    expect(Unit::query()->whereIn('code', ['MI', 'RA', 'TPQ'])->count())->toBe(3)
        ->and(AcademicYear::query()->where('is_active', true)->count())->toBe(1)
        ->and(Classroom::query()->count())->toBeGreaterThanOrEqual(3)
        ->and(Student::query()->count())->toBeGreaterThanOrEqual(6)
        ->and(Enrollment::query()->count())->toBe(Student::query()->count())
        ->and(Guardian::query()->count())->toBeGreaterThanOrEqual(3);

    $firstStudent = Student::query()->orderBy('id')->firstOrFail();
    expect($firstStudent->name)->toStartWith('Siswa Uji ');

    foreach (RoleEnum::cases() as $role) {
        if ($role === RoleEnum::Guardian) {
            continue;
        }

        expect(User::role($role->value)->exists())->toBeTrue();
    }
});

test('DatabaseSeeder berjalan lengkap dan DemoSeeder idempoten', function (): void {
    $this->seed(DatabaseSeeder::class);
    $studentCount = Student::query()->count();

    $this->seed(DemoSeeder::class);

    expect(Student::query()->count())->toBe($studentCount);
});
