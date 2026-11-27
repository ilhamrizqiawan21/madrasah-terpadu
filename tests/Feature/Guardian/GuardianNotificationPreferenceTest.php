<?php

declare(strict_types=1);

use App\Enums\NotificationCategory;
use App\Enums\Role as RoleEnum;
use App\Models\User;
use App\Services\Notifications\NotificationPreferenceService;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('wali dapat mengubah preferensi kategori biasa tetapi billing dan account tetap terkunci aktif', function (): void {
    $wali = User::factory()->create(['is_active' => true]);
    $wali->assignRole(RoleEnum::Guardian->value);

    $this->actingAs($wali)
        ->put(route('guardian.notifications.preferences.update'), [
            'preferences' => [
                'attendance' => false,
                'behavior' => true,
                'billing' => false,
                'account' => false,
            ],
        ])
        ->assertRedirect();

    $service = app(NotificationPreferenceService::class);

    expect($service->isPushEnabled($wali, NotificationCategory::Attendance))->toBeFalse()
        ->and($service->isPushEnabled($wali, NotificationCategory::Behavior))->toBeTrue()
        ->and($service->isPushEnabled($wali, NotificationCategory::Billing))->toBeTrue()
        ->and($service->isPushEnabled($wali, NotificationCategory::Account))->toBeTrue();
});
