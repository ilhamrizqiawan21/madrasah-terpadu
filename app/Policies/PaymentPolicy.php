<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;

class PaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::PaymentsReceive->value)
            || $user->can(Permission::InvoicesView->value);
    }

    public function receive(User $user): bool
    {
        return $user->can(Permission::PaymentsReceive->value);
    }

    public function reverse(User $user): bool
    {
        return $user->can(Permission::PaymentsReverse->value);
    }
}
