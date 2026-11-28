<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\User;

class ReportCardPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::StudentsView->value);
    }

    public function manage(User $user, Student $student, int $academicYearId): bool
    {
        if ($user->hasRole(Role::Admin->value)) {
            return true;
        }

        if (! $user->hasRole(Role::Teacher->value)) {
            return false;
        }

        return Enrollment::query()
            ->where('student_id', $student->id)
            ->where('academic_year_id', $academicYearId)
            ->whereHas('classroom', fn ($q) => $q->where('homeroom_teacher_id', $user->id))
            ->exists();
    }
}
