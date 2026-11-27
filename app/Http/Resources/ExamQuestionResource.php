<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\ExamQuestion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource soal untuk admin/guru pengampu (menyertakan kunci jawaban).
 *
 * @mixin ExamQuestion
 */
class ExamQuestionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'exam_id' => $this->exam_id,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'body' => $this->body,
            'is_rtl' => $this->is_rtl,
            'image_url' => $this->image_path !== null
                ? route('admin.cbt.questions.image', [$this->exam_id, $this->id], false)
                : null,
            'sort_order' => $this->sort_order,
            'answer_key' => $this->answer_key,
            'options' => $this->whenLoaded('options', fn () => $this->options->map(fn ($opt): array => [
                'id' => $opt->id,
                'label' => $opt->label,
                'body' => $opt->body,
                'sort_order' => $opt->sort_order,
            ])->values()),
        ];
    }
}
