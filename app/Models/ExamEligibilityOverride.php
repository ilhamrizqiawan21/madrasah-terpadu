<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Dispensasi kelayakan ujian oleh admin (T-612, ADR-016).
 *
 * @property int $id
 * @property int $exam_period_id
 * @property int $student_id
 * @property string $reason
 * @property int $granted_by
 * @property CarbonImmutable $granted_at
 * @property int|null $revoked_by
 * @property CarbonImmutable|null $revoked_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read ExamPeriod $examPeriod
 * @property-read Student $student
 * @property-read User $granter
 * @property-read User|null $revoker
 */
#[Fillable([
    'exam_period_id',
    'student_id',
    'reason',
    'granted_by',
    'granted_at',
    'revoked_by',
    'revoked_at',
])]
class ExamEligibilityOverride extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'granted_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<ExamPeriod, $this>
     */
    public function examPeriod(): BelongsTo
    {
        return $this->belongsTo(ExamPeriod::class);
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
    public function granter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function revoker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }
}
