<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Nilai essay kertas per soal per sesi (T-1507).
 *
 * @property int $id
 * @property int $exam_session_id
 * @property int $exam_question_id
 * @property int $score
 * @property int $scored_by
 */
class ExamEssayScore extends Model
{
    protected $fillable = [
        'exam_session_id',
        'exam_question_id',
        'score',
        'scored_by',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<ExamSession, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(ExamSession::class, 'exam_session_id');
    }

    /**
     * @return BelongsTo<ExamQuestion, $this>
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(ExamQuestion::class, 'exam_question_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function scorer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scored_by');
    }
}
