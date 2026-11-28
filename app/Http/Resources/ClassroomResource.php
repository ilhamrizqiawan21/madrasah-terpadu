<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Classroom;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Classroom
 */
class ClassroomResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'grade' => $this->grade,
            'name' => $this->name,
            'major_id' => $this->major_id,
            'major_name' => $this->relationLoaded('major') ? $this->major?->name : null,
            'capacity' => $this->capacity,
            'is_active' => $this->is_active,
            'unit' => ['id' => $this->unit->id, 'code' => $this->unit->code],
            'academic_year' => ['id' => $this->academicYear->id, 'label' => $this->academicYear->label()],
            'homeroom_teacher' => $this->homeroomTeacher === null
                ? null
                : ['id' => $this->homeroomTeacher->id, 'name' => $this->homeroomTeacher->name],
        ];
    }
}
