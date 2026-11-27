<?php

declare(strict_types=1);

namespace App\Http\Controllers\Guardian;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\MemorizationRecord;
use App\Models\Student;
use App\Models\Surah;
use App\Services\Quran\MemorizationProgressService;
use Inertia\Inertia;
use Inertia\Response;

class MemorizationController extends Controller
{
    public function __construct(
        private readonly MemorizationProgressService $progress,
    ) {}

    public function __invoke(Student $student): Response
    {
        $activeYear = AcademicYear::query()->where('is_active', true)->first();
        $progress = $this->progress->progressForStudent($student, $activeYear);

        $surahNames = Surah::query()->pluck('name_latin', 'number')->all();

        $records = MemorizationRecord::query()
            ->with('material:id,title')
            ->where('student_id', $student->id)
            ->orderByDesc('recorded_on')
            ->orderByDesc('id')
            ->limit(60)
            ->get()
            ->map(function (MemorizationRecord $r) use ($surahNames): array {
                $summary = '—';
                if ($r->kind === 'quran' && $r->surah_from !== null && $r->surah_to !== null) {
                    $fromName = $surahNames[$r->surah_from] ?? "Surah {$r->surah_from}";
                    $toName = $surahNames[$r->surah_to] ?? "Surah {$r->surah_to}";
                    $summary = $r->surah_from === $r->surah_to
                        ? "{$fromName}: {$r->ayah_from}–{$r->ayah_to}"
                        : "{$fromName}:{$r->ayah_from} – {$toName}:{$r->ayah_to}";
                } elseif ($r->material !== null) {
                    $summary = $r->material->title;
                }

                return [
                    'id' => $r->id,
                    'kind' => $r->kind,
                    'type' => $r->type,
                    'summary' => $summary,
                    'final_score' => $r->final_score,
                    'predicate' => $r->predicate,
                    'passed' => $r->passed,
                    'note' => $r->note,
                    'recorded_on' => $r->recorded_on->toDateString(),
                ];
            })
            ->values()
            ->all();

        return Inertia::render('guardian/quran', [
            'student' => [
                'id' => $student->id,
                'name' => $student->name,
                'nis' => $student->nis,
            ],
            'progress' => $progress,
            'records' => $records,
        ]);
    }
}
