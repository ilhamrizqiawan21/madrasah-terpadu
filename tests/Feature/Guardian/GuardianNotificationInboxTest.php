<?php

declare(strict_types=1);

use App\Enums\Role as RoleEnum;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function createInboxGuardian(): User
{
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole(RoleEnum::Guardian->value);

    return $user;
}

function seedNotification(User $user, string $title): DatabaseNotification
{
    return DatabaseNotification::query()->create([
        'id' => (string) Str::uuid(),
        'type' => 'App\\Notifications\\SampleNotification',
        'notifiable_type' => User::class,
        'notifiable_id' => $user->id,
        'data' => [
            'title' => $title,
            'body' => 'Isi pesan',
            'url' => '/wali',
            'category' => 'attendance',
        ],
        'read_at' => null,
    ]);
}

test('wali hanya melihat notifikasinya sendiri dan tidak dapat menandai milik wali lain', function (): void {
    $waliA = createInboxGuardian();
    $waliB = createInboxGuardian();

    $notifA = seedNotification($waliA, 'Notif Wali A');
    $notifB = seedNotification($waliB, 'Notif Wali B');

    $this->actingAs($waliA)
        ->get(route('guardian.notifications.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('guardian/notifications')
            ->has('notifications.data', 1)
            ->where('notifications.data.0.title', 'Notif Wali A')
            ->where('guardianContext.unreadNotifications', 1)
        );

    $this->actingAs($waliA)
        ->patch(route('guardian.notifications.read', $notifB->id))
        ->assertNotFound();

    $this->actingAs($waliA)
        ->patch(route('guardian.notifications.read', $notifA->id))
        ->assertRedirect();

    expect($notifA->fresh()?->read_at)->not->toBeNull();
});

test('wali dapat menandai semua notifikasi sebagai sudah dibaca', function (): void {
    $wali = createInboxGuardian();
    seedNotification($wali, 'Notif 1');
    seedNotification($wali, 'Notif 2');

    $this->actingAs($wali)
        ->post(route('guardian.notifications.read-all'))
        ->assertRedirect();

    expect($wali->unreadNotifications()->count())->toBe(0);
});
