<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pengajuan izin & cuti pegawai (T-1601). Ditulis hanya lewat StaffAttendanceService.
 *
 * @property int $id
 * @property int $user_id
 * @property string $type
 * @property CarbonImmutable $starts_on
 * @property CarbonImmutable $ends_on
 * @property string $reason
 * @property string $status
 * @property int|null $decided_by
 * @property CarbonImmutable|null $decided_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read User $user
 * @property-read User|null $decider
 */
#[Fillable(['user_id', 'type', 'starts_on', 'ends_on', 'reason', 'status', 'decided_by', 'decided_at'])]
class StaffLeaveRequest extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_on' => 'immutable_date',
            'ends_on' => 'immutable_date',
            'decided_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
