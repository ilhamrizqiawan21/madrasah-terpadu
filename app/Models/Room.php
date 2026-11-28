<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ruang kelas / laboratorium dalam satu gedung. Ditulis lewat App\Services\Academic\FacilityService.
 *
 * @property int $id
 * @property int $building_id
 * @property string $code
 * @property string $name
 * @property int $capacity
 * @property-read Building $building
 */
#[Fillable(['building_id', 'code', 'name', 'capacity'])]
class Room extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['capacity' => 'integer'];
    }

    /**
     * @return BelongsTo<Building, $this>
     */
    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class);
    }
}
