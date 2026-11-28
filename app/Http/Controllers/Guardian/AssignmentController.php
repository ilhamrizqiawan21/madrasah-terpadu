<?php

declare(strict_types=1);

namespace App\Http\Controllers\Guardian;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Assignment;
use App\Models\Enrollment;
use App\Models\Student;
use App\Support\AppTimezone;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Daftar Tugas & PR anak di portal wali (baca saja, T-1603).
 */
class AssignmentController extends Controller
{
    public function index(Student $student): Response
    {
        $activeYearId = AcademicYear::query()->where('is_active', true)->value('id');
        $enrollment = $activeYearId !== null
            ? Enrollment::query()
                ->with('classroom:id,name')
                ->where('student_id', $student->id)
                ->where('academic_year_id', $activeYearId)
                ->first()
            : null;

        $assignments = $enrollment !== null
            ? Assignment::query()
                ->with(['subject:id,name', 'teacher:id,name'])
                ->where('classroom_id', $enrollment->classroom_id)
                ->orderByDesc('due_at')
                ->orderByDesc('id')
                ->limit(30)
                ->get()
                ->map(fn (Assignment $a): array => [
                    'id' => $a->id,
                    'subject_name' => $a->subject->name,
                    'teacher_name' => $a->teacher->name,
                    'title' => $a->title,
                    'description' => $a->description,
                    'due_at' => $a->due_at->setTimezone(AppTimezone::name())->toIso8601String(),
                    'has_attachment' => $a->attachment_path !== null,
                ])
                ->values()
                ->all()
            : [];

        return Inertia::render('guardian/assignments', [
            'student' => [
                'id' => $student->id,
                'name' => $student->name,
                'classroom' => $enrollment?->classroom->name,
            ],
            'assignments' => $assignments,
        ]);
    }

    public function attachment(Student $student, Assignment $assignment): StreamedResponse
    {
        $activeYearId = AcademicYear::query()->where('is_active', true)->value('id');
        $enrolled = $activeYearId !== null && Enrollment::query()
            ->where('student_id', $student->id)
            ->where('academic_year_id', $activeYearId)
            ->where('classroom_id', $assignment->classroom_id)
            ->exists();

        abort_unless($enrolled, 404);
        abort_if($assignment->attachment_path === null || ! Storage::disk('local')->exists($assignment->attachment_path), 404);

        return Storage::disk('local')->download($assignment->attachment_path);
    }
}
