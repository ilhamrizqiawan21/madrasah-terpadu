<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\ExamSession;
use App\Services\Cbt\ExamScoringService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ScoreExamSession implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly int $examSessionId) {}

    public function handle(ExamScoringService $scoringService): void
    {
        $session = ExamSession::query()->find($this->examSessionId);
        if (! $session instanceof ExamSession) {
            return;
        }

        $scoringService->scoreSession($session);
    }
}
