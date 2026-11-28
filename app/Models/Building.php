<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Gedung sekolah per unit. Ditulis lewat App\Services\Academic\FacilityService.
 *
 * @property int $id
 * @property int $unit_id
 * @property string $code
 * @property string $name
 * @property-read Unit $unit
 * @property-read Collection<int, Room> $rooms
 */
#[Fillable(['unit_id', 'code', 'name'])]
class Building extends Model
{
    /**
     * @return BelongsTo<Unit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * @return HasMany<Room, $this>
     */
    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }
}
