<?php

declare(strict_types=1);

namespace App\Services\Habits;

use App\Models\HabitItem;
use App\Models\HabitLog;
use App\Models\Student;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class HabitLogService
{
    public const MAX_BACKDATE_DAYS = 3;

    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    public function toggleByGuardian(
        User $actor,
        Student $student,
        HabitItem $item,
        string $logDate,
        bool $done,
    ): HabitLog {
        $timezone = config('sekolah.timezone', 'Asia/Makassar');
        $tz = is_string($timezone) ? $timezone : 'Asia/Makassar';
        $today = CarbonImmutable::now($tz)->startOfDay();
        $targetDate = CarbonImmutable::parse($logDate, $tz)->startOfDay();

        if ($targetDate->greaterThan($today)) {
            throw ValidationException::withMessages([
                'log_date' => 'Tanggal pembiasaan tidak boleh di masa depan.',
            ]);
        }

        if ($targetDate->lessThan($today->subDays(self::MAX_BACKDATE_DAYS))) {
            throw ValidationException::withMessages([
                'log_date' => 'Hanya dapat mengisi pembiasaan maksimal 3 hari ke belakang.',
            ]);
        }

        if (! $item->is_active) {
            throw ValidationException::withMessages([
                'habit_item_id' => 'Item pembiasaan sedang tidak aktif.',
            ]);
        }

        if ($item->unit_id !== null && $item->unit_id !== $student->unit_id) {
            throw ValidationException::withMessages([
                'habit_item_id' => 'Item pembiasaan tidak berlaku untuk unit siswa ini.',
            ]);
        }

        $dateString = $targetDate->toDateString();

        return DB::transaction(function () use ($actor, $student, $item, $dateString, $done): HabitLog {
            $existing = HabitLog::query()
                ->where('student_id', $student->id)
                ->where('habit_item_id', $item->id)
                ->whereDate('log_date', $dateString)
                ->lockForUpdate()
                ->first();

            if ($existing !== null && $existing->verification === 'verified') {
                throw ValidationException::withMessages([
                    'habit_item_id' => 'Catatan pembiasaan ini sudah diverifikasi guru dan tidak dapat diubah.',
                ]);
            }

            if ($existing !== null) {
                $existing->update([
                    'done' => $done,
                    'submitted_by' => $actor->id,
                    'submitted_at' => now(),
                    'verification' => 'pending',
                    'verified_by' => null,
                    'verified_at' => null,
                    'note' => null,
                ]);

                $log = $existing;
            } else {
                $log = HabitLog::query()->create([
                    'student_id' => $student->id,
                    'habit_item_id' => $item->id,
                    'log_date' => $dateString,
                    'done' => $done,
                    'submitted_by' => $actor->id,
                    'submitted_at' => now(),
                    'verification' => 'pending',
                ]);
            }

            $this->audit->record(
                action: 'habit_log.toggled',
                subject: $log,
                after: ['student_id' => $student->id, 'habit_item_id' => $item->id, 'log_date' => $dateString, 'done' => $done],
                userId: $actor->id,
            );

            return $log;
        });
    }
}
