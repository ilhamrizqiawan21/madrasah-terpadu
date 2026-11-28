<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Role;
use App\Models\ExamEligibilityOverride;
use App\Models\User;

class ExamEligibilityOverridePolicy
{
    public function grant(User $user): bool
    {
        return $user->hasRole(Role::Admin->value);
    }

    public function revoke(User $user, ExamEligibilityOverride $override): bool
    {
        return $user->hasRole(Role::Admin->value);
    }
}
