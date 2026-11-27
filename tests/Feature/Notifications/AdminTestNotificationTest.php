<?php

declare(strict_types=1);

use App\Enums\GuardianRelation;
use App\Enums\Role as RoleEnum;
use App\Models\AuditLog;
use App\Models\Guardian;
use App\Models\Student;
use App\Models\User;
use App\Notifications\AdminTestNotification;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Notification;
use NotificationChannels\WebPush\WebPushChannel;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function createAdminActor(): User
{
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole(RoleEnum::Admin->value);

    return $admin;
}

function createGuardianWithChild(bool $active = true): Guardian
{
    $user = User::factory()->create(['is_active' => $active]);
    $user->assignRole(RoleEnum::Guardian->value);

    $guardian = Guardian::factory()->create(['user_id' => $user->id]);
    $student = Student::factory()->create();
    $student->guardians()->attach($guardian->id, [
        'relation' => GuardianRelation::Father->value,
        'is_primary' => true,
    ]);

    return $guardian;
}

test('tamu diarahkan ke login saat mengirim notifikasi uji wali', function (): void {
    $guardian = createGuardianWithChild();

    $this->post(route('admin.guardians.test-notification.send', $guardian))
        ->assertRedirect(route('login'));
});

test('peran selain admin ditolak 403 saat mengirim notifikasi uji wali', function (RoleEnum $role): void {
    $guardian = createGuardianWithChild();
    $actor = User::factory()->create(['is_active' => true]);
    $actor->assignRole($role->value);

    $this->actingAs($actor)
        ->post(route('admin.guardians.test-notification.send', $guardian))
        ->assertForbidden();
})->with([
    'guru' => [RoleEnum::Teacher],
    'bendahara' => [RoleEnum::Treasurer],
    'kepala unit' => [RoleEnum::UnitHead],
    'bk' => [RoleEnum::Counselor],
    'wali' => [RoleEnum::Guardian],
]);

test('admin dapat mengirim notifikasi uji ke wali aktif dan tercatat di audit', function (): void {
    Notification::fake();

    $admin = createAdminActor();
    $guardian = createGuardianWithChild(active: true);

    $this->actingAs($admin)
        ->from(route('admin.guardians.index'))
        ->post(route('admin.guardians.test-notification.send', $guardian))
        ->assertRedirect(route('admin.guardians.index'))
        ->assertSessionHasNoErrors();

    Notification::assertSentTo(
        $guardian->user,
        AdminTestNotification::class,
        function (AdminTestNotification $notification, array $channels): bool {
            return in_array('database', $channels, true)
                && in_array(WebPushChannel::class, $channels, true);
        },
    );

    $audit = AuditLog::query()
        ->where('action', 'guardian_test_notification_sent')
        ->where('subject_id', $guardian->id)
        ->first();

    expect($audit)->not->toBeNull()
        ->and($audit?->user_id)->toBe($admin->id);
});

test('notifikasi uji ditolak bila akun wali nonaktif', function (): void {
    Notification::fake();

    $admin = createAdminActor();
    $guardian = createGuardianWithChild(active: false);

    $this->actingAs($admin)
        ->from(route('admin.guardians.index'))
        ->post(route('admin.guardians.test-notification.send', $guardian))
        ->assertRedirect(route('admin.guardians.index'));

    Notification::assertNothingSent();
});
