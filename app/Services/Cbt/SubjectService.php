<?php

declare(strict_types=1);

namespace App\Services\Cbt;

use App\Models\Subject;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Satu-satunya penulis mata pelajaran (T-1501). Mapel tidak dihapus, hanya dinonaktifkan.
 */
final class SubjectService
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /**
     * @param  array{unit_id: int, code: string, name: string}  $data
     */
    public function create(array $data, User $actor): Subject
    {
        return DB::transaction(function () use ($data, $actor): Subject {
            $subject = Subject::query()->create([
                'unit_id' => $data['unit_id'],
                'code' => mb_strtoupper(trim($data['code'])),
                'name' => trim($data['name']),
                'is_active' => true,
            ]);

            $this->auditLogger->record(
                action: 'created',
                subject: $subject,
                after: $this->snapshot($subject),
                userId: $actor->id,
            );

            return $subject;
        });
    }

    /**
     * @param  array{code: string, name: string}  $data
     */
    public function update(Subject $subject, array $data, User $actor): Subject
    {
        return DB::transaction(function () use ($subject, $data, $actor): Subject {
            $before = $this->snapshot($subject);

            $subject->fill([
                'code' => mb_strtoupper(trim($data['code'])),
                'name' => trim($data['name']),
            ])->save();

            $this->auditLogger->record(
                action: 'updated',
                subject: $subject,
                before: $before,
                after: $this->snapshot($subject),
                userId: $actor->id,
            );

            return $subject;
        });
    }

    public function setActive(Subject $subject, bool $active, User $actor): Subject
    {
        if ($subject->is_active === $active) {
            return $subject;
        }

        return DB::transaction(function () use ($subject, $active, $actor): Subject {
            $subject->forceFill(['is_active' => $active])->save();

            $this->auditLogger->record(
                action: $active ? 'activated' : 'deactivated',
                subject: $subject,
                before: ['is_active' => ! $active],
                after: ['is_active' => $active],
                userId: $actor->id,
            );

            return $subject;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Subject $subject): array
    {
        return [
            'unit_id' => $subject->unit_id,
            'code' => $subject->code,
            'name' => $subject->name,
            'is_active' => $subject->is_active,
        ];
    }
}
