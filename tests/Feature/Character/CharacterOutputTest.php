<?php

declare(strict_types=1);

use App\Enums\Curriculum;
use App\Enums\Role as RoleEnum;
use App\Models\AcademicYear;
use App\Models\CharacterAssessment;
use App\Models\CharacterReview;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\Guardian;
use App\Models\Student;
use App\Models\Unit;
use App\Models\User;
use App\Notifications\CharacterAssessmentApprovedNotification;
use App\Services\Character\CharacterAssessmentService;
use App\Services\Character\CharacterSettingsService;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->travelTo(CarbonImmutable::parse('2026-08-10 10:00', 'Asia/Makassar'));
});

/**
 * @return array{classroom: Classroom, teacher: User, head: User, child: Student, other: Student, guardian: User}
 */
function outputSetup(): array
{
    $unit = Unit::factory()->create(['code' => 'MI']);
    $year = AcademicYear::factory()->active()->create(['starts_on' => '2026-08-01', 'ends_on' => '2026-12-19']);
    $teacher = User::factory()->create();
    $teacher->assignRole(RoleEnum::Teacher->value);
    $head = User::factory()->create();
    $head->assignRole(RoleEnum::UnitHead->value);
    $classroom = Classroom::factory()->for($unit)->for($year)->create(['name' => '2-A', 'homeroom_teacher_id' => $teacher->id]);

    $child = Student::factory()->for($unit)->create(['name' => 'Anak Sendiri']);
    $other = Student::factory()->for($unit)->create(['name' => 'Anak Lain']);

    foreach ([$child, $other] as $student) {
        Enrollment::factory()->create(['student_id' => $student->id, 'classroom_id' => $classroom->id, 'academic_year_id' => $year->id])
            ->forceFill(['created_at' => '2026-07-20 08:00:00'])->save();
    }

    $guardian = User::factory()->create();
    Guardian::factory()->withGuardianAccount()->linkedTo($child)->create(['user_id' => $guardian->id]);

    app(CharacterSettingsService::class)->applyCurriculum($unit, Curriculum::Merdeka, $head);

    return compact('classroom', 'teacher', 'head', 'child', 'other', 'guardian');
}

function submitClass(Classroom $classroom, User $teacher): CharacterReview
{
    $service = app(CharacterAssessmentService::class);
    $review = $service->compute($classroom, $classroom->academicYear, $teacher);

    CharacterAssessment::query()->whereNull('computed_level')->get()
        ->each(fn (CharacterAssessment $a) => $service->correct($a, ['final_level' => 'BSH', 'override_reason' => 'Observasi kelas'], $teacher));

    return $service->submit($review, $teacher);
}

test('wali tidak melihat penilaian sebelum disetujui lalu menerima notifikasi dan melihatnya setelah disetujui', function (): void {
    Notification::fake();
    ['classroom' => $classroom, 'teacher' => $teacher, 'head' => $head, 'child' => $child, 'guardian' => $guardian] = outputSetup();

    $review = submitClass($classroom, $teacher);

    $this->actingAs($guardian)->get(route('guardian.students.character.index', $child))
        ->assertInertia(fn (Assert $page) => $page->component('guardian/character')->where('reports', []));
    $this->actingAs($guardian)->get(route('guardian.students.character.pdf', [$child, $review]))->assertNotFound();

    app(CharacterAssessmentService::class)->approve($review, $head);

    Notification::assertSentTo($guardian, CharacterAssessmentApprovedNotification::class);

    $this->actingAs($guardian)->get(route('guardian.students.character.index', $child))
        ->assertInertia(fn (Assert $page) => $page
            ->has('reports', 1)
            ->has('reports.0.dimensions', 3)
            ->where('reports.0.dimensions.0.level_label', fn (string $label) => in_array($label, ['Sangat Berkembang', 'Berkembang Sesuai Harapan', 'Sedang Berkembang', 'Mulai Berkembang'], true))
        );

    $this->actingAs($guardian)->get(route('guardian.students.character.pdf', [$child, $review]))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');
});

test('wali tidak bisa membuka penilaian anak lain', function (): void {
    ['classroom' => $classroom, 'teacher' => $teacher, 'head' => $head, 'other' => $other, 'guardian' => $guardian] = outputSetup();
    $review = submitClass($classroom, $teacher);
    app(CharacterAssessmentService::class)->approve($review, $head);

    $this->actingAs($guardian)->get(route('guardian.students.character.index', $other))->assertNotFound();
    $this->actingAs($guardian)->get(route('guardian.students.character.pdf', [$other, $review]))->assertNotFound();
});

test('wali kelas mengunduh PDF rombel dan Excel untuk RDM', function (): void {
    ['classroom' => $classroom, 'teacher' => $teacher] = outputSetup();
    submitClass($classroom, $teacher);

    $this->actingAs($teacher)->get(route('admin.character.pdf', $classroom))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    $response = $this->actingAs($teacher)->get(route('admin.character.excel', $classroom))->assertOk();
    expect($response->headers->get('content-disposition'))->toContain('karakter-2-a.xlsx');

    $outsider = User::factory()->create();
    $outsider->assignRole(RoleEnum::Teacher->value);
    $this->actingAs($outsider)->get(route('admin.character.pdf', $classroom))->assertForbidden();
});
