<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\User;
use App\Services\Students\StudentProfileService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Profil siswa terpadu (mockup 03): identitas, wali, dan ringkasan semua modul dalam tab.
 */
class StudentProfileController extends Controller
{
    public function __invoke(Request $request, Student $student, StudentProfileService $profile): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return Inertia::render('admin/students/show', [
            ...$profile->build($student),
            'can' => [
                'manage' => $user->can(Permission::StudentsManage->value),
            ],
        ]);
    }
}
