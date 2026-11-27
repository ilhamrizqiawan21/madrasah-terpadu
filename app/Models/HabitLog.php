<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property CarbonImmutable $log_date
 * @property CarbonImmutable $submitted_at
 * @property CarbonImmutable|null $verified_at
 * @property-read Student $student
 * @property-read HabitItem $item
 * @property-read User $submitter
 * @property-read User|null $verifier
 */
class HabitLog extends Model
{
    protected $fillable = [
        'student_id',
        'habit_item_id',
        'log_date',
        'done',
        'submitted_by',
        'submitted_at',
        'verification',
        'verified_by',
        'verified_at',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'log_date' => 'date',
            'done' => 'boolean',
            'submitted_at' => 'datetime',
            'verified_at' => 'datetime',
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
     * @return BelongsTo<HabitItem, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(HabitItem::class, 'habit_item_id');
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
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
