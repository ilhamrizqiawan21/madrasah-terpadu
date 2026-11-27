<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\BehaviorRecord;
use App\Models\User;

class BehaviorRecordPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::BehaviorView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::BehaviorRecord->value);
    }

    public function void(User $user, BehaviorRecord $record): bool
    {
        return $user->can(Permission::BehaviorVoid->value);
    }

    public function viewEvidence(User $user, BehaviorRecord $record): bool
    {
        return $user->can(Permission::BehaviorView->value);
    }
}
