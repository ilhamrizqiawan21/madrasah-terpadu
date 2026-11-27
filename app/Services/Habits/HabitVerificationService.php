<?php

declare(strict_types=1);

namespace App\Services\Habits;

use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\HabitLog;
use App\Models\User;
use App\Notifications\HabitRejectedNotification;
use App\Services\Audit\AuditLogger;
use App\Services\Notifications\GuardianNotifier;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class HabitVerificationService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly GuardianNotifier $notifier,
        private readonly HabitStreakService $streaks,
    ) {}

    /**
     * @param  list<int>  $logIds
     */
    public function verifyBulk(User $actor, Classroom $classroom, string $logDate, array $logIds): int
    {
        $uniqueIds = array_values(array_unique(array_map('intval', $logIds)));
        if ($uniqueIds === []) {
            return 0;
        }

        $enrolledStudentIds = Enrollment::query()
            ->where('classroom_id', $classroom->id)
            ->where('academic_year_id', $classroom->academic_year_id)
            ->pluck('student_id')
            ->all();

        if ($enrolledStudentIds === []) {
            return 0;
        }

        return DB::transaction(function () use ($actor, $classroom, $logDate, $uniqueIds, $enrolledStudentIds): int {
            $logs = HabitLog::query()
                ->with('student')
                ->whereIn('id', $uniqueIds)
                ->whereIn('student_id', $enrolledStudentIds)
                ->whereDate('log_date', $logDate)
                ->lockForUpdate()
                ->get();

            $classroom->loadMissing('academicYear');
            $count = 0;
            foreach ($logs as $log) {
                $log->update([
                    'verification' => 'verified',
                    'verified_by' => $actor->id,
                    'verified_at' => now(),
                    'note' => null,
                ]);
                $this->streaks->evaluateAfterVerification($log, $classroom->academicYear);
                $count++;
            }

            if ($count > 0) {
                $this->audit->record(
                    action: 'habit_log.verified_bulk',
                    subject: $classroom,
                    after: ['log_date' => $logDate, 'count' => $count],
                    userId: $actor->id,
                );
            }

            return $count;
        });
    }

    public function reject(User $actor, Classroom $classroom, HabitLog $log, string $note): HabitLog
    {
        $trimmed = trim($note);
        if ($trimmed === '') {
            throw ValidationException::withMessages([
                'note' => 'Catatan penolakan wajib diisi.',
            ]);
        }

        $isEnrolled = Enrollment::query()
            ->where('classroom_id', $classroom->id)
            ->where('academic_year_id', $classroom->academic_year_id)
            ->where('student_id', $log->student_id)
            ->exists();

        if (! $isEnrolled) {
            throw ValidationException::withMessages([
                'note' => 'Siswa tidak terdaftar pada rombel ini.',
            ]);
        }

        $timezone = config('sekolah.timezone', 'Asia/Makassar');
        $tz = is_string($timezone) ? $timezone : 'Asia/Makassar';

        return DB::transaction(function () use ($actor, $log, $trimmed, $tz): HabitLog {
            /** @var HabitLog $locked */
            $locked = HabitLog::query()
                ->with(['student', 'item'])
                ->lockForUpdate()
                ->findOrFail($log->id);

            $locked->update([
                'verification' => 'rejected',
                'verified_by' => $actor->id,
                'verified_at' => now(),
                'note' => $trimmed,
            ]);

            $this->audit->record(
                action: 'habit_log.rejected',
                subject: $locked,
                after: ['note' => $trimmed],
                userId: $actor->id,
            );

            $logDate = $locked->log_date->toDateString();
            $dateLabel = CarbonImmutable::parse($logDate, $tz)->format('d/m/Y');

            $this->notifier->notifyStudentGuardians(
                $locked->student,
                new HabitRejectedNotification(
                    logId: $locked->id,
                    studentId: $locked->student_id,
                    studentName: $locked->student->name,
                    itemName: $locked->item->name,
                    logDate: $logDate,
                    dateLabel: $dateLabel,
                    note: $trimmed,
                ),
            );

            return $locked;
        });
    }
}
