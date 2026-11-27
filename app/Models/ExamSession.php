<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ExamSessionStatus;
use Carbon\CarbonImmutable;
use Database\Factories\ExamSessionFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Sesi ujian siswa (T-1505, ADR-020).
 *
 * @property int $id
 * @property int $exam_id
 * @property int $student_id
 * @property CarbonImmutable $started_at
 * @property CarbonImmutable $deadline_at
 * @property int $extra_minutes
 * @property ExamSessionStatus $status
 * @property string $device_type
 * @property string $device_token
 * @property int $shuffle_seed
 * @property CarbonImmutable|null $last_seen_at
 * @property int $takeover_count
 * @property-read Exam $exam
 * @property-read Student $student
 * @property-read Collection<int, ExamAnswer> $answers
 */
class ExamSession extends Model
{
    /** @use HasFactory<ExamSessionFactory> */
    use HasFactory;

    protected $fillable = [
        'exam_id',
        'student_id',
        'started_at',
        'deadline_at',
        'extra_minutes',
        'status',
        'device_type',
        'device_token',
        'shuffle_seed',
        'last_seen_at',
        'takeover_count',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'immutable_datetime',
            'deadline_at' => 'immutable_datetime',
            'extra_minutes' => 'integer',
            'status' => ExamSessionStatus::class,
            'shuffle_seed' => 'integer',
            'last_seen_at' => 'immutable_datetime',
            'takeover_count' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Exam, $this>
     */
    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return HasMany<ExamAnswer, $this>
     */
    public function answers(): HasMany
    {
        return $this->hasMany(ExamAnswer::class);
    }

    /**
     * @return HasMany<ExamEssayScore, $this>
     */
    public function essayScores(): HasMany
    {
        return $this->hasMany(ExamEssayScore::class);
    }

    /**
     * @return HasMany<ExamViolation, $this>
     */
    public function violations(): HasMany
    {
        return $this->hasMany(ExamViolation::class);
    }

    /**
     * @return HasOne<ExamResult, $this>
     */
    public function result(): HasOne
    {
        return $this->hasOne(ExamResult::class);
    }
}
