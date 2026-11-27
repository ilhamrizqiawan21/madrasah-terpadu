<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\MemorizationMaterial;
use App\Models\User;

class MemorizationMaterialPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::QuranManage->value)
            || $user->can(Permission::QuranView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::QuranManage->value);
    }

    public function update(User $user, MemorizationMaterial $material): bool
    {
        return $user->can(Permission::QuranManage->value);
    }

    public function toggle(User $user, MemorizationMaterial $material): bool
    {
        return $user->can(Permission::QuranManage->value);
    }

    public function delete(User $user, MemorizationMaterial $material): bool
    {
        return $user->can(Permission::QuranManage->value);
    }
}
