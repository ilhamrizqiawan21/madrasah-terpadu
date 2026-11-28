<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\StudentDiscount;
use App\Models\User;

class StudentDiscountPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::FeeTypesManage->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::FeeTypesManage->value);
    }

    public function update(User $user, StudentDiscount $discount): bool
    {
        return $user->can(Permission::FeeTypesManage->value);
    }

    public function toggle(User $user, StudentDiscount $discount): bool
    {
        return $user->can(Permission::FeeTypesManage->value);
    }
}
