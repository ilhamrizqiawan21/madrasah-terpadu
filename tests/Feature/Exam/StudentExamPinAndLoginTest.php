<?php

declare(strict_types=1);

use App\Enums\Feature;
use App\Enums\Role as RoleEnum;
use App\Enums\StudentStatus;
use App\Models\AcademicYear;
use App\Models\AuditLog;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\StudentExamPin;
use App\Models\Unit;
use App\Models\User;
use App\Services\Cbt\StudentExamPinService;
use App\Services\Features\FeatureFlags;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function pinAdmin(): User
{
    $admin = User::factory()->withTwoFactor()->create();
    $admin->assignRole(RoleEnum::Admin->value);

    return $admin;
}

function pinTeacher(string $name = 'Wali Kelas'): User
{
    $teacher = User::factory()->create(['name' => $name]);
    $teacher->assignRole(RoleEnum::Teacher->value);

    return $teacher;
}

test('tamu diarahkan ke halaman masuk ujian saat membuka area ujian', function (): void {
    $this->get(route('exam.index'))->assertRedirect(route('exam.login'));
});

test('kunci fitur cbt nonaktif mengembalikan 404 di rute ujian dan pin siswa', function (): void {
    $admin = pinAdmin();
    app(FeatureFlags::class)->update([Feature::Cbt->value => false], $admin);

    $this->get(route('exam.login'))->assertNotFound();
    $this->actingAs($admin)->get(route('admin.cbt.pins.index'))->assertNotFound();
});

test('admin dan wali kelas dapat mereset pin siswa sementara guru lain dan bendahara ditolak', function (): void {
    $unit = Unit::factory()->create(['code' => 'MI']);
    $year = AcademicYear::factory()->active()->create();
    $homeroom = pinTeacher('Wali Kelas 5A');
    $otherTeacher = pinTeacher('Guru Lain');
    $treasurer = User::factory()->withTwoFactor()->create();
    $treasurer->assignRole(RoleEnum::Treasurer->value);

    $classroom = Classroom::factory()->for($unit)->for($year)->create([
        'name' => '5-A',
        'homeroom_teacher_id' => $homeroom->id,
    ]);
    $student = Student::factory()->for($unit)->create(['nis' => '2026001', 'name' => 'Ahmad']);
    Enrollment::factory()->for($student)->for($classroom)->for($year)->create();

    // Bendahara ditolak.
    $this->actingAs($treasurer)->get(route('admin.cbt.pins.index'))->assertForbidden();

    // Guru bukan wali kelas ditolak reset siswa maupun rombel ini.
    $this->actingAs($otherTeacher)->post(route('admin.cbt.pins.reset-student', $student))->assertForbidden();
    $this->actingAs($otherTeacher)->post(route('admin.cbt.pins.reset-classroom', $classroom))->assertForbidden();

    // Wali kelas dapat melihat dan mereset PIN siswanya.
    $this->actingAs($homeroom)->get(route('admin.cbt.pins.index', ['classroom_id' => $classroom->id]))->assertOk();

    $response = $this->actingAs($homeroom)->post(route('admin.cbt.pins.reset-student', $student));
    $response->assertRedirect();

    $pinRecord = StudentExamPin::query()->where('student_id', $student->id)->firstOrFail();
    expect(strlen($pinRecord->pin_hash))->toBeGreaterThan(20)
        ->and($pinRecord->reset_by)->toBe($homeroom->id);

    // PIN tidak tersimpan plaintext di audit log.
    $audit = AuditLog::query()->where('subject_type', StudentExamPin::class)->firstOrFail();
    expect(json_encode($audit->after))->not->toContain('pin_hash');

    // Admin dapat mereset satu rombel sekaligus.
    $student2 = Student::factory()->for($unit)->create(['nis' => '2026002', 'name' => 'Budi']);
    Enrollment::factory()->for($student2)->for($classroom)->for($year)->create();

    $this->actingAs(pinAdmin())
        ->post(route('admin.cbt.pins.reset-classroom', $classroom))
        ->assertRedirect()
        ->assertSessionHas('generatedPins');
});

test('siswa dapat masuk dengan nis dan pin yang benar serta terisolasi dari area lain', function (): void {
    $unit = Unit::factory()->create(['code' => 'MI']);
    $student = Student::factory()->for($unit)->create(['nis' => '2026010', 'name' => 'Fatimah']);
    $admin = pinAdmin();

    $pin = app(StudentExamPinService::class)->resetForStudent($student, $admin);
    expect(Hash::check($pin, $student->examPin()->firstOrFail()->pin_hash))->toBeTrue();

    $oldSessionId = session()->getId();

    $this->post(route('exam.login.store'), [
        'nis' => '2026010',
        'pin' => $pin,
    ])->assertRedirect(route('exam.index'));

    expect(Auth::guard('student')->id())->toBe($student->id)
        ->and(Auth::guard('web')->check())->toBeFalse()
        ->and(session()->getId())->not->toBe($oldSessionId);

    // Siswa bisa mengakses /ujian.
    $this->get(route('exam.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('exam/index')
            ->where('student.nis', '2026010')
            ->where('student.name', 'Fatimah'));

    // Siswa tidak bisa membuka /admin, /wali, /gerbang, atau /kantin.
    $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
    $this->get(route('guardian.home'))->assertRedirect(route('login'));
    $this->get(route('gate.index'))->assertRedirect(route('login'));
    $this->get(route('canteen.index'))->assertRedirect(route('login'));

    // Keluar dari ujian.
    $this->post(route('exam.logout'))->assertRedirect(route('exam.login'));
    expect(Auth::guard('student')->check())->toBeFalse();
});

test('galat masuk identik untuk nis tidak ada siswa nonaktif dan pin salah tanpa membocorkan enumerasi', function (): void {
    $unit = Unit::factory()->create();
    $active = Student::factory()->for($unit)->create(['nis' => '2026020']);
    $inactive = Student::factory()->for($unit)->create(['nis' => '2026021', 'status' => StudentStatus::Inactive]);
    $admin = pinAdmin();

    app(StudentExamPinService::class)->resetForStudent($active, $admin);
    $inactivePin = app(StudentExamPinService::class)->resetForStudent($inactive, $admin);

    $this->post(route('exam.login.store'), ['nis' => '9999999', 'pin' => '123456'])
        ->assertSessionHasErrors(['nis' => 'NIS atau PIN ujian tidak sesuai.']);

    $this->post(route('exam.login.store'), ['nis' => '2026020', 'pin' => '000000'])
        ->assertSessionHasErrors(['nis' => 'NIS atau PIN ujian tidak sesuai.']);

    $this->post(route('exam.login.store'), ['nis' => '2026021', 'pin' => $inactivePin])
        ->assertSessionHasErrors(['nis' => 'NIS atau PIN ujian tidak sesuai.']);
});

test('rate limit berlaku per nis bukan per ip', function (): void {
    $unit = Unit::factory()->create();
    $studentA = Student::factory()->for($unit)->create(['nis' => '2026030']);
    $studentB = Student::factory()->for($unit)->create(['nis' => '2026031']);
    $admin = pinAdmin();

    app(StudentExamPinService::class)->resetForStudent($studentA, $admin);
    $pinB = app(StudentExamPinService::class)->resetForStudent($studentB, $admin);

    for ($i = 0; $i < 5; $i++) {
        $this->post(route('exam.login.store'), ['nis' => '2026030', 'pin' => '000000'])
            ->assertSessionHasErrors('nis');
    }

    // Percobaan ke-6 pada NIS A terkena rate limit.
    $this->post(route('exam.login.store'), ['nis' => '2026030', 'pin' => '000000'])
        ->assertSessionHasErrors('nis');
    expect(session('errors')->first('nis'))->toContain('Terlalu banyak percobaan');

    // NIS B dari IP yang sama tetap bisa login.
    $this->post(route('exam.login.store'), ['nis' => '2026031', 'pin' => $pinB])
        ->assertRedirect(route('exam.index'));
});
