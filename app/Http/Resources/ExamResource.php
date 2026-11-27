<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Exam;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Exam
 */
class ExamResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'exam_period_id' => $this->exam_period_id,
            'subject_id' => $this->subject_id,
            'title' => $this->title,
            'grade' => $this->grade,
            'classroom_ids' => $this->classroom_ids,
            'opens_at' => $this->opens_at->toIso8601String(),
            'closes_at' => $this->closes_at->toIso8601String(),
            'duration_minutes' => $this->duration_minutes,
            'shuffle_questions' => $this->shuffle_questions,
            'shuffle_options' => $this->shuffle_options,
            'weights' => $this->weights,
            'access_token' => $this->access_token,
            'violation_limit' => $this->violation_limit,
            'device_modes' => $this->device_modes,
            'show_result_to_guardian' => $this->show_result_to_guardian,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'locked_at' => $this->locked_at?->toIso8601String(),
            'subject' => $this->whenLoaded('subject', fn (): array => [
                'id' => $this->subject->id,
                'code' => $this->subject->code,
                'name' => $this->subject->name,
            ]),
            'creator' => $this->whenLoaded('creator', fn (): array => [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ]),
        ];
    }
}
