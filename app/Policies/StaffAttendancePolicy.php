<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;

class StaffAttendancePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::UsersManage->value);
    }

    public function manage(User $user): bool
    {
        return $user->can(Permission::UsersManage->value);
    }
}
