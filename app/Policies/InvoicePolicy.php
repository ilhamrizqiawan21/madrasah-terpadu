<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::InvoicesView->value);
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return $user->can(Permission::InvoicesView->value);
    }

    public function issue(User $user): bool
    {
        return $user->can(Permission::InvoicesIssue->value);
    }

    public function adjust(User $user, Invoice $invoice): bool
    {
        return $user->can(Permission::InvoicesAdjust->value);
    }
}
