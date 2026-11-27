<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Permission as PermissionEnum;
use App\Enums\Role as RoleEnum;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (PermissionEnum::cases() as $permission) {
            Permission::findOrCreate($permission->value, 'web');
        }

        foreach ($this->rolePermissions() as $roleValue => $permissions) {
            $role = Role::findOrCreate($roleValue, 'web');
            $role->syncPermissions(array_map(
                static fn (PermissionEnum $permission): string => $permission->value,
                $permissions,
            ));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * @return array<string, list<PermissionEnum>>
     */
    private function rolePermissions(): array
    {
        return [
            RoleEnum::Admin->value => PermissionEnum::cases(),
            RoleEnum::Treasurer->value => [
                PermissionEnum::StudentsView,
                PermissionEnum::FeeTypesManage,
                PermissionEnum::InvoicesView,
                PermissionEnum::InvoicesIssue,
                PermissionEnum::InvoicesAdjust,
                PermissionEnum::PaymentsReceive,
                PermissionEnum::PaymentsReverse,
                PermissionEnum::PaymentsReconcile,
                PermissionEnum::CanteenPos,
                PermissionEnum::WalletTopup,
                PermissionEnum::WalletManage,
                PermissionEnum::ReportsView,
            ],
            RoleEnum::Teacher->value => [
                PermissionEnum::StudentsView,
                PermissionEnum::AttendanceView,
                PermissionEnum::AttendanceManage,
                PermissionEnum::BehaviorView,
                PermissionEnum::BehaviorRecord,
                PermissionEnum::HabitsVerify,
                PermissionEnum::QuranView,
                PermissionEnum::QuranRecord,
                PermissionEnum::AttitudeView,
                PermissionEnum::AttitudeLock,
                PermissionEnum::CbtView,
                PermissionEnum::CbtTeach,
            ],
            RoleEnum::Counselor->value => [
                PermissionEnum::StudentsView,
                PermissionEnum::AttendanceView,
                PermissionEnum::BehaviorView,
                PermissionEnum::BehaviorRecord,
                PermissionEnum::BehaviorVoid,
                PermissionEnum::PointRulesManage,
                PermissionEnum::ThresholdsManage,
                PermissionEnum::RewardsApprove,
                PermissionEnum::ReportsView,
            ],
            RoleEnum::UnitHead->value => [
                PermissionEnum::StudentsView,
                PermissionEnum::AttendanceView,
                PermissionEnum::InvoicesView,
                PermissionEnum::BehaviorView,
                PermissionEnum::QuranView,
                PermissionEnum::AttitudeView,
                PermissionEnum::AttitudeApprove,
                PermissionEnum::CbtView,
                PermissionEnum::ReportsView,
            ],
            RoleEnum::GateOfficer->value => [
                PermissionEnum::AttendanceScan,
            ],
            RoleEnum::CanteenCashier->value => [
                PermissionEnum::CanteenPos,
                PermissionEnum::WalletTopup,
            ],
            RoleEnum::Guardian->value => [],
        ];
    }
}
