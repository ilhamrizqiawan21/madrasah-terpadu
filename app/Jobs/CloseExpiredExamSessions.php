<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\ExamSessionStatus;
use App\Models\ExamSession;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

/**
 * Menutup sesi ujian yang melewati waktu / terputus dan menilai sesi `force_closed` (T-1506).
 */
class CloseExpiredExamSessions implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        $now = CarbonImmutable::now();

        DB::transaction(function () use ($now): void {
            $openSessions = ExamSession::query()
                ->with('exam:id,closes_at')
                ->whereIn('status', [ExamSessionStatus::InProgress, ExamSessionStatus::Locked])
                ->lockForUpdate()
                ->get();

            foreach ($openSessions as $session) {
                $effectiveDeadline = $session->deadline_at->addMinutes($session->extra_minutes);
                $examClosesAt = $session->exam->closes_at->addMinutes($session->extra_minutes);
                $limit = $effectiveDeadline->lessThan($examClosesAt) ? $effectiveDeadline : $examClosesAt;

                if ($now->greaterThanOrEqualTo($limit)) {
                    $session->forceFill(['status' => ExamSessionStatus::AutoClosed])->save();
                    ScoreExamSession::dispatch($session->id)->afterCommit();
                }
            }

            $forceClosedWithoutResult = ExamSession::query()
                ->where('status', ExamSessionStatus::ForceClosed)
                ->whereDoesntHave('result')
                ->pluck('id');

            foreach ($forceClosedWithoutResult as $sessionId) {
                ScoreExamSession::dispatch((int) $sessionId)->afterCommit();
            }
        });
    }
}
