<?php

declare(strict_types=1);

namespace App\Http\Controllers\Exam;

use App\Enums\ExamStatus;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Exam;
use App\Models\ExamSession;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ExamDashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        /** @var Student $student */
        $student = Auth::guard('student')->user();
        $student->loadMissing('unit');

        $activeYearId = AcademicYear::query()->where('is_active', true)->value('id');
        $classroomId = null;
        $classroomName = null;

        if ($activeYearId !== null) {
            $enrollment = $student->enrollments()
                ->with('classroom:id,name')
                ->where('academic_year_id', $activeYearId)
                ->first();
            $classroomId = $enrollment?->classroom?->id;
            $classroomName = $enrollment?->classroom?->name;
        }

        $exams = $classroomId !== null
            ? Exam::query()
                ->with('subject:id,code,name')
                ->whereIn('status', [ExamStatus::Scheduled, ExamStatus::Running])
                ->orderBy('opens_at')
                ->get()
                ->filter(fn (Exam $exam): bool => in_array($classroomId, $exam->classroom_ids, true))
                ->values()
            : collect();

        $sessions = ExamSession::query()
            ->where('student_id', $student->id)
            ->whereIn('exam_id', $exams->pluck('id'))
            ->get()
            ->keyBy('exam_id');

        return Inertia::render('exam/index', [
            'student' => [
                'id' => $student->id,
                'nis' => $student->nis,
                'name' => $student->name,
                'unit' => $student->unit->code,
                'classroom' => $classroomName,
            ],
            'exams' => $exams->map(function (Exam $exam) use ($sessions): array {
                /** @var ExamSession|null $session */
                $session = $sessions->get($exam->id);

                return [
                    'id' => $exam->id,
                    'title' => $exam->title,
                    'subject' => $exam->subject->name,
                    'duration_minutes' => $exam->duration_minutes,
                    'requires_token' => $exam->access_token !== null,
                    'device_modes' => $exam->device_modes,
                    'session_status' => $session?->status->value,
                    'session_status_label' => $session?->status->label(),
                ];
            })->all(),
        ]);
    }
}
