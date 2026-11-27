<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\DisciplineFollowup;
use App\Models\User;

class DisciplineFollowupPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::BehaviorView->value);
    }

    public function complete(User $user, DisciplineFollowup $followup): bool
    {
        return $user->can(Permission::ThresholdsManage->value)
            || $user->can(Permission::BehaviorVoid->value);
    }
}
