<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AttendanceDecider;
use App\Enums\AttendanceStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Status presensi harian per siswa per tanggal sekolah WITA (T-502).
 *
 * @property int $id
 * @property int $student_id
 * @property CarbonImmutable $school_date
 * @property AttendanceStatus $status
 * @property CarbonImmutable|null $checked_in_at
 * @property CarbonImmutable|null $checked_out_at
 * @property int|null $leave_request_id
 * @property AttendanceDecider $decided_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Student $student
 */
#[Fillable(['student_id', 'school_date', 'status', 'checked_in_at', 'checked_out_at', 'leave_request_id', 'decided_by'])]
class AttendanceDay extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'school_date' => 'immutable_date',
            'status' => AttendanceStatus::class,
            'checked_in_at' => 'immutable_datetime',
            'checked_out_at' => 'immutable_datetime',
            'decided_by' => AttendanceDecider::class,
        ];
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
