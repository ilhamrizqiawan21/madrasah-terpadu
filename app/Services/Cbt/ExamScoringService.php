<?php

declare(strict_types=1);

namespace App\Services\Cbt;

use App\Domain\Exam\ExamScorer;
use App\Models\ExamResult;
use App\Models\ExamSession;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Penilaian sesi ujian CBT dan penyimpanan hasil ke `exam_results` (T-1506).
 */
final class ExamScoringService
{
    public function __construct(private readonly ExamScorer $scorer) {}

    /**
     * @param  array<int, int>  $essayScoresByQuestionId
     */
    public function scoreSession(ExamSession $session, array $essayScoresByQuestionId = []): ExamResult
    {
        return DB::transaction(function () use ($session, $essayScoresByQuestionId): ExamResult {
            $lockedSession = ExamSession::query()
                ->with(['exam.questions', 'answers', 'essayScores'])
                ->whereKey($session->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($essayScoresByQuestionId === []) {
                foreach ($lockedSession->essayScores as $essayScore) {
                    $essayScoresByQuestionId[$essayScore->exam_question_id] = $essayScore->score;
                }
            }

            $questions = array_values($lockedSession->exam->questions->map(fn ($q): array => [
                'id' => $q->id,
                'type' => $q->type,
                'answer_key' => $q->answer_key,
            ])->all());

            /** @var array<int, array<string, mixed>|null> $answersByQuestionId */
            $answersByQuestionId = [];
            foreach ($lockedSession->answers as $ans) {
                $answersByQuestionId[$ans->exam_question_id] = $ans->answer;
            }

            $computed = $this->scorer->score(
                weights: $lockedSession->exam->weights,
                questions: $questions,
                answersByQuestionId: $answersByQuestionId,
                essayScoresByQuestionId: $essayScoresByQuestionId,
            );

            return ExamResult::query()->updateOrCreate(
                ['exam_session_id' => $lockedSession->id],
                [
                    'auto_points' => $computed['auto_points'],
                    'essay_points' => $computed['essay_points'],
                    'max_points' => $computed['max_points'],
                    'score' => $computed['score'],
                    'status' => $computed['status'],
                    'computed_at' => CarbonImmutable::now(),
                ],
            );
        });
    }
}
