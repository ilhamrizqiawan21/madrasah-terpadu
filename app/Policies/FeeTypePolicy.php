<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\FeeType;
use App\Models\User;

class FeeTypePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::FeeTypesManage->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::FeeTypesManage->value);
    }

    public function update(User $user, FeeType $feeType): bool
    {
        return $user->can(Permission::FeeTypesManage->value);
    }

    public function toggle(User $user, FeeType $feeType): bool
    {
        return $user->can(Permission::FeeTypesManage->value);
    }

    public function delete(User $user, FeeType $feeType): bool
    {
        return $user->can(Permission::FeeTypesManage->value);
    }
}
