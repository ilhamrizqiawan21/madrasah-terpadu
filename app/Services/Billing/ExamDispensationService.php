<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\ExamEligibilityOverride;
use App\Models\ExamPeriod;
use App\Models\Student;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Pengelola dispensasi kelayakan ujian oleh admin (T-612, ADR-016).
 */
final class ExamDispensationService
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    public function grant(User $actor, ExamPeriod $period, Student $student, string $reason): ExamEligibilityOverride
    {
        if ($student->unit_id !== $period->unit_id) {
            throw ValidationException::withMessages([
                'student_id' => 'Siswa harus berasal dari unit yang sama dengan periode ujian.',
            ]);
        }

        $cleanReason = trim($reason);
        if ($cleanReason === '') {
            throw ValidationException::withMessages([
                'reason' => 'Alasan dispensasi wajib diisi.',
            ]);
        }

        return DB::transaction(function () use ($actor, $period, $student, $cleanReason): ExamEligibilityOverride {
            $exists = ExamEligibilityOverride::query()
                ->where('exam_period_id', $period->id)
                ->where('student_id', $student->id)
                ->whereNull('revoked_at')
                ->lockForUpdate()
                ->exists();

            if ($exists) {
                throw ValidationException::withMessages([
                    'student_id' => 'Siswa ini sudah memiliki dispensasi aktif pada periode ujian tersebut.',
                ]);
            }

            $override = ExamEligibilityOverride::query()->create([
                'exam_period_id' => $period->id,
                'student_id' => $student->id,
                'reason' => $cleanReason,
                'granted_by' => $actor->id,
                'granted_at' => now(),
            ]);

            $this->audit->record('exam_dispensation.granted', $override, null, [
                'exam_period_id' => $period->id,
                'student_id' => $student->id,
                'reason' => $cleanReason,
            ]);

            return $override;
        });
    }

    public function revoke(User $actor, ExamEligibilityOverride $override): ExamEligibilityOverride
    {
        return DB::transaction(function () use ($actor, $override): ExamEligibilityOverride {
            $locked = ExamEligibilityOverride::query()->whereKey($override->id)->lockForUpdate()->firstOrFail();

            if ($locked->revoked_at !== null) {
                throw ValidationException::withMessages([
                    'override' => 'Dispensasi ini sudah dicabut.',
                ]);
            }

            $locked->forceFill([
                'revoked_by' => $actor->id,
                'revoked_at' => now(),
            ])->save();

            $this->audit->record('exam_dispensation.revoked', $locked, ['revoked_at' => null], [
                'exam_period_id' => $locked->exam_period_id,
                'student_id' => $locked->student_id,
                'revoked_by' => $actor->id,
            ]);

            return $locked;
        });
    }
}
