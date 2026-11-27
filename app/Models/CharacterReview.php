<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CharacterReviewStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Status penilaian karakter satu rombel satu semester. Ditulis lewat CharacterAssessmentService.
 *
 * @property int $id
 * @property int $classroom_id
 * @property int $academic_year_id
 * @property CharacterReviewStatus $status
 * @property Carbon|null $computed_at
 * @property int|null $submitted_by
 * @property Carbon|null $submitted_at
 * @property int|null $approved_by
 * @property Carbon|null $approved_at
 * @property int|null $reopened_by
 * @property Carbon|null $reopened_at
 * @property string|null $reopen_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Classroom $classroom
 * @property-read AcademicYear $academicYear
 * @property-read User|null $submitter
 * @property-read User|null $approver
 * @property-read Collection<int, CharacterAssessment> $assessments
 */
#[Fillable(['classroom_id', 'academic_year_id', 'status', 'computed_at', 'submitted_by', 'submitted_at', 'approved_by', 'approved_at', 'reopened_by', 'reopened_at', 'reopen_reason'])]
class CharacterReview extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CharacterReviewStatus::class,
            'computed_at' => 'datetime',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'reopened_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Classroom, $this>
     */
    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    /**
     * @return BelongsTo<AcademicYear, $this>
     */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return HasMany<CharacterAssessment, $this>
     */
    public function assessments(): HasMany
    {
        return $this->hasMany(CharacterAssessment::class);
    }
}
