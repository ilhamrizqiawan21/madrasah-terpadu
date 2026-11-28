<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Slot jam pelajaran per unit (jam HH:MM WITA). Ditulis lewat App\Services\Academic\LessonScheduleService.
 *
 * @property int $id
 * @property int $unit_id
 * @property string $name
 * @property string $starts_at
 * @property string $ends_at
 * @property int $sort_order
 * @property bool $is_break
 * @property-read Unit $unit
 */
#[Fillable(['unit_id', 'name', 'starts_at', 'ends_at', 'sort_order', 'is_break'])]
class LessonPeriod extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['sort_order' => 'integer', 'is_break' => 'boolean'];
    }

    /**
     * @return BelongsTo<Unit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
