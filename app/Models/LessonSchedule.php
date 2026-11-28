<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu sel jadwal mingguan: rombel × hari (1 Senin … 6 Sabtu) × slot jam.
 *
 * @property int $id
 * @property int $classroom_id
 * @property int $weekday
 * @property int $lesson_period_id
 * @property int $subject_id
 * @property int $teacher_id
 * @property int|null $room_id
 * @property-read Classroom $classroom
 * @property-read LessonPeriod $period
 * @property-read Subject $subject
 * @property-read User $teacher
 * @property-read Room|null $room
 */
#[Fillable(['classroom_id', 'weekday', 'lesson_period_id', 'subject_id', 'teacher_id', 'room_id'])]
class LessonSchedule extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['weekday' => 'integer'];
    }

    /**
     * @return BelongsTo<Classroom, $this>
     */
    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    /**
     * @return BelongsTo<LessonPeriod, $this>
     */
    public function period(): BelongsTo
    {
        return $this->belongsTo(LessonPeriod::class, 'lesson_period_id');
    }

    /**
     * @return BelongsTo<Subject, $this>
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    /**
     * @return BelongsTo<Room, $this>
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }
}
