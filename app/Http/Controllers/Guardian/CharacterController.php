<?php

declare(strict_types=1);

namespace App\Http\Controllers\Guardian;

use App\Enums\CharacterReviewStatus;
use App\Http\Controllers\Controller;
use App\Models\CharacterReview;
use App\Models\Student;
use App\Services\Character\CharacterReportService;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Penilaian karakter anak untuk wali (ADR-029, T-1204). Hanya penilaian yang sudah disetujui kepala unit.
 * Kepemilikan anak dijaga middleware `guardian.owns-student` (anak lain 404).
 */
class CharacterController extends Controller
{
    public function __construct(private readonly CharacterReportService $reports) {}

    public function index(Student $student): Response
    {
        $reviews = CharacterReview::query()
            ->with(['academicYear', 'classroom:id,name'])
            ->where('status', CharacterReviewStatus::Approved->value)
            ->whereHas('assessments', fn ($q) => $q->where('student_id', $student->id))
            ->orderByDesc('approved_at')
            ->orderByDesc('id')
            ->get();

        return Inertia::render('guardian/character', [
            'student' => ['id' => $student->id, 'name' => $student->name, 'nickname' => $student->nickname],
            'reports' => $reviews->map(function (CharacterReview $review) use ($student): array {
                $row = $this->reports->rows($review, $student->id)[0] ?? null;

                return [
                    'id' => $review->id,
                    'semester' => $review->academicYear->name.' · '.$review->academicYear->semester->label(),
                    'classroom' => $review->classroom->name,
                    'approved_at' => $review->approved_at?->toIso8601String(),
                    'dimensions' => $row['dimensions'] ?? [],
                    'pdf_url' => route('guardian.students.character.pdf', ['student' => $student->id, 'review' => $review->id]),
                ];
            })->values()->all(),
        ]);
    }

    public function pdf(Student $student, CharacterReview $review): HttpResponse
    {
        abort_unless(
            $review->status === CharacterReviewStatus::Approved
                && $review->assessments()->where('student_id', $student->id)->exists(),
            404,
        );

        return response($this->reports->pdf($review, $student->id), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="karakter-'.$student->nis.'.pdf"',
        ]);
    }
}
