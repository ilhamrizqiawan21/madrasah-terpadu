<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\ExamPeriod;
use App\Models\User;

class ExamPeriodPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::InvoicesView->value);
    }

    public function view(User $user, ExamPeriod $examPeriod): bool
    {
        return $user->can(Permission::InvoicesView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::InvoicesIssue->value);
    }

    public function update(User $user, ExamPeriod $examPeriod): bool
    {
        return $user->can(Permission::InvoicesIssue->value);
    }

    public function changeStatus(User $user, ExamPeriod $examPeriod): bool
    {
        return $user->can(Permission::InvoicesIssue->value);
    }
}
