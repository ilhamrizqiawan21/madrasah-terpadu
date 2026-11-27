<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\Permission;
use App\Enums\StudentStatus;
use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\Guardian;
use App\Models\Student;
use App\Models\StudentMutation;
use App\Models\Unit;
use App\Models\User;
use App\Support\SchoolContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Beranda Staf interaktif dengan ringkasan data nyata sesuai konteks unit & tahun ajaran (ADR-027).
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $context = SchoolContext::resolve($request->session());
        $unit = $context['unit'] !== null
            ? Unit::query()->where('code', $context['unit'])->first()
            : null;
        $yearId = $context['academicYearId'];

        $canViewStudents = $user->can(Permission::StudentsView->value);
        $canManageStudents = $user->can(Permission::StudentsManage->value);
        $canManageClassrooms = $user->can(Permission::ClassroomsManage->value);
        $canManageGuardians = $user->can(Permission::GuardiansManage->value);

        $activeStudentsCount = $canViewStudents
            ? Student::query()
                ->where('status', StudentStatus::Active)
                ->when($unit !== null, fn (Builder $q) => $q->where('unit_id', $unit->id))
                ->count()
            : null;

        $classroomsCount = ($canViewStudents || $canManageClassrooms)
            ? Classroom::query()
                ->where('is_active', true)
                ->when($unit !== null, fn (Builder $q) => $q->where('unit_id', $unit->id))
                ->when($yearId !== null, fn (Builder $q) => $q->where('academic_year_id', $yearId))
                ->count()
            : null;

        $guardiansCount = $canManageGuardians
            ? Guardian::query()
                ->when($unit !== null, fn (Builder $q) => $q->whereHas('students', fn (Builder $s) => $s->where('unit_id', $unit->id)))
                ->count()
            : null;

        $recentMutations = $canManageStudents
            ? StudentMutation::query()
                ->with(['student.unit'])
                ->when($unit !== null, fn (Builder $q) => $q->whereHas('student', fn (Builder $s) => $s->where('unit_id', $unit->id)))
                ->orderByDesc('mutated_on')
                ->orderByDesc('id')
                ->limit(5)
                ->get()
                ->map(fn (StudentMutation $mutation): array => [
                    'id' => $mutation->id,
                    'type' => ['value' => $mutation->type->value, 'label' => $mutation->type->label()],
                    'mutated_on' => $mutation->mutated_on->toDateString(),
                    'reason' => $mutation->reason,
                    'student_name' => $mutation->student->name,
                    'student_nis' => $mutation->student->nis,
                    'unit_code' => $mutation->student->unit->code,
                ])
                ->values()
                ->all()
            : [];

        return Inertia::render('admin/dashboard', [
            'summary' => [
                'unit_code' => $unit?->code,
                'unit_name' => $unit?->name,
                'academic_year' => $context['academicYear'],
                'active_students' => $activeStudentsCount,
                'active_classrooms' => $classroomsCount,
                'guardians' => $guardiansCount,
            ],
            'recentMutations' => $recentMutations,
            'permissions' => [
                'view_students' => $canViewStudents,
                'manage_students' => $canManageStudents,
                'manage_classrooms' => $canManageClassrooms,
                'manage_guardians' => $canManageGuardians,
                'import_students' => $canManageStudents && $canManageGuardians,
            ],
        ]);
    }
}
