<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;

/**
 * Pengelolaan akun staf (T-114). Aturan pengaman (diri sendiri, admin terakhir)
 * ada di StaffUserService agar berlaku juga di luar HTTP.
 */
class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can(Permission::UsersManage->value);
    }

    public function create(User $actor): bool
    {
        return $actor->can(Permission::UsersManage->value);
    }

    public function update(User $actor, User $target): bool
    {
        return $actor->can(Permission::UsersManage->value);
    }

    public function updateStatus(User $actor, User $target): bool
    {
        return $actor->can(Permission::UsersManage->value);
    }

    public function resetPassword(User $actor, User $target): bool
    {
        return $actor->can(Permission::UsersManage->value);
    }
}
