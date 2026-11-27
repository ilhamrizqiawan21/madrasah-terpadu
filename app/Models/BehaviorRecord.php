<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BehaviorRecordStatus;
use App\Enums\PointCategory;
use Carbon\CarbonImmutable;
use Database\Factories\BehaviorRecordFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property PointCategory $category
 * @property BehaviorRecordStatus $status
 * @property CarbonImmutable $occurred_on
 * @property CarbonImmutable|null $voided_at
 * @property-read Student $student
 * @property-read AcademicYear $academicYear
 * @property-read PointRule $pointRule
 * @property-read User $recorder
 * @property-read User|null $voider
 */
class BehaviorRecord extends Model
{
    /** @use HasFactory<BehaviorRecordFactory> */
    use HasFactory;

    protected $fillable = [
        'student_id',
        'academic_year_id',
        'point_rule_id',
        'category',
        'points',
        'occurred_on',
        'note',
        'evidence_path',
        'recorded_by',
        'status',
        'voided_by',
        'voided_at',
        'void_reason',
    ];

    protected function casts(): array
    {
        return [
            'category' => PointCategory::class,
            'points' => 'integer',
            'occurred_on' => 'date',
            'status' => BehaviorRecordStatus::class,
            'voided_at' => 'datetime',
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
     * @return BelongsTo<AcademicYear, $this>
     */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    /**
     * @return BelongsTo<PointRule, $this>
     */
    public function pointRule(): BelongsTo
    {
        return $this->belongsTo(PointRule::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function voider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }
}
