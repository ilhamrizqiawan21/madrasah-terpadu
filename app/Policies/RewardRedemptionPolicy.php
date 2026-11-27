<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\RewardRedemption;
use App\Models\User;

class RewardRedemptionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::BehaviorView->value)
            || $user->can(Permission::RewardsApprove->value);
    }

    public function request(User $user): bool
    {
        return $user->can(Permission::BehaviorRecord->value)
            || $user->can(Permission::RewardsApprove->value);
    }

    public function decide(User $user, RewardRedemption $redemption): bool
    {
        return $user->can(Permission::RewardsApprove->value);
    }
}
