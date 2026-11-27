<?php

declare(strict_types=1);

use App\Enums\CharacterReviewStatus;
use App\Enums\Curriculum;
use App\Enums\HabitAspect;
use App\Enums\PointCategory;
use App\Enums\Role as RoleEnum;
use App\Models\AcademicYear;
use App\Models\AttendanceDay;
use App\Models\AuditLog;
use App\Models\BehaviorRecord;
use App\Models\CharacterAssessment;
use App\Models\CharacterDimension;
use App\Models\CharacterReview;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\HabitItem;
use App\Models\HabitLog;
use App\Models\Student;
use App\Models\Unit;
use App\Models\User;
use App\Services\Character\CharacterSettingsService;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    // Semester 1–10 Agustus 2026 (Sabtu–Minggu libur), dinilai pada 10 Agustus 2026.
    $this->travelTo(CarbonImmutable::parse('2026-08-10 10:00', 'Asia/Makassar'));
});

/**
 * @return array{unit: Unit, year: AcademicYear, classroom: Classroom, teacher: User, admin: User, students: list<Student>}
 */
function characterClass(int $students = 2): array
{
    $unit = Unit::factory()->create(['code' => 'MI']);
    $year = AcademicYear::factory()->active()->create(['starts_on' => '2026-08-01', 'ends_on' => '2026-12-19']);
    $teacher = User::factory()->create();
    $teacher->assignRole(RoleEnum::Teacher->value);
    $admin = User::factory()->create();
    $admin->assignRole(RoleEnum::Admin->value);
    $classroom = Classroom::factory()->for($unit)->for($year)->create(['name' => '2-A', 'homeroom_teacher_id' => $teacher->id]);

    $list = [];
    for ($i = 0; $i < $students; $i++) {
        $student = Student::factory()->for($unit)->create(['name' => 'Siswa '.chr(65 + $i)]);
        $enrollment = Enrollment::factory()->create(['student_id' => $student->id, 'classroom_id' => $classroom->id, 'academic_year_id' => $year->id]);
        $enrollment->forceFill(['created_at' => '2026-07-20 08:00:00'])->save();
        $list[] = $student;
    }

    app(CharacterSettingsService::class)->applyCurriculum($unit, Curriculum::Merdeka, $admin);

    return ['unit' => $unit, 'year' => $year, 'classroom' => $classroom, 'teacher' => $teacher, 'admin' => $admin, 'students' => $list];
}

/** Centang & verifikasi satu item setiap hari 1–10 Agustus. */
function habitEveryDay(Student $student, HabitItem $item, User $by): void
{
    for ($d = 1; $d <= 10; $d++) {
        HabitLog::query()->create([
            'student_id' => $student->id, 'habit_item_id' => $item->id, 'log_date' => sprintf('2026-08-%02d', $d),
            'done' => true, 'submitted_by' => $by->id, 'submitted_at' => now(), 'verification' => 'verified',
        ]);
    }
}

function assessmentOf(Student $student, string $dimensionCode): CharacterAssessment
{
    $dimension = CharacterDimension::query()->where('code', $dimensionCode)->firstOrFail();

    return CharacterAssessment::query()->where('student_id', $student->id)->where('character_dimension_id', $dimension->id)->firstOrFail();
}

test('wali kelas menghitung capaian dari pembiasaan, kehadiran, dan catatan perilaku aktif', function (): void {
    ['classroom' => $classroom, 'teacher' => $teacher, 'students' => [$a, $b]] = characterClass();
    $spiritual = HabitItem::factory()->create(['aspect' => HabitAspect::Spiritual]);
    habitEveryDay($a, $spiritual, $teacher);

    // 6 hari sekolah (3–7, 10 Agustus): tepat waktu semua.
    foreach (['2026-08-03', '2026-08-04', '2026-08-05', '2026-08-06', '2026-08-07', '2026-08-10'] as $date) {
        AttendanceDay::query()->create(['student_id' => $a->id, 'school_date' => $date, 'status' => 'present', 'decided_by' => 'scan']);
    }

    BehaviorRecord::factory()->create(['student_id' => $a->id, 'academic_year_id' => $classroom->academic_year_id, 'category' => PointCategory::Violation, 'points' => 15]);
    BehaviorRecord::factory()->create(['student_id' => $a->id, 'academic_year_id' => $classroom->academic_year_id, 'category' => PointCategory::Violation, 'points' => 40, 'status' => 'voided']);

    $this->actingAs($teacher)->post(route('admin.character.compute', $classroom))->assertSessionHasNoErrors();

    $keimanan = assessmentOf($a, 'keimanan');
    $disiplin = assessmentOf($a, 'kedisiplinan');

    expect($keimanan->computed_level)->toBe('SAB')
        ->and((float) $keimanan->computed_score)->toBe(100.0)
        // kehadiran 100 × 50% + pelanggaran 15 poin (catatan batal diabaikan) → 75 × 50% = 87,5
        ->and((float) $disiplin->computed_score)->toBe(87.5)
        ->and($disiplin->computed_level)->toBe('BSH')
        ->and($disiplin->description)->toContain('kedisiplinan & kemandirian')
        // Siswa B tanpa centang pembiasaan: dimensi keimanan data kurang.
        ->and(assessmentOf($b, 'keimanan')->computed_level)->toBeNull()
        ->and(AuditLog::query()->where('action', 'character.computed')->exists())->toBeTrue();
});

test('pengajuan ditolak selama ada data kurang lalu berhasil setelah wali kelas menetapkan capaian dengan alasan', function (): void {
    ['classroom' => $classroom, 'teacher' => $teacher, 'students' => [$a]] = characterClass(1);
    $this->actingAs($teacher)->post(route('admin.character.compute', $classroom));

    $this->actingAs($teacher)->post(route('admin.character.submit', $classroom))->assertSessionHasErrors('review');

    $pending = CharacterAssessment::query()->whereNull('computed_level')->get();
    expect($pending)->not->toBeEmpty();

    foreach ($pending as $assessment) {
        $this->actingAs($teacher)
            ->patch(route('admin.character.correct', $assessment), ['final_level' => 'BSH'])
            ->assertSessionHasErrors('override_reason');

        $this->actingAs($teacher)
            ->patch(route('admin.character.correct', $assessment), ['final_level' => 'BSH', 'override_reason' => 'Pengamatan di kelas baik'])
            ->assertSessionHasNoErrors();
    }

    $this->actingAs($teacher)->post(route('admin.character.submit', $classroom))->assertSessionHasNoErrors();

    expect(CharacterReview::query()->firstOrFail()->status)->toBe(CharacterReviewStatus::Submitted);
});

test('hitung ulang mempertahankan capaian akhir dan deskripsi yang diubah manual', function (): void {
    ['classroom' => $classroom, 'teacher' => $teacher, 'students' => [$a]] = characterClass(1);
    $this->actingAs($teacher)->post(route('admin.character.compute', $classroom));
    $assessment = assessmentOf($a, 'kedisiplinan');

    $this->actingAs($teacher)->patch(route('admin.character.correct', $assessment), [
        'final_level' => 'MB', 'override_reason' => 'Sering bolos jam pelajaran', 'description' => 'Ditulis wali kelas.',
    ])->assertSessionHasNoErrors();

    $this->actingAs($teacher)->post(route('admin.character.compute', $classroom))->assertSessionHasNoErrors();
    $fresh = $assessment->fresh();

    expect($fresh?->final_level)->toBe('MB')
        ->and($fresh?->description)->toBe('Ditulis wali kelas.')
        ->and($fresh?->description_edited)->toBeTrue();
});

test('kepala unit mengubah capaian saat diajukan, menyetujui, lalu nilai tidak bisa diubah sampai kunci dibuka', function (): void {
    ['unit' => $unit, 'classroom' => $classroom, 'teacher' => $teacher, 'students' => [$a]] = characterClass(1);
    $head = User::factory()->create();
    $head->assignRole(RoleEnum::UnitHead->value);

    $this->actingAs($teacher)->post(route('admin.character.compute', $classroom));
    CharacterAssessment::query()->whereNull('computed_level')->get()->each(fn (CharacterAssessment $x) => $this->actingAs($teacher)
        ->patch(route('admin.character.correct', $x), ['final_level' => 'SB', 'override_reason' => 'Observasi']));
    $this->actingAs($teacher)->post(route('admin.character.submit', $classroom));

    $assessment = assessmentOf($a, 'kedisiplinan');

    // Wali kelas tidak lagi bisa mengubah setelah diajukan.
    $this->actingAs($teacher)->patch(route('admin.character.correct', $assessment), ['final_level' => 'SAB', 'override_reason' => 'x'])->assertForbidden();
    $this->actingAs($teacher)->post(route('admin.character.approve', $classroom))->assertForbidden();

    $this->actingAs($head)->patch(route('admin.character.correct', $assessment), ['final_level' => 'SAB', 'override_reason' => 'Juara lomba disiplin'])->assertSessionHasNoErrors();
    $this->actingAs($head)->post(route('admin.character.approve', $classroom))->assertSessionHasNoErrors();

    expect($assessment->fresh()?->effectiveLevel())->toBe('SAB')
        ->and(CharacterReview::query()->firstOrFail()->status)->toBe(CharacterReviewStatus::Approved);

    $this->actingAs($head)->patch(route('admin.character.correct', $assessment), ['final_level' => 'MB', 'override_reason' => 'x'])->assertForbidden();

    $this->actingAs($head)->post(route('admin.character.reopen', $classroom), ['reason' => ''])->assertSessionHasErrors('reason');
    $this->actingAs($head)->post(route('admin.character.reopen', $classroom), ['reason' => 'Ada siswa pindahan'])->assertSessionHasNoErrors();

    expect(CharacterReview::query()->firstOrFail()->status)->toBe(CharacterReviewStatus::Draft)
        ->and(AuditLog::query()->where('action', 'character.reopened')->exists())->toBeTrue();
});

test('guru yang bukan wali kelas rombel tidak bisa melihat atau menghitung', function (): void {
    ['classroom' => $classroom] = characterClass(1);
    $other = User::factory()->create();
    $other->assignRole(RoleEnum::Teacher->value);

    $this->actingAs($other)->get(route('admin.character.show', $classroom))->assertForbidden();
    $this->actingAs($other)->post(route('admin.character.compute', $classroom))->assertForbidden();

    $guardianless = User::factory()->create();
    $guardianless->assignRole(RoleEnum::Treasurer->value);
    $this->actingAs($guardianless)->get(route('admin.character.index'))->assertForbidden();
});

test('daftar dan halaman rombel menampilkan status serta baris siswa', function (): void {
    ['classroom' => $classroom, 'teacher' => $teacher] = characterClass(2);
    $this->actingAs($teacher)->post(route('admin.character.compute', $classroom));

    $this->actingAs($teacher)->get(route('admin.character.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/character/index')
            ->where('classrooms.0.name', '2-A')
            ->where('classrooms.0.status', 'draft')
            ->where('classrooms.0.students_count', 2)
        );

    $this->actingAs($teacher)->get(route('admin.character.show', $classroom))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/character/show')
            ->has('dimensions', 3)
            ->has('rows', 2)
            ->where('can.manage', true)
            ->where('can.review', false)
        );
});
