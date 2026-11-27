<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\MemorizationTarget;
use App\Models\User;

class MemorizationTargetPolicy
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

    public function update(User $user, MemorizationTarget $target): bool
    {
        return $user->can(Permission::QuranManage->value);
    }

    public function toggle(User $user, MemorizationTarget $target): bool
    {
        return $user->can(Permission::QuranManage->value);
    }
}
