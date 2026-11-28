<?php

declare(strict_types=1);

namespace App\Services\Users;

use App\Models\Meeting;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Penulis tunggal agenda rapat & notulen (Pengguna > Undangan Rapat).
 */
final class MeetingService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array{unit_id: ?int, title: string, held_on: string, audience: string, minutes: ?string}  $data
     */
    public function create(array $data, User $actor): Meeting
    {
        return DB::transaction(function () use ($data, $actor): Meeting {
            $meeting = Meeting::query()->create([...$data, 'created_by' => $actor->id]);
            $this->audit->record('meeting.created', $meeting, null, $this->snapshot($meeting), $actor->id);

            return $meeting;
        });
    }

    /**
     * @param  array{unit_id: ?int, title: string, held_on: string, audience: string, minutes: ?string}  $data
     */
    public function update(Meeting $meeting, array $data, User $actor): Meeting
    {
        return DB::transaction(function () use ($meeting, $data, $actor): Meeting {
            $before = $this->snapshot($meeting);
            $meeting->update($data);
            $this->audit->record('meeting.updated', $meeting, $before, $this->snapshot($meeting), $actor->id);

            return $meeting;
        });
    }

    public function delete(Meeting $meeting, User $actor): void
    {
        DB::transaction(function () use ($meeting, $actor): void {
            $this->audit->record('meeting.deleted', $meeting, $this->snapshot($meeting), null, $actor->id);
            $meeting->delete();
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Meeting $meeting): array
    {
        return [
            'unit_id' => $meeting->unit_id,
            'title' => $meeting->title,
            'held_on' => $meeting->held_on->toDateString(),
            'audience' => $meeting->audience->value,
            'has_minutes' => $meeting->minutes !== null,
        ];
    }
}
