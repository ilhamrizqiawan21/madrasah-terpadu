<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ExamResultStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Hasil penilaian ujian CBT per sesi (T-1506).
 *
 * @property int $id
 * @property int $exam_session_id
 * @property int $auto_points
 * @property int|null $essay_points
 * @property int $max_points
 * @property int|null $score
 * @property ExamResultStatus $status
 * @property CarbonImmutable $computed_at
 */
class ExamResult extends Model
{
    protected $fillable = [
        'exam_session_id',
        'auto_points',
        'essay_points',
        'max_points',
        'score',
        'status',
        'computed_at',
    ];

    protected function casts(): array
    {
        return [
            'auto_points' => 'integer',
            'essay_points' => 'integer',
            'max_points' => 'integer',
            'score' => 'integer',
            'status' => ExamResultStatus::class,
            'computed_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<ExamSession, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(ExamSession::class, 'exam_session_id');
    }
}
