<?php

declare(strict_types=1);

namespace App\Services\Cbt;

use App\Enums\Role;
use App\Models\TeachingAssignment;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Satu-satunya penulis penugasan guru × mapel × rombel × tahun ajaran (T-1501).
 */
final class TeachingAssignmentService
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /**
     * Guru aktif yang dapat ditugaskan mengajar.
     *
     * @return Builder<User>
     */
    public static function teacherCandidates(): Builder
    {
        return User::query()->role(Role::Teacher->value)->where('is_active', true);
    }

    /**
     * @param  array{user_id: int, subject_id: int, classroom_id: int, academic_year_id: int}  $data
     */
    public function create(array $data, User $actor): TeachingAssignment
    {
        return DB::transaction(function () use ($data, $actor): TeachingAssignment {
            $assignment = TeachingAssignment::query()->create($data);

            $this->auditLogger->record(
                action: 'created',
                subject: $assignment,
                after: $this->snapshot($assignment),
                userId: $actor->id,
            );

            return $assignment;
        });
    }

    public function delete(TeachingAssignment $assignment, User $actor): void
    {
        DB::transaction(function () use ($assignment, $actor): void {
            $before = $this->snapshot($assignment);

            $this->auditLogger->record(
                action: 'deleted',
                subject: $assignment,
                before: $before,
                userId: $actor->id,
            );

            $assignment->delete();
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(TeachingAssignment $assignment): array
    {
        return [
            'user_id' => $assignment->user_id,
            'subject_id' => $assignment->subject_id,
            'classroom_id' => $assignment->classroom_id,
            'academic_year_id' => $assignment->academic_year_id,
        ];
    }
}
