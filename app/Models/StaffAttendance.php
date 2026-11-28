<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Rekap presensi harian pegawai (T-1601). Ditulis hanya lewat StaffAttendanceService.
 *
 * @property int $id
 * @property int $user_id
 * @property CarbonImmutable $work_date
 * @property CarbonImmutable|null $check_in_at
 * @property CarbonImmutable|null $check_out_at
 * @property string $status
 * @property string $source
 * @property string|null $note
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read User $user
 */
#[Fillable(['user_id', 'work_date', 'check_in_at', 'check_out_at', 'status', 'source', 'note'])]
class StaffAttendance extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'work_date' => 'immutable_date',
            'check_in_at' => 'immutable_datetime',
            'check_out_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
