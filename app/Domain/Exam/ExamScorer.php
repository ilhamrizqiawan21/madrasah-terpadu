<?php

declare(strict_types=1);

namespace App\Domain\Exam;

use App\Enums\ExamQuestionType;
use App\Enums\ExamResultStatus;

/**
 * Mesin penilaian ujian CBT murni (port aturan CBT-Sekolah, ADR-020 / T-1506).
 */
final class ExamScorer
{
    /**
     * @param  array{mcq?: int, matching?: int, essay_paper?: int}  $weights
     * @param  list<array{id: int, type: ExamQuestionType, answer_key: array<string, mixed>|null}>  $questions
     * @param  array<int, array<string, mixed>|null>  $answersByQuestionId
     * @param  array<int, int>  $essayScoresByQuestionId
     * @return array{auto_points: int, essay_points: int|null, max_points: int, score: int|null, status: ExamResultStatus}
     */
    public function score(
        array $weights,
        array $questions,
        array $answersByQuestionId,
        array $essayScoresByQuestionId = [],
    ): array {
        $mcqWeight = max(0, (int) ($weights['mcq'] ?? 2));
        $matchingWeight = max(0, (int) ($weights['matching'] ?? 2));
        $essayWeight = max(0, (int) ($weights['essay_paper'] ?? 4));

        $autoPoints = 0;
        $essayPoints = 0;
        $maxPoints = 0;
        $hasEssay = false;
        $allEssaysScored = true;

        foreach ($questions as $question) {
            $answer = $answersByQuestionId[$question['id']] ?? null;
            $key = $question['answer_key'];

            if ($question['type'] === ExamQuestionType::Mcq) {
                $maxPoints += $mcqWeight;
                $selected = is_array($answer) ? ($answer['selected'] ?? null) : null;
                $correct = is_array($key) ? ($key['correct'] ?? null) : null;

                if ($selected !== null && $correct !== null && $selected === $correct) {
                    $autoPoints += $mcqWeight;
                }

                continue;
            }

            if ($question['type'] === ExamQuestionType::Matching) {
                $maxPoints += $matchingWeight;
                $studentPairs = is_array($answer) && is_array($answer['pairs'] ?? null) ? $answer['pairs'] : null;
                $correctPairs = is_array($key) && is_array($key['pairs'] ?? null) ? $key['pairs'] : null;

                if (
                    $studentPairs !== null
                    && $correctPairs !== null
                    && $correctPairs !== []
                    && count($studentPairs) === count($correctPairs)
                ) {
                    $allMatch = true;
                    foreach ($correctPairs as $pairKey => $pairValue) {
                        if (! array_key_exists($pairKey, $studentPairs) || $studentPairs[$pairKey] !== $pairValue) {
                            $allMatch = false;
                            break;
                        }
                    }

                    if ($allMatch) {
                        $autoPoints += $matchingWeight;
                    }
                }

                continue;
            }

            // EssayPaper
            $hasEssay = true;
            $maxPoints += $essayWeight;

            if (! array_key_exists($question['id'], $essayScoresByQuestionId)) {
                $allEssaysScored = false;
            } else {
                $essayPoints += max(0, min($essayWeight, $essayScoresByQuestionId[$question['id']]));
            }
        }

        if ($hasEssay && ! $allEssaysScored) {
            return [
                'auto_points' => $autoPoints,
                'essay_points' => null,
                'max_points' => $maxPoints,
                'score' => null,
                'status' => ExamResultStatus::AwaitingEssay,
            ];
        }

        $totalPoints = $autoPoints + $essayPoints;
        $finalScore = $maxPoints > 0 ? (int) round(($totalPoints / $maxPoints) * 100) : 0;

        return [
            'auto_points' => $autoPoints,
            'essay_points' => $essayPoints,
            'max_points' => $maxPoints,
            'score' => min(100, max(0, $finalScore)),
            'status' => ExamResultStatus::Final,
        ];
    }
}
