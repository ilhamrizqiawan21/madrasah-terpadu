<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Guardian;
use App\Models\User;

/**
 * Pengelolaan wali (T-206). Aturan akun staf bersama ada di GuardianService.
 */
class GuardianPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can(Permission::GuardiansManage->value);
    }

    public function create(User $actor): bool
    {
        return $actor->can(Permission::GuardiansManage->value);
    }

    public function update(User $actor, Guardian $guardian): bool
    {
        return $actor->can(Permission::GuardiansManage->value);
    }
}
