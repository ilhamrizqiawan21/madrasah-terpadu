<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\TransferProof;
use App\Models\User;

class TransferProofPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::PaymentsReconcile->value);
    }

    public function review(User $user, TransferProof $proof): bool
    {
        return $user->can(Permission::PaymentsReconcile->value);
    }
}
