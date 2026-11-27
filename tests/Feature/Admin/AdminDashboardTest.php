<?php

declare(strict_types=1);

use App\Enums\Role as RoleEnum;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Student;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('beranda staf menampilkan ringkasan data nyata sesuai unit aktif dan izin pengguna', function (): void {
    $mi = Unit::factory()->create(['code' => 'MI', 'name' => 'Madrasah Ibtidaiyah']);
    $year = AcademicYear::factory()->active()->create();
    Classroom::factory()->for($mi)->for($year)->create(['name' => '1-A']);
    Student::factory()->for($mi)->count(2)->create();

    $admin = User::factory()->create();
    $admin->assignRole(RoleEnum::Admin->value);

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/dashboard')
            ->where('summary.unit_code', 'MI')
            ->where('summary.active_students', 2)
            ->where('summary.active_classrooms', 1)
            ->where('permissions.manage_students', true)
        );
});

/**
 * @return array{unit: Unit, year: AcademicYear}
 */
function dashboardSchool(): array
{
    $unit = Unit::factory()->create(['code' => 'MI', 'name' => 'Madrasah Ibtidaiyah']);
    $year = AcademicYear::factory()->active()->create();

    return ['unit' => $unit, 'year' => $year];
}

function enrollIn(Classroom $classroom, int $count): \Illuminate\Support\Collection
{
    return Student::factory()->count($count)->create(['unit_id' => $classroom->unit_id])
        ->each(fn (Student $student) => \App\Models\Enrollment::factory()->create([
            'student_id' => $student->id,
            'classroom_id' => $classroom->id,
            'academic_year_id' => $classroom->academic_year_id,
        ]));
}

test('beranda staf menghitung presensi hari ini per status dan siswa belum tap', function (): void {
    ['unit' => $unit, 'year' => $year] = dashboardSchool();
    $classroom = Classroom::factory()->for($unit)->for($year)->create(['name' => '2-A']);
    $students = enrollIn($classroom, 4);
    $today = \Carbon\CarbonImmutable::now('Asia/Makassar')->toDateString();

    \App\Models\AttendanceDay::query()->create(['student_id' => $students[0]->id, 'school_date' => $today, 'status' => 'present', 'decided_by' => 'scan']);
    \App\Models\AttendanceDay::query()->create(['student_id' => $students[1]->id, 'school_date' => $today, 'status' => 'late', 'decided_by' => 'scan']);

    $admin = User::factory()->create();
    $admin->assignRole(RoleEnum::Admin->value);

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('attendance.scope', 'unit')
            ->where('attendance.total', 4)
            ->where('attendance.counts.present', 1)
            ->where('attendance.counts.late', 1)
            ->where('attendance.counts.not_yet', 2)
            ->where('attendance.classrooms.0.name', '2-A')
            ->where('attendance.classrooms.0.not_yet', 2)
            ->has('queue')
            ->has('dataGaps')
        );
});

test('wali kelas hanya melihat presensi rombel yang ia walikan beserta nama siswa belum tap', function (): void {
    ['unit' => $unit, 'year' => $year] = dashboardSchool();
    $teacher = User::factory()->create();
    $teacher->assignRole(RoleEnum::Teacher->value);

    $own = Classroom::factory()->for($unit)->for($year)->create(['name' => '2-A', 'homeroom_teacher_id' => $teacher->id]);
    $other = Classroom::factory()->for($unit)->for($year)->create(['name' => '3-A']);
    $mine = enrollIn($own, 2);
    $others = enrollIn($other, 5);

    foreach ([$mine[0], $others[0]] as $student) {
        LeaveRequest::query()->create([
            'student_id' => $student->id, 'requested_by' => $teacher->id, 'type' => 'sick',
            'starts_on' => now()->toDateString(), 'ends_on' => now()->toDateString(),
            'reason' => 'Demam', 'status' => 'pending',
        ]);
    }

    $this->actingAs($teacher)
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('attendance.scope', 'homeroom')
            ->where('attendance.total', 2)
            ->has('attendance.not_yet_students', 2)
            ->where('attendance.not_yet_students.0.classroom', '2-A')
            ->where('queue', null)
            ->where('dataGaps', null)
            ->where('leaves.count', 1)
            ->where('leaves.items.0.student_name', $mine[0]->name)
        );
});

test('beranda staf menampilkan izin menunggu dan ujian yang sedang berlangsung', function (): void {
    ['unit' => $unit, 'year' => $year] = dashboardSchool();
    $classroom = Classroom::factory()->for($unit)->for($year)->create();
    $students = enrollIn($classroom, 2);
    $admin = User::factory()->create();
    $admin->assignRole(RoleEnum::Admin->value);

    \App\Models\LeaveRequest::query()->create([
        'student_id' => $students[0]->id, 'requested_by' => $admin->id, 'type' => 'sick',
        'starts_on' => now()->toDateString(), 'ends_on' => now()->toDateString(),
        'reason' => 'Demam', 'status' => 'pending',
    ]);

    $exam = \App\Models\Exam::factory()->create([
        'status' => \App\Enums\ExamStatus::Running,
        'opens_at' => now()->subHour(),
        'closes_at' => now()->addHour(),
    ]);
    \App\Models\ExamSession::factory()->create(['exam_id' => $exam->id, 'student_id' => $students[0]->id]);
    \App\Models\ExamSession::factory()->create(['exam_id' => $exam->id, 'student_id' => $students[1]->id, 'status' => \App\Enums\ExamSessionStatus::Submitted]);
    \App\Models\Exam::factory()->create(['status' => \App\Enums\ExamStatus::Draft, 'opens_at' => now()]);

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('leaves.count', 1)
            ->where('leaves.items.0.student_name', $students[0]->name)
            ->has('exams.today', 1)
            ->where('exams.today.0.id', $exam->id)
            ->where('exams.today.0.in_progress', 1)
            ->where('exams.today.0.finished', 1)
        );
});

test('bagian beranda tidak dikirim bila modulnya dimatikan atau peran tidak berizin', function (): void {
    dashboardSchool();
    $admin = User::factory()->create();
    $admin->assignRole(RoleEnum::Admin->value);
    app(\App\Services\Features\FeatureFlags::class)->update([
        \App\Enums\Feature::Attendance->value => false,
        \App\Enums\Feature::Cbt->value => false,
    ], $admin);

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('attendance', null)
            ->where('leaves', null)
            ->where('exams', null)
        );

    $treasurer = User::factory()->create();
    $treasurer->assignRole(RoleEnum::Treasurer->value);

    $this->actingAs($treasurer)
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('attendance', null)
            ->where('exams', null)
            ->where('dataGaps', null)
            ->where('queue', null)
            ->where('recentMutations', [])
        );
});

test('data induk yang perlu dilengkapi dihitung dari unit dan tahun ajaran terpilih', function (): void {
    ['unit' => $unit, 'year' => $year] = dashboardSchool();
    Classroom::factory()->for($unit)->for($year)->create(['homeroom_teacher_id' => null]);
    Student::factory()->count(3)->create(['unit_id' => $unit->id]);

    $admin = User::factory()->create();
    $admin->assignRole(RoleEnum::Admin->value);

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('dataGaps.students_without_classroom', 3)
            ->where('dataGaps.students_without_guardian', 3)
            ->where('dataGaps.classrooms_without_homeroom', 1)
        );
});
