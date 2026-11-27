<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\ExamQuestion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource soal untuk siswa ujian CBT. DILARANG mengirim `answer_key`.
 *
 * @mixin ExamQuestion
 */
class StudentExamQuestionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'body' => $this->body,
            'is_rtl' => $this->is_rtl,
            'image_url' => $this->image_path !== null
                ? route('exam.questions.image', [$this->exam_id, $this->id], false)
                : null,
            'options' => $this->whenLoaded('options', fn () => $this->options->map(fn ($opt): array => [
                'id' => $opt->id,
                'label' => $opt->label,
                'body' => $opt->body,
            ])->values()),
        ];
    }
}
