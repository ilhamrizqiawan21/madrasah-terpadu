<?php

declare(strict_types=1);

namespace App\Services\Elearning;

use App\Models\Assignment;
use App\Models\Classroom;
use App\Models\Subject;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\AppTimezone;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Pengelola Tugas & PR per mapel dan rombel (T-1603).
 */
final class AssignmentService
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array{
     *     subject_id: int,
     *     classroom_id: int,
     *     title: string,
     *     description?: ?string,
     *     due_date: string,
     *     due_time: string,
     * }  $data
     */
    public function save(?Assignment $assignment, array $data, ?UploadedFile $attachment, User $actor): Assignment
    {
        $subject = Subject::query()->findOrFail((int) $data['subject_id']);
        $classroom = Classroom::query()->findOrFail((int) $data['classroom_id']);

        if ($subject->unit_id !== $classroom->unit_id) {
            throw ValidationException::withMessages([
                'classroom_id' => 'Mata pelajaran dan rombel harus berada pada unit yang sama.',
            ]);
        }

        $dueAt = CarbonImmutable::parse("{$data['due_date']} {$data['due_time']}", AppTimezone::name());

        return DB::transaction(function () use ($assignment, $data, $dueAt, $attachment, $actor): Assignment {
            $oldPath = $assignment?->attachment_path;
            $newPath = $oldPath;

            if ($attachment !== null) {
                $stored = Storage::disk('local')->putFile('assignments', $attachment);
                $newPath = $stored !== false ? $stored : $oldPath;
            }

            $before = $assignment !== null ? [
                'title' => $assignment->title,
                'subject_id' => $assignment->subject_id,
                'classroom_id' => $assignment->classroom_id,
                'due_at' => $assignment->due_at->toIso8601String(),
            ] : null;

            $saved = $assignment ?? new Assignment(['teacher_id' => $actor->id]);
            $saved->forceFill([
                'subject_id' => (int) $data['subject_id'],
                'classroom_id' => (int) $data['classroom_id'],
                'title' => trim($data['title']),
                'description' => isset($data['description']) && trim($data['description']) !== ''
                    ? trim($data['description'])
                    : null,
                'due_at' => $dueAt,
                'attachment_path' => $newPath,
            ])->save();

            if ($attachment !== null && $oldPath !== null && $oldPath !== $newPath) {
                Storage::disk('local')->delete($oldPath);
            }

            $this->audit->record(
                $assignment === null ? 'assignment.created' : 'assignment.updated',
                $saved,
                $before,
                [
                    'title' => $saved->title,
                    'subject_id' => $saved->subject_id,
                    'classroom_id' => $saved->classroom_id,
                    'due_at' => $saved->due_at->toIso8601String(),
                ],
            );

            return $saved;
        });
    }

    public function delete(Assignment $assignment, User $actor): void
    {
        DB::transaction(function () use ($assignment, $actor): void {
            $before = [
                'title' => $assignment->title,
                'subject_id' => $assignment->subject_id,
                'classroom_id' => $assignment->classroom_id,
            ];
            $path = $assignment->attachment_path;

            $assignment->delete();

            if ($path !== null) {
                Storage::disk('local')->delete($path);
            }

            $this->audit->record('assignment.deleted', $assignment, $before, ['deleted_by' => $actor->id]);
        });
    }
}
