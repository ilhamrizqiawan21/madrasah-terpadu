<?php

declare(strict_types=1);

use App\Enums\Feature;
use App\Enums\Role as RoleEnum;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\LessonPeriod;
use App\Models\LessonSchedule;
use App\Models\Subject;
use App\Models\Unit;
use App\Models\User;
use App\Services\Features\FeatureFlags;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->admin = User::factory()->create();
    $this->admin->assignRole(RoleEnum::Admin->value);
    $this->unit = Unit::factory()->create(['code' => 'MI']);
    $this->year = AcademicYear::factory()->active()->create();
    $this->a = Classroom::factory()->for($this->unit)->for($this->year)->create(['grade' => '1', 'name' => '1-A']);
    $this->b = Classroom::factory()->for($this->unit)->for($this->year)->create(['grade' => '1', 'name' => '1-B']);
    $this->subject = Subject::factory()->for($this->unit)->create(['code' => 'MTK', 'name' => 'Matematika']);
    $this->teacher = User::factory()->create();
    $this->teacher->assignRole(RoleEnum::Teacher->value);
    $this->period = LessonPeriod::query()->create(['unit_id' => $this->unit->id, 'name' => 'JP 1', 'starts_at' => '07:30', 'ends_at' => '08:05', 'sort_order' => 1, 'is_break' => false]);
});

test('slot jam divalidasi dan fitur nonaktif menutup menu jadwal', function (): void {
    $this->actingAs($this->admin)->post(route('admin.lesson-periods.store'), [
        'unit_id' => $this->unit->id, 'name' => 'JP 2', 'starts_at' => '09:00', 'ends_at' => '08:00', 'sort_order' => 2, 'is_break' => '0',
    ])->assertSessionHasErrors('ends_at');

    app(FeatureFlags::class)->update([Feature::LessonSchedule->value => false], $this->admin);
    $this->actingAs($this->admin)->get(route('admin.lesson-schedules.index'))->assertNotFound();
});

test('plot jadwal, kelas paralel, dan bentrok guru', function (): void {
    $payload = fn (Classroom $c, array $extra = []) => [
        'classroom_id' => $c->id, 'weekday' => 1, 'lesson_period_id' => $this->period->id,
        'subject_id' => $this->subject->id, 'teacher_id' => $this->teacher->id, ...$extra,
    ];

    // Gabung paralel: satu guru mengajar 1-A dan 1-B bersamaan.
    $this->actingAs($this->admin)->post(route('admin.lesson-schedules.plot'), $payload($this->a, ['parallel_ids' => [$this->b->id]]))
        ->assertSessionHasNoErrors();
    expect(LessonSchedule::query()->count())->toBe(2);

    // Rombel lain tanpa digabung → bentrok guru.
    $c = Classroom::factory()->for($this->unit)->for($this->year)->create(['grade' => '2', 'name' => '2-A']);
    $this->actingAs($this->admin)->post(route('admin.lesson-schedules.plot'), $payload($c))
        ->assertSessionHasErrors('teacher_id');

    $this->actingAs($this->admin)->get(route('admin.lesson-schedules.index', ['unit' => $this->unit->id, 'classroom' => $this->a->id]))
        ->assertInertia(fn (Assert $page) => $page->component('admin/academic/lesson-schedule')
            ->where('cells.0.subject', 'Matematika')->has('periods', 1));

    $this->actingAs($this->admin)->post(route('admin.lesson-schedules.clear'), ['classroom_id' => $this->a->id, 'weekday' => 1, 'lesson_period_id' => $this->period->id])
        ->assertSessionHasNoErrors();
    expect(LessonSchedule::query()->count())->toBe(1);

    // Slot yang masih dipakai tidak bisa dihapus.
    $this->actingAs($this->admin)->delete(route('admin.lesson-periods.destroy', $this->period))->assertSessionHasErrors('master');
});
