<?php

declare(strict_types=1);

use App\Enums\ExamStatus;
use App\Enums\Feature;
use App\Enums\PointCategory;
use App\Enums\Role as RoleEnum;
use App\Models\AcademicYear;
use App\Models\AttendanceDay;
use App\Models\BehaviorRecord;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\Guardian;
use App\Models\LeaveRequest;
use App\Models\PointEntry;
use App\Models\Student;
use App\Models\Unit;
use App\Models\User;
use App\Services\Features\FeatureFlags;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    // Jumat 27 Nov 2026 pukul 09.00 WITA.
    $this->travelTo(CarbonImmutable::parse('2026-11-27 09:00', 'Asia/Makassar'));
});

/**
 * @return array{user: User, child: Student, classroom: Classroom, year: AcademicYear, other: Student}
 */
function guardianWithChild(): array
{
    $unit = Unit::factory()->create(['code' => 'MI']);
    $year = AcademicYear::factory()->active()->create();
    $classroom = Classroom::factory()->for($unit)->for($year)->create(['name' => '2-A']);
    $child = Student::factory()->for($unit)->create();
    $other = Student::factory()->for($unit)->create();
    Enrollment::factory()->create(['student_id' => $child->id, 'classroom_id' => $classroom->id, 'academic_year_id' => $year->id]);

    $user = User::factory()->create();
    Guardian::factory()->withGuardianAccount()->linkedTo($child)->create(['user_id' => $user->id]);

    return ['user' => $user, 'child' => $child, 'classroom' => $classroom, 'year' => $year, 'other' => $other];
}

test('kartu hari ini tampil walau anak belum tap, dengan lima hari sekolah terakhir tanpa akhir pekan', function (): void {
    ['user' => $user, 'child' => $child] = guardianWithChild();

    AttendanceDay::query()->create(['student_id' => $child->id, 'school_date' => '2026-11-24', 'status' => 'excused', 'decided_by' => 'leave']);

    $this->actingAs($user)
        ->get(route('guardian.home'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('home.today.date', '2026-11-27')
            ->where('home.today.status', null)
            ->where('home.today.off_day', null)
            ->has('home.week', 5)
            ->where('home.week.0.date', '2026-11-23')
            ->where('home.week.1.status', 'excused')
            ->where('home.week.4.date', '2026-11-27')
            ->where('home.cards', [])
        );
});

test('kartu hari ini memuat jam masuk dan izin yang menunggu hanya milik anak sendiri', function (): void {
    ['user' => $user, 'child' => $child, 'other' => $other] = guardianWithChild();

    AttendanceDay::query()->create([
        'student_id' => $child->id, 'school_date' => '2026-11-27', 'status' => 'present',
        'checked_in_at' => CarbonImmutable::parse('2026-11-27 06:52', 'Asia/Makassar'), 'decided_by' => 'scan',
    ]);

    foreach ([$child, $other] as $student) {
        LeaveRequest::query()->create([
            'student_id' => $student->id, 'requested_by' => $user->id, 'type' => 'sick',
            'starts_on' => '2026-11-28', 'ends_on' => '2026-11-28', 'reason' => 'Demam', 'status' => 'pending',
        ]);
    }

    $this->actingAs($user)
        ->get(route('guardian.home'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('home.today.status', 'present')
            ->where('home.today.checked_in_at', '06.52')
            ->where('home.leave.status', 'pending')
            ->where('home.leave.starts_on', '2026-11-28')
            ->where('home.cards.0.key', 'attendance_today')
        );
});

test('ringkasan perilaku memakai saldo terakhir per akun dan catatan terbaru yang tidak dibatalkan', function (): void {
    ['user' => $user, 'child' => $child, 'year' => $year] = guardianWithChild();

    $merit = BehaviorRecord::factory()->create([
        'student_id' => $child->id, 'academic_year_id' => $year->id,
        'category' => PointCategory::GoodDeed, 'points' => 10, 'occurred_on' => '2026-11-26',
    ]);
    BehaviorRecord::factory()->create([
        'student_id' => $child->id, 'academic_year_id' => $year->id,
        'category' => PointCategory::Violation, 'points' => 5, 'occurred_on' => '2026-11-27',
        'status' => 'voided',
    ]);

    foreach ([['merit', 10, 10], ['merit', 5, 15], ['violation', 5, 5], ['violation', -5, 0]] as $i => [$account, $delta, $balance]) {
        PointEntry::query()->create([
            'student_id' => $child->id, 'academic_year_id' => $year->id, 'account' => $account,
            'delta' => $delta, 'balance_after' => $balance, 'source_type' => 'behavior_record',
            'source_id' => $merit->id, 'idempotency_key' => "uji-{$i}", 'created_at' => now(),
        ]);
    }

    $this->actingAs($user)
        ->get(route('guardian.home'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('home.behavior.merit', 15)
            ->where('home.behavior.violation', 0)
            ->where('home.behavior.latest.points', 10)
            ->where('home.behavior.latest.category', 'good_deed')
        );
});

test('kartu ujian hanya berisi ujian rombel anak yang berlangsung atau segera dibuka', function (): void {
    ['user' => $user, 'classroom' => $classroom] = guardianWithChild();

    $own = Exam::factory()->create([
        'title' => 'PTS Matematika', 'classroom_ids' => [$classroom->id], 'status' => ExamStatus::Scheduled,
        'opens_at' => now()->addDay(), 'closes_at' => now()->addDay()->addHours(2),
    ]);
    Exam::factory()->create(['classroom_ids' => [$classroom->id + 100], 'status' => ExamStatus::Scheduled, 'opens_at' => now()->addDay(), 'closes_at' => now()->addDays(2)]);
    Exam::factory()->create(['classroom_ids' => [$classroom->id], 'status' => ExamStatus::Draft, 'opens_at' => now()->addDay(), 'closes_at' => now()->addDays(2)]);
    Exam::factory()->create(['classroom_ids' => [$classroom->id], 'status' => ExamStatus::Scheduled, 'opens_at' => now()->addDays(20), 'closes_at' => now()->addDays(21)]);

    $this->actingAs($user)
        ->get(route('guardian.home'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('home.exams.upcoming', 1)
            ->where('home.exams.upcoming.0.id', $own->id)
            ->where('home.exams.latest_result', null)
        );
});

test('bagian beranda wali bernilai null saat modulnya dimatikan', function (): void {
    ['user' => $user] = guardianWithChild();
    $admin = User::factory()->create();
    $admin->assignRole(RoleEnum::Admin->value);
    app(FeatureFlags::class)->update([
        Feature::Attendance->value => false,
        Feature::Behavior->value => false,
        Feature::Cbt->value => false,
    ], $admin);

    $this->actingAs($user)
        ->get(route('guardian.home'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('home.today', null)
            ->where('home.week', [])
            ->where('home.leave', null)
            ->where('home.behavior', null)
            ->where('home.exams', null)
        );
});
