<?php

declare(strict_types=1);

namespace App\Services\Behavior;

use App\Domain\Behavior\ThresholdEvaluator;
use App\Models\AcademicYear;
use App\Models\DisciplineFollowup;
use App\Models\DisciplineThreshold;
use App\Models\Student;
use App\Models\User;
use App\Notifications\DisciplineThresholdTriggeredNotification;
use App\Services\Audit\AuditLogger;
use App\Services\Notifications\GuardianNotifier;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DisciplineFollowupService
{
    public function __construct(
        private readonly ThresholdEvaluator $evaluator,
        private readonly AuditLogger $audit,
        private readonly GuardianNotifier $notifier,
    ) {}

    /**
     * @return Collection<int, DisciplineFollowup>
     */
    public function evaluateForStudent(
        Student $student,
        AcademicYear $academicYear,
        int $violationBalance,
        ?User $actor = null,
    ): Collection {
        if ($violationBalance <= 0) {
            return new Collection;
        }

        return DB::transaction(function () use ($student, $academicYear, $violationBalance, $actor): Collection {
            $activeThresholds = DisciplineThreshold::query()
                ->active()
                ->where(function ($q) use ($student): void {
                    $q->where('unit_id', $student->unit_id)->orWhereNull('unit_id');
                })
                ->orderBy('points')
                ->get();

            /** @var list<int> $alreadyTriggeredIds */
            $alreadyTriggeredIds = DisciplineFollowup::query()
                ->where('student_id', $student->id)
                ->where('academic_year_id', $academicYear->id)
                ->pluck('discipline_threshold_id')
                ->map(static fn (mixed $id): int => (int) $id)
                ->values()
                ->all();

            $crossed = $this->evaluator->crossedThresholds(
                $activeThresholds,
                $violationBalance,
                $alreadyTriggeredIds,
            );

            $created = new Collection;

            foreach ($crossed as $threshold) {
                $followup = DisciplineFollowup::query()->create([
                    'student_id' => $student->id,
                    'academic_year_id' => $academicYear->id,
                    'discipline_threshold_id' => $threshold->id,
                    'triggered_at' => now(),
                    'status' => 'open',
                ]);

                $this->audit->record(
                    action: 'followup.triggered',
                    subject: $followup,
                    after: [
                        'student_id' => $student->id,
                        'discipline_threshold_id' => $threshold->id,
                        'violation_balance' => $violationBalance,
                    ],
                    userId: $actor?->id,
                );

                if ($threshold->notify_guardian) {
                    $this->notifier->notifyStudentGuardians(
                        $student,
                        new DisciplineThresholdTriggeredNotification(
                            followupId: $followup->id,
                            studentId: $student->id,
                            studentName: $student->name,
                            thresholdPoints: $threshold->points,
                            actionName: $threshold->action_name,
                        ),
                    );
                }

                $created->push($followup);
            }

            return $created;
        });
    }

    public function markDone(User $actor, DisciplineFollowup $followup, string $note): DisciplineFollowup
    {
        $trimmed = trim($note);
        if ($trimmed === '') {
            throw ValidationException::withMessages([
                'note' => 'Catatan penyelesaian tindak lanjut wajib diisi.',
            ]);
        }

        return DB::transaction(function () use ($actor, $followup, $trimmed): DisciplineFollowup {
            /** @var DisciplineFollowup $locked */
            $locked = DisciplineFollowup::query()->lockForUpdate()->findOrFail($followup->id);

            if ($locked->status === 'done') {
                throw ValidationException::withMessages([
                    'note' => 'Tindak lanjut ini sudah ditandai selesai.',
                ]);
            }

            $before = ['status' => $locked->status];
            $locked->update([
                'status' => 'done',
                'done_by' => $actor->id,
                'done_at' => now(),
                'note' => $trimmed,
            ]);

            $this->audit->record(
                action: 'followup.done',
                subject: $locked,
                before: $before,
                after: ['status' => 'done', 'note' => $trimmed],
                userId: $actor->id,
            );

            return $locked;
        });
    }
}
