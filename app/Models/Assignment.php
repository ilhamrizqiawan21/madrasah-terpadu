<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Tugas & PR per mapel dan rombel (T-1603).
 *
 * @property int $id
 * @property int $subject_id
 * @property int $classroom_id
 * @property int $teacher_id
 * @property string $title
 * @property string|null $description
 * @property CarbonImmutable $due_at
 * @property string|null $attachment_path
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Subject $subject
 * @property-read Classroom $classroom
 * @property-read User $teacher
 */
#[Fillable([
    'subject_id',
    'classroom_id',
    'teacher_id',
    'title',
    'description',
    'due_at',
    'attachment_path',
])]
class Assignment extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'due_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<Subject, $this>
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /**
     * @return BelongsTo<Classroom, $this>
     */
    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }
}
