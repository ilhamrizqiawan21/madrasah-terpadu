<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ExamQuestionType;
use Database\Factories\ExamQuestionFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Soal ujian CBT (T-1504). `answer_key` tidak pernah dikirim ke siswa.
 *
 * @property int $id
 * @property int $exam_id
 * @property ExamQuestionType $type
 * @property string $body
 * @property bool $is_rtl
 * @property string|null $image_path
 * @property int $sort_order
 * @property array<string, mixed>|null $answer_key
 * @property-read Collection<int, ExamQuestionOption> $options
 */
class ExamQuestion extends Model
{
    /** @use HasFactory<ExamQuestionFactory> */
    use HasFactory;

    protected $fillable = [
        'exam_id',
        'type',
        'body',
        'is_rtl',
        'image_path',
        'sort_order',
        'answer_key',
    ];

    protected $hidden = [
        'answer_key',
    ];

    protected function casts(): array
    {
        return [
            'type' => ExamQuestionType::class,
            'is_rtl' => 'boolean',
            'sort_order' => 'integer',
            'answer_key' => 'array',
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
     * @return HasMany<ExamQuestionOption, $this>
     */
    public function options(): HasMany
    {
        return $this->hasMany(ExamQuestionOption::class)->orderBy('sort_order')->orderBy('id');
    }
}
