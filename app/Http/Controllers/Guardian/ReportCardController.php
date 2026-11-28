<?php

declare(strict_types=1);

namespace App\Http\Controllers\Guardian;

use App\Http\Controllers\Controller;
use App\Models\ReportCard;
use App\Models\Student;
use App\Support\AppTimezone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Daftar & unduh PDF E-Rapor anak di portal wali lewat URL bertanda tangan (T-1604).
 */
class ReportCardController extends Controller
{
    public function index(Student $student): Response
    {
        $tz = AppTimezone::name();

        $cards = ReportCard::query()
            ->with('academicYear:id,name')
            ->where('student_id', $student->id)
            ->whereNotNull('published_at')
            ->orderByDesc('academic_year_id')
            ->orderByDesc('semester')
            ->get()
            ->map(function (ReportCard $card) use ($student, $tz): array {
                return [
                    'id' => $card->id,
                    'academic_year_name' => $card->academicYear->name,
                    'semester' => $card->semester,
                    'semester_label' => $card->semester === 'odd' ? 'Ganjil' : 'Genap',
                    'published_at' => $card->published_at?->setTimezone($tz)->toIso8601String(),
                    'download_url' => URL::temporarySignedRoute(
                        'guardian.students.report-cards.download',
                        now()->addMinutes(15),
                        ['student' => $student->id, 'reportCard' => $card->id],
                    ),
                ];
            })
            ->values();

        return Inertia::render('guardian/report-cards', [
            'student' => [
                'id' => $student->id,
                'name' => $student->name,
            ],
            'reportCards' => $cards,
        ]);
    }

    public function download(Request $request, Student $student, ReportCard $reportCard): StreamedResponse
    {
        abort_unless($request->hasValidSignature(), 403);
        abort_unless($reportCard->student_id === $student->id && $reportCard->published_at !== null, 404);
        abort_unless(Storage::disk('local')->exists($reportCard->file_path), 404);

        return Storage::disk('local')->download(
            $reportCard->file_path,
            "rapor-{$student->nis}-{$reportCard->semester}.pdf",
        );
    }
}
