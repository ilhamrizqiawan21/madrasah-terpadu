<?php

declare(strict_types=1);

namespace App\Services\Academic;

use App\Models\LearningObjective;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

final class LearningObjectiveService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array{subject_id: int, grade: string, code: string, description: string}  $data
     */
    public function save(?LearningObjective $objective, array $data, User $actor): LearningObjective
    {
        return DB::transaction(function () use ($objective, $data, $actor): LearningObjective {
            $before = $objective?->only(array_keys($data));
            $objective ??= new LearningObjective;
            $objective->fill($data)->save();
            $this->audit->record($before === null ? 'learning_objective.created' : 'learning_objective.updated', $objective, $before, $objective->only(array_keys($data)), $actor->id);

            return $objective;
        });
    }

    public function delete(LearningObjective $objective, User $actor): void
    {
        DB::transaction(function () use ($objective, $actor): void {
            $this->audit->record('learning_objective.deleted', $objective, $objective->only(['subject_id', 'grade', 'code']), null, $actor->id);
            $objective->delete();
        });
    }
}
