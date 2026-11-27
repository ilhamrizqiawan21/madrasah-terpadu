<?php

declare(strict_types=1);

namespace App\Http\Controllers\Guardian;

use App\Enums\ExamResultStatus;
use App\Http\Controllers\Controller;
use App\Models\ExamResult;
use App\Models\Student;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ExamResultController extends Controller
{
    public function __invoke(Request $request, Student $student): Response
    {
        $results = ExamResult::query()
            ->with(['session.exam.subject'])
            ->where('status', ExamResultStatus::Final)
            ->whereHas('session', function ($query) use ($student): void {
                $query->where('student_id', $student->id)
                    ->whereHas('exam', fn ($examQuery) => $examQuery->where('show_result_to_guardian', true));
            })
            ->orderByDesc('computed_at')
            ->get()
            ->map(fn (ExamResult $result): array => [
                'id' => $result->id,
                'exam_title' => $result->session->exam->title,
                'subject' => $result->session->exam->subject->name,
                'score' => $result->score,
                'computed_at' => $result->computed_at->toIso8601String(),
            ])
            ->values();

        return Inertia::render('guardian/exams', [
            'student' => [
                'id' => $student->id,
                'name' => $student->name,
                'nis' => $student->nis,
            ],
            'results' => $results,
        ]);
    }
}
