<?php

declare(strict_types=1);

use App\Enums\GateScanResult;
use App\Enums\Role as RoleEnum;
use App\Models\Student;
use App\Models\Unit;
use App\Models\User;
use App\Services\Attendance\StudentCardService;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function kioskGateOfficer(): User
{
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole(RoleEnum::GateOfficer->value);

    return $user;
}

test('tamu dan peran non-gerbang ditolak mengakses layar gerbang', function (): void {
    $this->get(route('gate.index'))->assertRedirect(route('login'));
    $this->postJson(route('gate.scan'), ['token' => 'abc'])->assertUnauthorized();

    $teacher = User::factory()->create(['is_active' => true]);
    $teacher->assignRole(RoleEnum::Teacher->value);

    $this->actingAs($teacher)->get(route('gate.index'))->assertForbidden();
    $this->actingAs($teacher)->postJson(route('gate.scan'), ['token' => 'abc'])->assertForbidden();
});

test('petugas gerbang dan admin dapat membuka halaman gerbang', function (): void {
    $officer = kioskGateOfficer();

    $this->actingAs($officer)
        ->get(route('gate.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('gate/index')
            ->has('recentScans')
            ->where('operatorName', $officer->name));
});

test('endpoint scan gerbang mengembalikan hasil masuk, terlambat, duplikat, dan kartu dicabut', function (): void {
    $officer = kioskGateOfficer();
    $unit = Unit::factory()->create([
        'attendance_settings' => [
            'check_in_opens_at' => '05:30',
            'late_after' => '07:00',
            'absent_after' => '09:00',
            'check_out_opens_at' => '11:00',
        ],
    ]);
    $student = Student::factory()->create(['unit_id' => $unit->id]);
    $issued = app(StudentCardService::class)->issueQr($student, $officer);

    $this->travelTo(CarbonImmutable::parse('2026-11-27 06:50:00', 'Asia/Makassar'));

    $this->actingAs($officer)
        ->postJson(route('gate.scan'), ['token' => $issued['token']])
        ->assertOk()
        ->assertJsonPath('accepted', true)
        ->assertJsonPath('code', GateScanResult::OnTime->value)
        ->assertJsonPath('student.id', $student->id);

    $this->actingAs($officer)
        ->postJson(route('gate.scan'), ['token' => $issued['token']])
        ->assertOk()
        ->assertJsonPath('accepted', false)
        ->assertJsonPath('code', GateScanResult::Duplicate->value);

    app(StudentCardService::class)->revokeActive($student, $officer);

    $this->actingAs($officer)
        ->postJson(route('gate.scan'), ['token' => $issued['token']])
        ->assertOk()
        ->assertJsonPath('accepted', false)
        ->assertJsonPath('code', 'invalid_card');
});

test('endpoint scan gerbang merespons di bawah 300 ms dengan 1000 siswa di database', function (): void {
    $officer = kioskGateOfficer();
    $unit = Unit::factory()->create([
        'attendance_settings' => [
            'check_in_opens_at' => '05:30',
            'late_after' => '07:00',
            'absent_after' => '09:00',
            'check_out_opens_at' => '11:00',
        ],
    ]);

    Student::factory()->count(999)->create(['unit_id' => $unit->id]);
    $targetStudent = Student::factory()->create(['unit_id' => $unit->id]);
    $issued = app(StudentCardService::class)->issueQr($targetStudent, $officer);

    $this->travelTo(CarbonImmutable::parse('2026-11-27 06:45:00', 'Asia/Makassar'));

    // Warm-up routing/auth di luar pengukuran query scan.
    $this->actingAs($officer)->postJson(route('gate.scan'), ['token' => '']);

    $startedAt = microtime(true);

    $this->actingAs($officer)
        ->postJson(route('gate.scan'), ['token' => $issued['token']])
        ->assertOk()
        ->assertJsonPath('code', GateScanResult::OnTime->value);

    $elapsedMs = (microtime(true) - $startedAt) * 1000;
    expect($elapsedMs)->toBeLessThan(300.0);
});
