<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\PointRule;
use App\Models\User;

class PointRulePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::PointRulesManage->value)
            || $user->can(Permission::BehaviorView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::PointRulesManage->value);
    }

    public function update(User $user, PointRule $pointRule): bool
    {
        return $user->can(Permission::PointRulesManage->value);
    }

    public function toggle(User $user, PointRule $pointRule): bool
    {
        return $user->can(Permission::PointRulesManage->value);
    }
}
