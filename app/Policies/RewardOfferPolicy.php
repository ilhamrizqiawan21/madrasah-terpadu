<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\RewardOffer;
use App\Models\User;

class RewardOfferPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::BehaviorView->value)
            || $user->can(Permission::RewardsApprove->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::RewardsApprove->value);
    }

    public function update(User $user, RewardOffer $offer): bool
    {
        return $user->can(Permission::RewardsApprove->value);
    }

    public function toggle(User $user, RewardOffer $offer): bool
    {
        return $user->can(Permission::RewardsApprove->value);
    }
}
