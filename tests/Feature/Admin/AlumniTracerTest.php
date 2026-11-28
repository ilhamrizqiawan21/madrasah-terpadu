<?php

declare(strict_types=1);

use App\Enums\AlumniActivity;
use App\Enums\Role as RoleEnum;
use App\Enums\StudentStatus;
use App\Models\AlumniTracerResponse;
use App\Models\Student;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->admin = User::factory()->create();
    $this->admin->assignRole(RoleEnum::Admin->value);
    $unit = Unit::factory()->create(['code' => 'MI']);
    $this->alumnus = Student::factory()->create(['unit_id' => $unit->id, 'name' => 'Alumni Satu', 'status' => StudentStatus::Graduated]);
    $this->active = Student::factory()->create(['unit_id' => $unit->id, 'status' => StudentStatus::Active]);
});

function tracerPayload(int $studentId, array $overrides = []): array
{
    return array_merge([
        'student_id' => $studentId, 'graduation_year' => 2024, 'activity' => 'study',
        'institution' => 'MTs Negeri 1 Kolaka', 'program' => 'Reguler', 'income_range' => null,
        'relevance' => 'very', 'feedback' => 'Pertahankan program tahfidz.',
    ], $overrides);
}

test('hanya pengelola siswa yang membuka data dan survei alumni', function (): void {
    $teacher = User::factory()->create();
    $teacher->assignRole('guru');
    $this->actingAs($teacher)->get(route('admin.alumni.index'))->assertForbidden();
    $this->actingAs($teacher)->post(route('admin.alumni.store'), tracerPayload($this->alumnus->id))->assertForbidden();
});

test('kuesioner alumni tersimpan sekali per alumni, diperbarui, dan direkap per status', function (): void {
    $this->actingAs($this->admin)->get(route('admin.alumni.create'))
        ->assertInertia(fn (Assert $page) => $page->component('admin/students/alumni-survey')
            ->has('alumni', 1)->where('alumni.0.id', $this->alumnus->id));

    $this->actingAs($this->admin)->post(route('admin.alumni.store'), tracerPayload($this->alumnus->id))
        ->assertRedirect(route('admin.alumni.index'));
    $this->actingAs($this->admin)->post(route('admin.alumni.store'), tracerPayload($this->alumnus->id, ['activity' => 'employed', 'income_range' => 'below_3m']))
        ->assertSessionHasNoErrors();

    $response = AlumniTracerResponse::query()->sole();
    expect($response->activity)->toBe(AlumniActivity::Employed);

    $this->actingAs($this->admin)->get(route('admin.alumni.index', ['activity' => 'employed']))
        ->assertInertia(fn (Assert $page) => $page->component('admin/students/alumni')
            ->has('responses.data', 1)
            ->where('responses.data.0.student_name', 'Alumni Satu')
            ->where('summary', fn ($summary) => collect($summary)->firstWhere('value', 'employed')['total'] === 1));

    $this->actingAs($this->admin)->delete(route('admin.alumni.destroy', $response))->assertRedirect();
    expect(AlumniTracerResponse::query()->count())->toBe(0);
    $this->assertDatabaseHas('audit_logs', ['action' => 'alumni_tracer.deleted']);
});

test('siswa yang belum lulus ditolak dan isian divalidasi', function (): void {
    $this->actingAs($this->admin)->post(route('admin.alumni.store'), tracerPayload($this->active->id))
        ->assertSessionHasErrors('student_id');
    $this->actingAs($this->admin)->post(route('admin.alumni.store'), tracerPayload($this->alumnus->id, ['activity' => 'aneh', 'relevance' => '']))
        ->assertSessionHasErrors(['activity', 'relevance']);
});
