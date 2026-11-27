<?php

declare(strict_types=1);

namespace App\Services\Cbt;

use App\Enums\ExamQuestionType;
use App\Models\Exam;
use App\Models\ExamEssayScore;
use App\Models\ExamQuestion;
use App\Models\ExamResult;
use App\Models\ExamSession;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use OpenSpout\Reader\XLSX\Reader;

/**
 * Penilaian soal essay kertas oleh guru pengampu/admin (T-1507).
 */
final class ExamEssayScoreService
{
    public function __construct(
        private readonly ExamScoringService $scoringService,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @param  list<array{exam_question_id: int, score: int}>  $scores
     */
    public function saveForSession(Exam $exam, ExamSession $session, array $scores, User $actor): ExamResult
    {
        $essayQuestionIds = $exam->questions()
            ->where('type', ExamQuestionType::EssayPaper)
            ->pluck('id')
            ->all();

        DB::transaction(function () use ($exam, $session, $scores, $essayQuestionIds, $actor): void {
            foreach ($scores as $entry) {
                if (! in_array($entry['exam_question_id'], $essayQuestionIds, true)) {
                    continue;
                }

                ExamEssayScore::query()->updateOrCreate(
                    [
                        'exam_session_id' => $session->id,
                        'exam_question_id' => $entry['exam_question_id'],
                    ],
                    [
                        'score' => $entry['score'],
                        'scored_by' => $actor->id,
                    ],
                );
            }

            $this->auditLogger->record(
                action: 'essay_scored',
                subject: $session,
                after: ['exam_id' => $exam->id, 'count' => count($scores)],
                userId: $actor->id,
            );
        });

        return $this->scoringService->scoreSession($session);
    }

    /**
     * Impor nilai essay dari Excel (.xlsx): kolom `nis`, `question_order`, `score`.
     *
     * @return array{updated: int, failed: list<array{row: int, error: string}>}
     */
    public function importFromExcel(Exam $exam, UploadedFile $file, User $actor): array
    {
        $maxWeight = max(0, (int) ($exam->weights['essay_paper'] ?? 4));
        $sessionsByNis = ExamSession::query()
            ->with('student:id,nis')
            ->where('exam_id', $exam->id)
            ->get()
            ->keyBy(fn (ExamSession $s): string => $s->student->nis);

        $essaysByOrder = $exam->questions()
            ->where('type', ExamQuestionType::EssayPaper)
            ->get()
            ->keyBy('sort_order');

        $reader = new Reader;
        $reader->open($file->getRealPath());

        $updated = 0;
        $failed = [];
        $headers = [];
        $rowNumber = 0;
        $grouped = [];

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $rowNumber++;
                    $cells = $row->toArray();

                    if ($rowNumber === 1) {
                        $headers = array_map(
                            static fn (mixed $v): string => mb_strtolower(trim(is_scalar($v) ? (string) $v : '')),
                            $cells,
                        );

                        continue;
                    }

                    $mapped = [];
                    foreach ($headers as $idx => $key) {
                        if ($key !== '') {
                            $raw = $cells[$idx] ?? '';
                            $mapped[$key] = trim(is_scalar($raw) ? (string) $raw : '');
                        }
                    }

                    $nis = $mapped['nis'] ?? '';
                    if ($nis === '') {
                        continue;
                    }

                    $session = $sessionsByNis->get($nis);
                    if (! $session instanceof ExamSession) {
                        $failed[] = ['row' => $rowNumber, 'error' => "Sesi untuk NIS {$nis} tidak ditemukan."];

                        continue;
                    }

                    $order = (int) ($mapped['question_order'] ?? 0);
                    $question = $essaysByOrder->get($order);
                    if (! $question instanceof ExamQuestion) {
                        $failed[] = ['row' => $rowNumber, 'error' => "Soal essay nomor urut {$order} tidak ditemukan."];

                        continue;
                    }

                    $rawScore = $mapped['score'] ?? '';
                    if (! is_numeric($rawScore) || (int) $rawScore < 0 || (int) $rawScore > $maxWeight) {
                        $failed[] = ['row' => $rowNumber, 'error' => "Nilai harus antara 0 dan {$maxWeight}."];

                        continue;
                    }

                    $grouped[$session->id][] = [
                        'exam_question_id' => $question->id,
                        'score' => (int) $rawScore,
                    ];
                    $updated++;
                }

                break;
            }
        } finally {
            $reader->close();
        }

        foreach ($grouped as $sessionId => $scores) {
            /** @var ExamSession $session */
            $session = ExamSession::query()->findOrFail($sessionId);
            $this->saveForSession($exam, $session, $scores, $actor);
        }

        return ['updated' => $updated, 'failed' => $failed];
    }
}
