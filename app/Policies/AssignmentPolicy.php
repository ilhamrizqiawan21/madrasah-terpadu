<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\Assignment;
use App\Models\User;

class AssignmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::StudentsView->value);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole([Role::Admin->value, Role::Teacher->value]);
    }

    public function update(User $user, Assignment $assignment): bool
    {
        if ($user->hasRole(Role::Admin->value)) {
            return true;
        }

        return $user->hasRole(Role::Teacher->value) && $assignment->teacher_id === $user->id;
    }

    public function delete(User $user, Assignment $assignment): bool
    {
        return $this->update($user, $assignment);
    }

    public function download(User $user, Assignment $assignment): bool
    {
        return $user->can(Permission::StudentsView->value);
    }
}
