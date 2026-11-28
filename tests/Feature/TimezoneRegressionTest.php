<?php

declare(strict_types=1);

use App\Enums\Role as RoleEnum;
use App\Enums\Semester;
use App\Exceptions\Wallet\DailyLimitExceededException;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Exam;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Models\Unit;
use App\Models\User;
use App\Services\Wallet\WalletService;
use Database\Seeders\RolesAndPermissionsSeeder;

/*
| Regresi: kolom waktu disimpan dalam zona aplikasi (WITA). Batas kueri dan nilai tersimpan tidak boleh
| dikonversi ke UTC, atau transaksi sore hari / jam ujian bergeser 8 jam.
*/

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('limit jajan harian menghitung belanja sore hari pada hari yang sama', function (): void {
    $this->travelTo(now('Asia/Makassar')->setDate(2026, 11, 20)->setTime(17, 0));
    $student = Student::factory()->create();
    $wallet = app(WalletService::class);
    $wallet->topUp($student, 50_000, 'tz-topup');
    $wallet->updateDailyLimit($student, 20_000, User::factory()->create());

    $wallet->purchase($student, 15_000, 'tz-beli-1');

    expect(fn () => $wallet->purchase($student, 10_000, 'tz-beli-2'))
        ->toThrow(DailyLimitExceededException::class);
});

test('jam buka dan tutup ujian tersimpan sesuai jam WITA yang diketik', function (): void {
    $unit = Unit::factory()->create(['code' => 'MI']);
    $year = AcademicYear::factory()->active()->create(['name' => '2026/2027', 'semester' => Semester::Ganjil]);
    $subject = Subject::factory()->for($unit)->create(['code' => 'MTK']);
    $classroom = Classroom::factory()->for($unit)->for($year)->create(['name' => '5-A']);
    $teacher = User::factory()->create();
    $teacher->assignRole(RoleEnum::Teacher->value);
    TeachingAssignment::factory()->for($teacher, 'teacher')->for($subject)->for($classroom)->for($year)->create();

    $this->actingAs($teacher)->post(route('admin.cbt.exams.store'), [
        'subject_id' => $subject->id, 'title' => 'PAS', 'grade' => '5', 'classroom_ids' => [$classroom->id],
        'opens_at' => '2026-12-01 07:30', 'closes_at' => '2026-12-01 10:00', 'duration_minutes' => 90,
        'shuffle_questions' => true, 'shuffle_options' => true,
        'weights' => ['mcq' => 2, 'matching' => 2, 'essay_paper' => 4], 'access_token' => 'PAS5A',
        'violation_limit' => 3, 'device_modes' => ['lab'], 'show_result_to_guardian' => false,
    ])->assertSessionHasNoErrors();

    $exam = Exam::query()->sole();
    expect($exam->opens_at->format('Y-m-d H:i'))->toBe('2026-12-01 07:30')
        ->and($exam->closes_at->format('Y-m-d H:i'))->toBe('2026-12-01 10:00');
});
