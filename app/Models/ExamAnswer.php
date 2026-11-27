<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Jawaban siswa per soal (T-1505). Upsert idempoten per (exam_session_id, exam_question_id).
 *
 * @property int $id
 * @property int $exam_session_id
 * @property int $exam_question_id
 * @property array<string, mixed>|null $answer
 * @property bool $is_flagged
 * @property CarbonImmutable $client_ts
 * @property CarbonImmutable $received_at
 */
class ExamAnswer extends Model
{
    protected $fillable = [
        'exam_session_id',
        'exam_question_id',
        'answer',
        'is_flagged',
        'client_ts',
        'received_at',
    ];

    protected function casts(): array
    {
        return [
            'answer' => 'array',
            'is_flagged' => 'boolean',
            'client_ts' => 'immutable_datetime',
            'received_at' => 'immutable_datetime',
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
}
