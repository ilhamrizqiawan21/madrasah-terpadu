<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\StudentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StudentPromotionRequest;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\Unit;
use App\Models\User;
use App\Services\Students\StudentPromotionService;
use App\Support\SchoolContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

/**
 * Kenaikan kelas dan kelulusan massal per rombel (T-212).
 */
class StudentPromotionController extends Controller
{
    public function __construct(private readonly StudentPromotionService $service) {}

    public function index(Request $request): Response
    {
        $context = SchoolContext::resolve($request->session());
        $units = Unit::query()->where('is_active', true)->orderBy('id')->get(['id', 'code', 'name']);
        $years = AcademicYear::query()->orderByDesc('starts_on')->orderByDesc('id')->get();

        $unitId = $request->integer('unit_id')
            ?: ($units->firstWhere('code', $context['unit'])->id ?? $units->first()->id ?? null);
        $sourceYearId = $request->integer('source_year_id') ?: ($years->firstWhere('is_active', false)->id ?? $years->first()->id ?? null);
        $targetYearId = $request->integer('target_year_id') ?: ($years->firstWhere('is_active', true)->id ?? $years->first()->id ?? null);

        $sourceClassrooms = $unitId && $sourceYearId
            ? Classroom::query()
                ->where('unit_id', $unitId)
                ->where('academic_year_id', $sourceYearId)
                ->orderBy('grade')
                ->orderBy('name')
                ->get(['id', 'grade', 'name', 'is_active'])
            : collect();

        $targetClassrooms = $unitId && $targetYearId
            ? Classroom::query()
                ->where('unit_id', $unitId)
                ->where('academic_year_id', $targetYearId)
                ->where('is_active', true)
                ->orderBy('grade')
                ->orderBy('name')
                ->get(['id', 'grade', 'name'])
            : collect();

        $sourceClassroomId = $request->integer('source_classroom_id') ?: $sourceClassrooms->first()?->id;
        $targetClassroomId = $request->integer('target_classroom_id') ?: $targetClassrooms->first()?->id;

        $candidates = [];

        if ($sourceYearId && $sourceClassroomId) {
            $existingTargetStudentIds = $targetYearId
                ? Enrollment::query()->where('academic_year_id', $targetYearId)->pluck('student_id')->flip()->all()
                : [];

            $enrollments = Enrollment::query()
                ->whereHas('student', fn ($q) => $q->where('status', StudentStatus::Active))
                ->with('student')
                ->where('academic_year_id', $sourceYearId)
                ->where('classroom_id', $sourceClassroomId)
                ->get()
                ->sortBy(fn (Enrollment $enrollment): string => $enrollment->student->name)
                ->values();

            foreach ($enrollments as $enrollment) {
                $student = $enrollment->student;

                $candidates[] = [
                    'id' => $student->id,
                    'nis' => $student->nis,
                    'name' => $student->name,
                    'already_enrolled' => isset($existingTargetStudentIds[$student->id]),
                ];
            }
        }

        return Inertia::render('admin/students/promotions', [
            'filters' => [
                'unit_id' => $unitId,
                'source_year_id' => $sourceYearId,
                'source_classroom_id' => $sourceClassroomId,
                'target_year_id' => $targetYearId,
                'target_classroom_id' => $targetClassroomId,
            ],
            'unitOptions' => $units->map(fn (Unit $unit): array => [
                'id' => $unit->id,
                'code' => $unit->code,
                'name' => $unit->name,
            ])->values(),
            'yearOptions' => $years->map(fn (AcademicYear $year): array => [
                'id' => $year->id,
                'label' => "{$year->name} {$year->semester->label()}",
                'is_active' => $year->is_active,
            ])->values(),
            'sourceClassroomOptions' => $sourceClassrooms->map(fn (Classroom $c): array => [
                'id' => $c->id,
                'name' => $c->name,
            ])->values(),
            'targetClassroomOptions' => $targetClassrooms->map(fn (Classroom $c): array => [
                'id' => $c->id,
                'name' => $c->name,
            ])->values(),
            'candidates' => $candidates,
        ]);
    }

    public function store(StudentPromotionRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        try {
            $summary = $this->service->execute(
                unitId: (int) $data['unit_id'],
                sourceYearId: (int) $data['source_year_id'],
                sourceClassroomId: (int) $data['source_classroom_id'],
                targetYearId: (int) $data['target_year_id'],
                mode: (string) $data['mode'],
                targetClassroomId: isset($data['target_classroom_id']) ? (int) $data['target_classroom_id'] : null,
                studentIds: array_values(array_map('intval', $data['student_ids'])),
                actor: $actor,
            );
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages(['target_classroom_id' => $exception->getMessage()]);
        }

        $message = $data['mode'] === StudentPromotionService::MODE_GRADUATE
            ? "{$summary['graduated']} siswa diluluskan."
            : "{$summary['promoted']} siswa dinaikkan ke rombel tujuan.";

        if ($summary['skipped'] > 0) {
            $message .= " {$summary['skipped']} siswa dilewati karena sudah terdaftar.";
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return to_route('admin.students.promotions.index', [
            'unit_id' => $data['unit_id'],
            'source_year_id' => $data['source_year_id'],
            'source_classroom_id' => $data['source_classroom_id'],
            'target_year_id' => $data['target_year_id'],
            'target_classroom_id' => $data['target_classroom_id'] ?? null,
        ]);
    }
}
