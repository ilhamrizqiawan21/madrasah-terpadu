<?php

declare(strict_types=1);

use App\Domain\Exam\ExamScorer;
use App\Enums\ExamQuestionType;
use App\Enums\ExamResultStatus;

test('exam scorer menghitung pg menjodohkan dan pembulatan dengan bobot dari ujian', function (): void {
    $scorer = new ExamScorer;

    $result = $scorer->score(
        weights: ['mcq' => 2, 'matching' => 4, 'essay_paper' => 4],
        questions: [
            ['id' => 1, 'type' => ExamQuestionType::Mcq, 'answer_key' => ['correct' => 'A']],
            ['id' => 2, 'type' => ExamQuestionType::Mcq, 'answer_key' => ['correct' => 'B']],
            ['id' => 3, 'type' => ExamQuestionType::Matching, 'answer_key' => ['pairs' => ['1' => 'X', '2' => 'Y']]],
        ],
        answersByQuestionId: [
            1 => ['selected' => 'A'],
            2 => ['selected' => 'C'],
            3 => ['pairs' => ['1' => 'X', '2' => 'Y']],
        ],
    );

    // 1 PG benar (2) + 1 PG salah (0) + 1 menjodohkan benar semua (4) = 6 / 8 * 100 = 75.
    expect($result['auto_points'])->toBe(6)
        ->and($result['essay_points'])->toBe(0)
        ->and($result['max_points'])->toBe(8)
        ->and($result['score'])->toBe(75)
        ->and($result['status'])->toBe(ExamResultStatus::Final);
});

test('soal menjodohkan bersifat all or nothing', function (): void {
    $scorer = new ExamScorer;

    $result = $scorer->score(
        weights: ['mcq' => 2, 'matching' => 4, 'essay_paper' => 4],
        questions: [
            ['id' => 1, 'type' => ExamQuestionType::Matching, 'answer_key' => ['pairs' => ['1' => 'X', '2' => 'Y']]],
        ],
        answersByQuestionId: [
            1 => ['pairs' => ['1' => 'X', '2' => 'Z']],
        ],
    );

    expect($result['auto_points'])->toBe(0)
        ->and($result['score'])->toBe(0);
});

test('ujian dengan soal essay berstatus awaiting_essay sampai semua essay dinilai', function (): void {
    $scorer = new ExamScorer;
    $questions = [
        ['id' => 1, 'type' => ExamQuestionType::Mcq, 'answer_key' => ['correct' => 'A']],
        ['id' => 2, 'type' => ExamQuestionType::EssayPaper, 'answer_key' => null],
    ];
    $answers = [1 => ['selected' => 'A']];
    $weights = ['mcq' => 2, 'matching' => 2, 'essay_paper' => 4];

    $pending = $scorer->score($weights, $questions, $answers);
    expect($pending['status'])->toBe(ExamResultStatus::AwaitingEssay)
        ->and($pending['auto_points'])->toBe(2)
        ->and($pending['essay_points'])->toBeNull()
        ->and($pending['score'])->toBeNull();

    $final = $scorer->score($weights, $questions, $answers, [2 => 3]);
    // (2 + 3) / (2 + 4) * 100 = 83.33 -> 83.
    expect($final['status'])->toBe(ExamResultStatus::Final)
        ->and($final['essay_points'])->toBe(3)
        ->and($final['max_points'])->toBe(6)
        ->and($final['score'])->toBe(83);
});
