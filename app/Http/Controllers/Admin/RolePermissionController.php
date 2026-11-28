<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\Role as RoleEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Users\UpdateRolePermissionsRequest;
use App\Models\User;
use App\Services\Users\RolePermissionService;
use App\Support\PermissionCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

/**
 * Peran & Hak Akses (Pengguna > Peran & Hak Akses): pilih peran → centang izin per fitur → simpan.
 */
class RolePermissionController extends Controller
{
    public function __construct(private readonly RolePermissionService $service) {}

    public function index(Request $request): Response
    {
        $roles = RolePermissionService::editableRoles();
        $selected = RoleEnum::tryFrom($request->string('peran')->toString());
        $selected = $selected !== null && in_array($selected, $roles, true) ? $selected : $roles[0];

        $granted = Role::findOrCreate($selected->value, 'web')->permissions()->orderBy('name')->pluck('name')->all();

        return Inertia::render('admin/users/roles', [
            'roles' => array_map(static fn (RoleEnum $r): array => ['value' => $r->value, 'label' => $r->label()], $roles),
            'selectedRole' => $selected->value,
            'granted' => $granted,
            'groups' => array_map(static fn (array $group): array => [
                'label' => $group['label'],
                'rows' => array_map(static fn (array $row): array => [
                    'label' => $row['label'],
                    'view' => $row['view']?->value,
                    'manage' => $row['manage']?->value,
                ], $group['rows']),
            ], PermissionCatalog::groups()),
        ]);
    }

    public function update(UpdateRolePermissionsRequest $request, string $role): RedirectResponse
    {
        $roleEnum = RoleEnum::tryFrom($role);
        abort_if($roleEnum === null, 404);

        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $this->service->update($roleEnum, $request->permissionNames(), $user);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Izin peran {$roleEnum->label()} disimpan."]);

        return to_route('admin.roles.index', ['peran' => $roleEnum->value]);
    }
}
