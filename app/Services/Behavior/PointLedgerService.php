<?php

declare(strict_types=1);

namespace App\Services\Behavior;

use App\Enums\PointAccount;
use App\Models\AcademicYear;
use App\Models\PointEntry;
use App\Models\Student;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Satu-satunya pintu penulisan ke buku besar poin `point_entries` (ADR-006).
 */
class PointLedgerService
{
    public function post(
        Student $student,
        AcademicYear $academicYear,
        PointAccount $account,
        int $delta,
        Model $source,
        string $idempotencyKey,
    ): PointEntry {
        return DB::transaction(function () use ($student, $academicYear, $account, $delta, $source, $idempotencyKey): PointEntry {
            $existing = PointEntry::query()
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            $latest = PointEntry::query()
                ->where('student_id', $student->id)
                ->where('academic_year_id', $academicYear->id)
                ->where('account', $account->value)
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            $currentBalance = $latest->balance_after ?? 0;
            $balanceAfter = $currentBalance + $delta;

            return PointEntry::query()->create([
                'student_id' => $student->id,
                'academic_year_id' => $academicYear->id,
                'account' => $account->value,
                'delta' => $delta,
                'balance_after' => $balanceAfter,
                'source_type' => $source->getMorphClass(),
                'source_id' => (int) $source->getKey(),
                'idempotency_key' => $idempotencyKey,
                'created_at' => now(),
            ]);
        });
    }

    public function balanceFor(int $studentId, int $academicYearId, PointAccount $account): int
    {
        $latest = PointEntry::query()
            ->where('student_id', $studentId)
            ->where('academic_year_id', $academicYearId)
            ->where('account', $account->value)
            ->orderByDesc('id')
            ->first();

        return $latest->balance_after ?? 0;
    }
}
