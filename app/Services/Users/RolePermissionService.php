<?php

declare(strict_types=1);

namespace App\Services\Users;

use App\Enums\Permission;
use App\Enums\Role as RoleEnum;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\PermissionCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Mengatur izin per peran dari halaman Peran & Hak Akses (alur aplikasi lama).
 * Admin selalu memegang semua izin dan wali tidak memakai izin staf, jadi keduanya tidak dapat diubah.
 */
final class RolePermissionService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @return list<RoleEnum>
     */
    public static function editableRoles(): array
    {
        return array_values(array_filter(
            RoleEnum::cases(),
            static fn (RoleEnum $role): bool => ! in_array($role, [RoleEnum::Admin, RoleEnum::Guardian], true),
        ));
    }

    /**
     * @param  list<string>  $permissions
     * @return list<string> izin yang tersimpan
     */
    public function update(RoleEnum $role, array $permissions, User $actor): array
    {
        if (! in_array($role, self::editableRoles(), true)) {
            throw ValidationException::withMessages(['role' => 'Izin peran ini tidak dapat diubah.']);
        }

        $valid = array_map(static fn (Permission $p): string => $p->value, Permission::cases());
        $unknown = array_diff($permissions, $valid);
        if ($unknown !== []) {
            throw ValidationException::withMessages(['permissions' => 'Ada izin yang tidak dikenal.']);
        }

        // "Kelola" tanpa "Lihat" membuat halaman daftar menolak akses; lengkapi otomatis.
        $implied = PermissionCatalog::impliedViews();
        foreach ($permissions as $permission) {
            if (isset($implied[$permission])) {
                $permissions[] = $implied[$permission];
            }
        }
        $permissions = array_values(array_unique($permissions));
        sort($permissions);

        return DB::transaction(function () use ($role, $permissions, $actor): array {
            $model = Role::query()->where('name', $role->value)->where('guard_name', 'web')->firstOrFail();
            $before = $model->permissions()->orderBy('name')->pluck('name')->all();

            $model->syncPermissions($permissions);
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            $this->audit->record('role.permissions_updated', $model, ['permissions' => $before], [
                'role' => $role->value,
                'permissions' => $permissions,
            ], $actor->id);

            return $permissions;
        });
    }
}
