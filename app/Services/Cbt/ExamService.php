<?php

declare(strict_types=1);

namespace App\Services\Cbt;

use App\Enums\ExamStatus;
use App\Enums\Permission;
use App\Models\Exam;
use App\Models\TeachingAssignment;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Satu-satunya penulis pengaturan ujian CBT (T-1503).
 */
final class ExamService
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /**
     * Pastikan guru pengampu hanya dapat membuat ujian untuk (subject_id, classroom_ids) yang ditugaskan kepadanya.
     *
     * @param  list<int>  $classroomIds
     */
    public function canTeachTarget(User $actor, int $subjectId, array $classroomIds): bool
    {
        if ($actor->can(Permission::CbtManage->value)) {
            return true;
        }

        if (! $actor->can(Permission::CbtTeach->value) || $classroomIds === []) {
            return false;
        }

        $assignedCount = TeachingAssignment::query()
            ->where('user_id', $actor->id)
            ->where('subject_id', $subjectId)
            ->whereIn('classroom_id', $classroomIds)
            ->distinct('classroom_id')
            ->count('classroom_id');

        return $assignedCount === count(array_unique($classroomIds));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $actor): Exam
    {
        return DB::transaction(function () use ($data, $actor): Exam {
            $exam = Exam::query()->create([
                ...$data,
                'status' => ExamStatus::Draft,
                'created_by' => $actor->id,
            ]);

            $this->auditLogger->record(
                action: 'created',
                subject: $exam,
                after: $this->snapshot($exam),
                userId: $actor->id,
            );

            return $exam;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Exam $exam, array $data, User $actor): Exam
    {
        return DB::transaction(function () use ($exam, $data, $actor): Exam {
            $before = $this->snapshot($exam);

            $exam->fill($data)->save();

            $this->auditLogger->record(
                action: 'updated',
                subject: $exam,
                before: $before,
                after: $this->snapshot($exam),
                userId: $actor->id,
            );

            return $exam;
        });
    }

    public function updateStatus(Exam $exam, ExamStatus $status, User $actor): Exam
    {
        if ($exam->status === $status) {
            return $exam;
        }

        return DB::transaction(function () use ($exam, $status, $actor): Exam {
            $before = ['status' => $exam->status->value];

            $exam->forceFill(['status' => $status])->save();

            $this->auditLogger->record(
                action: 'status_updated',
                subject: $exam,
                before: $before,
                after: ['status' => $status->value],
                userId: $actor->id,
            );

            return $exam;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Exam $exam): array
    {
        return [
            'subject_id' => $exam->subject_id,
            'title' => $exam->title,
            'classroom_ids' => $exam->classroom_ids,
            'opens_at' => $exam->opens_at->toIso8601String(),
            'closes_at' => $exam->closes_at->toIso8601String(),
            'duration_minutes' => $exam->duration_minutes,
            'device_modes' => $exam->device_modes,
            'status' => $exam->status->value,
        ];
    }
}
