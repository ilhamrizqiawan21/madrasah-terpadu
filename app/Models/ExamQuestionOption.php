<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pilihan / pasangan pada soal ujian CBT (T-1504).
 *
 * @property int $id
 * @property int $exam_question_id
 * @property string $label
 * @property string $body
 * @property int $sort_order
 */
class ExamQuestionOption extends Model
{
    protected $fillable = [
        'exam_question_id',
        'label',
        'body',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<ExamQuestion, $this>
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(ExamQuestion::class, 'exam_question_id');
    }
}
