<?php

declare(strict_types=1);

use App\Enums\Permission as PermissionEnum;
use App\Enums\Role as RoleEnum;
use Database\Seeders\RolesAndPermissionsSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

test('seeder peran dan izin bersifat idempoten', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(RolesAndPermissionsSeeder::class);

    expect(Role::query()->count())->toBe(count(RoleEnum::cases()))
        ->and(Permission::query()->count())->toBe(count(PermissionEnum::cases()));
});

test('setiap peran memiliki izin yang sesuai dengan PRD', function (
    RoleEnum $roleEnum,
    array $expectedPermissions,
    array $forbiddenPermissions,
): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    $role = Role::findByName($roleEnum->value, 'web');

    foreach ($expectedPermissions as $permission) {
        expect($role->hasPermissionTo($permission->value))->toBeTrue();
    }

    foreach ($forbiddenPermissions as $permission) {
        expect($role->hasPermissionTo($permission->value))->toBeFalse();
    }
})->with([
    'admin' => [
        RoleEnum::Admin,
        [PermissionEnum::SettingsManage, PermissionEnum::UsersManage, PermissionEnum::PaymentsReverse, PermissionEnum::AuditView],
        [],
    ],
    'bendahara' => [
        RoleEnum::Treasurer,
        [PermissionEnum::InvoicesIssue, PermissionEnum::PaymentsReceive, PermissionEnum::PaymentsReconcile, PermissionEnum::WalletManage],
        [PermissionEnum::SettingsManage, PermissionEnum::BehaviorVoid, PermissionEnum::AttitudeApprove],
    ],
    'guru' => [
        RoleEnum::Teacher,
        [PermissionEnum::HabitsVerify, PermissionEnum::QuranRecord, PermissionEnum::BehaviorRecord, PermissionEnum::AttitudeLock],
        [PermissionEnum::InvoicesIssue, PermissionEnum::PaymentsReceive, PermissionEnum::BehaviorVoid, PermissionEnum::SettingsManage],
    ],
    'bk' => [
        RoleEnum::Counselor,
        [PermissionEnum::BehaviorRecord, PermissionEnum::BehaviorVoid, PermissionEnum::PointRulesManage, PermissionEnum::RewardsApprove],
        [PermissionEnum::InvoicesIssue, PermissionEnum::PaymentsReceive, PermissionEnum::SettingsManage],
    ],
    'kepala unit' => [
        RoleEnum::UnitHead,
        [PermissionEnum::StudentsView, PermissionEnum::AttitudeApprove, PermissionEnum::ReportsView],
        [PermissionEnum::SettingsManage, PermissionEnum::PaymentsReceive, PermissionEnum::BehaviorVoid],
    ],
    'petugas gerbang' => [
        RoleEnum::GateOfficer,
        [PermissionEnum::AttendanceScan],
        [PermissionEnum::StudentsManage, PermissionEnum::InvoicesIssue, PermissionEnum::CanteenPos],
    ],
    'kasir kantin' => [
        RoleEnum::CanteenCashier,
        [PermissionEnum::CanteenPos, PermissionEnum::WalletTopup],
        [PermissionEnum::AttendanceScan, PermissionEnum::InvoicesIssue, PermissionEnum::SettingsManage],
    ],
    'wali' => [
        RoleEnum::Guardian,
        [],
        [PermissionEnum::StudentsView, PermissionEnum::InvoicesIssue, PermissionEnum::AttendanceScan, PermissionEnum::CanteenPos],
    ],
]);
