<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MutationType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Catatan mutasi masuk/keluar/lulus siswa (append-only, T-212).
 *
 * @property int $id
 * @property int $student_id
 * @property MutationType $type
 * @property CarbonImmutable $mutated_on
 * @property string|null $school_name
 * @property string $reason
 * @property int $recorded_by
 * @property Carbon $created_at
 * @property-read Student $student
 * @property-read User $recorder
 */
#[Fillable([
    'student_id',
    'type',
    'mutated_on',
    'school_name',
    'reason',
    'recorded_by',
])]
class StudentMutation extends Model
{
    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => MutationType::class,
            'mutated_on' => 'immutable_date',
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
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
