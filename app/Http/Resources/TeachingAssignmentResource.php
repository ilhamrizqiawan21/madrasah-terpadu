<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\TeachingAssignment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TeachingAssignment
 */
class TeachingAssignmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'teacher' => $this->whenLoaded('teacher', fn (): array => [
                'id' => $this->teacher->id,
                'name' => $this->teacher->name,
            ]),
            'subject' => $this->whenLoaded('subject', fn (): array => [
                'id' => $this->subject->id,
                'code' => $this->subject->code,
                'name' => $this->subject->name,
            ]),
            'classroom' => $this->whenLoaded('classroom', fn (): array => [
                'id' => $this->classroom->id,
                'name' => $this->classroom->name,
                'grade' => $this->classroom->grade,
            ]),
            'academic_year' => $this->whenLoaded('academicYear', fn (): array => [
                'id' => $this->academicYear->id,
                'label' => $this->academicYear->label(),
            ]),
        ];
    }
}
