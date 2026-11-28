<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Master Akademik per unit. Ditulis lewat App\Services\Academic\AcademicMasterService.
 *
 * @property int $id
 * @property int $unit_id
 * @property string $name
 * @property-read Unit $unit
 */
#[Fillable(['unit_id', 'name'])]
class Major extends Model
{
    /**
     * @return BelongsTo<Unit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
