<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Unit;
use App\Support\AttendanceHours;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Unit
 */
class UnitResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'is_active' => $this->is_active,
            'attendance' => [...AttendanceHours::defaults(), ...$this->attendance_settings],
        ];
    }
}
