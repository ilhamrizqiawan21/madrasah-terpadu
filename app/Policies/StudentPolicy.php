<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Student;
use App\Models\User;

class StudentPolicy
{
    /**
     * Staf berizin lihat siswa, atau wali yang terhubung dengan siswa itu.
     * Wali lain tidak boleh tahu siswa itu ada: pemanggil menjawab 404.
     */
    public function view(User $user, Student $student): bool
    {
        if ($user->can(Permission::StudentsView->value)) {
            return true;
        }

        return $user->guardian?->students()->whereKey($student->id)->exists() === true;
    }

    public function update(User $user, Student $student): bool
    {
        return $user->can(Permission::StudentsManage->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::StudentsManage->value);
    }
}
