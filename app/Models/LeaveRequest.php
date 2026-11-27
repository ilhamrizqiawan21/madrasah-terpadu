<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LeaveRequestStatus;
use App\Enums\LeaveType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Pengajuan izin / sakit siswa dari wali (T-506).
 *
 * @property int $id
 * @property int $student_id
 * @property int $requested_by
 * @property LeaveType $type
 * @property CarbonImmutable $starts_on
 * @property CarbonImmutable $ends_on
 * @property string $reason
 * @property string|null $attachment_path
 * @property LeaveRequestStatus $status
 * @property int|null $decided_by
 * @property CarbonImmutable|null $decided_at
 * @property string|null $decision_note
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Student $student
 * @property-read User $requester
 * @property-read User|null $decider
 */
#[Fillable([
    'student_id', 'requested_by', 'type', 'starts_on', 'ends_on',
    'reason', 'attachment_path', 'status', 'decided_by', 'decided_at', 'decision_note',
])]
class LeaveRequest extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => LeaveType::class,
            'starts_on' => 'immutable_date',
            'ends_on' => 'immutable_date',
            'status' => LeaveRequestStatus::class,
            'decided_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
