<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('authorizes audit.view and filters audit logs with before and after payloads', function (): void {
    $admin = User::factory()->create(['name' => 'Admin Audit']);
    $admin->assignRole(Role::Admin->value);

    $teacher = User::factory()->create(['name' => 'Guru Biasa']);
    $teacher->assignRole(Role::Teacher->value);

    AuditLog::query()->create([
        'user_id' => $admin->id,
        'action' => 'student.updated',
        'subject_type' => 'App\\Models\\Student',
        'subject_id' => 10,
        'before' => ['name' => 'Lama'],
        'after' => ['name' => 'Baru'],
        'ip' => '127.0.0.1',
        'created_at' => '2026-11-27 10:00:00',
    ]);

    AuditLog::query()->create([
        'user_id' => $teacher->id,
        'action' => 'login_failed',
        'subject_type' => 'App\\Models\\User',
        'subject_id' => $teacher->id,
        'before' => null,
        'after' => ['username' => 'guru'],
        'ip' => '127.0.0.2',
        'created_at' => '2026-11-26 09:00:00',
    ]);

    $this->actingAs($teacher)->get(route('admin.reports.audit.index'))->assertForbidden();

    $this->actingAs($admin)
        ->get(route('admin.reports.audit.index', [
            'user_id' => $admin->id,
            'action' => 'student.updated',
            'date_from' => '2026-11-27',
            'date_to' => '2026-11-27',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/reports/audit')
            ->has('logs.data', 1)
            ->where('logs.data.0.action', 'student.updated')
            ->where('logs.data.0.before.name', 'Lama')
            ->where('logs.data.0.after.name', 'Baru')
        );
});
