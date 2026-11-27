<?php

declare(strict_types=1);

namespace App\Services\Cbt;

use App\Enums\ExamDeviceMode;
use App\Enums\ExamSessionStatus;
use App\Enums\ExamViolationKind;
use App\Exceptions\Cbt\DeviceTakeoverException;
use App\Models\ExamSession;
use App\Models\ExamViolation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Pencatatan pelanggaran ujian append-only dan penguncian otomatis sesuai mode perangkat (T-1508).
 */
final class ExamViolationService
{
    /**
     * @return array{counted_violations: int, locked: bool}
     */
    public function record(ExamSession $session, string $deviceToken, ExamViolationKind $kind): array
    {
        if (! hash_equals($session->device_token, $deviceToken)) {
            throw new DeviceTakeoverException;
        }

        return DB::transaction(function () use ($session, $kind): array {
            $lockedSession = ExamSession::query()
                ->with('exam:id,violation_limit')
                ->whereKey($session->id)
                ->lockForUpdate()
                ->firstOrFail();

            $counted = $lockedSession->device_type !== ExamDeviceMode::Phone->value
                && $kind !== ExamViolationKind::Takeover;

            ExamViolation::query()->create([
                'exam_session_id' => $lockedSession->id,
                'kind' => $kind,
                'occurred_at' => CarbonImmutable::now(),
                'counted' => $counted,
            ]);

            $countedTotal = ExamViolation::query()
                ->where('exam_session_id', $lockedSession->id)
                ->where('counted', true)
                ->count();

            $locked = false;
            if (
                $counted
                && $lockedSession->status === ExamSessionStatus::InProgress
                && $countedTotal >= $lockedSession->exam->violation_limit
            ) {
                $lockedSession->forceFill(['status' => ExamSessionStatus::Locked])->save();
                $locked = true;
            }

            return [
                'counted_violations' => $countedTotal,
                'locked' => $locked || $lockedSession->status === ExamSessionStatus::Locked,
            ];
        });
    }
}
