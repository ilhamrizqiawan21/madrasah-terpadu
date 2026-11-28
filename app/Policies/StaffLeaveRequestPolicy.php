<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Role;
use App\Models\User;

class StaffLeaveRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(array_map(fn (Role $r): string => $r->value, Role::adminArea()));
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(array_map(fn (Role $r): string => $r->value, Role::adminArea()));
    }

    public function decide(User $user): bool
    {
        return $user->hasAnyRole([Role::Admin->value, Role::UnitHead->value]);
    }
}
