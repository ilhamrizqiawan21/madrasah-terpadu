<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property CarbonImmutable $triggered_at
 * @property CarbonImmutable|null $done_at
 * @property-read Student $student
 * @property-read AcademicYear $academicYear
 * @property-read DisciplineThreshold $threshold
 * @property-read User|null $doneByUser
 */
class DisciplineFollowup extends Model
{
    protected $fillable = [
        'student_id',
        'academic_year_id',
        'discipline_threshold_id',
        'triggered_at',
        'status',
        'done_by',
        'done_at',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'triggered_at' => 'datetime',
            'done_at' => 'datetime',
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
     * @return BelongsTo<DisciplineThreshold, $this>
     */
    public function threshold(): BelongsTo
    {
        return $this->belongsTo(DisciplineThreshold::class, 'discipline_threshold_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function doneByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'done_by');
    }
}
