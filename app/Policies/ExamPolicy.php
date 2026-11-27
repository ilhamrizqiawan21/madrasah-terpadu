<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\Exam;
use App\Models\TeachingAssignment;
use App\Models\User;

class ExamPolicy
{
    public function view(User $user, Exam $exam): bool
    {
        if ($user->can(Permission::CbtManage->value) || $user->hasRole(Role::UnitHead->value)) {
            return true;
        }

        if (! $user->can(Permission::CbtView->value)) {
            return false;
        }

        return $exam->created_by === $user->id
            || TeachingAssignment::query()
                ->where('user_id', $user->id)
                ->where('subject_id', $exam->subject_id)
                ->whereIn('classroom_id', $exam->classroom_ids)
                ->exists();
    }

    public function update(User $user, Exam $exam): bool
    {
        if ($user->can(Permission::CbtManage->value)) {
            return true;
        }

        if (! $user->can(Permission::CbtTeach->value)) {
            return false;
        }

        return $exam->created_by === $user->id;
    }
}
