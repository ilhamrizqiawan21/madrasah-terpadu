<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\AcademicYear;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AcademicYear
 */
class AcademicYearResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'semester' => ['value' => $this->semester->value, 'label' => $this->semester->label()],
            'starts_on' => $this->starts_on->toDateString(),
            'ends_on' => $this->ends_on->toDateString(),
            'is_active' => $this->is_active,
        ];
    }
}
