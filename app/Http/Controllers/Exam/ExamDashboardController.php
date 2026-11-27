<?php

declare(strict_types=1);

namespace App\Http\Controllers\Exam;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
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
        $classroomName = null;

        if ($activeYearId !== null) {
            $enrollment = $student->enrollments()
                ->with('classroom:id,name')
                ->where('academic_year_id', $activeYearId)
                ->first();
            $classroomName = $enrollment?->classroom?->name;
        }

        return Inertia::render('exam/index', [
            'student' => [
                'id' => $student->id,
                'nis' => $student->nis,
                'name' => $student->name,
                'unit' => $student->unit->code,
                'classroom' => $classroomName,
            ],
        ]);
    }
}
