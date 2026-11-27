<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\DisciplineThreshold;
use App\Models\User;

class DisciplineThresholdPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ThresholdsManage->value)
            || $user->can(Permission::BehaviorView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::ThresholdsManage->value);
    }

    public function update(User $user, DisciplineThreshold $threshold): bool
    {
        return $user->can(Permission::ThresholdsManage->value);
    }

    public function toggle(User $user, DisciplineThreshold $threshold): bool
    {
        return $user->can(Permission::ThresholdsManage->value);
    }
}
