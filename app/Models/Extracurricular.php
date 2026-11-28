<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Kegiatan ekstrakurikuler per unit dengan pembina guru internal atau pelatih luar.
 *
 * @property int $id
 * @property int $unit_id
 * @property string $name
 * @property string $coach_type internal|external
 * @property int|null $coach_user_id
 * @property string|null $coach_name
 * @property-read Unit $unit
 * @property-read User|null $coach
 */
#[Fillable(['unit_id', 'name', 'coach_type', 'coach_user_id', 'coach_name'])]
class Extracurricular extends Model
{
    /**
     * @return BelongsTo<Unit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function coach(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coach_user_id');
    }

    /**
     * @return HasMany<ExtracurricularMember, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(ExtracurricularMember::class);
    }

    public function coachLabel(): string
    {
        return $this->coach_type === 'internal'
            ? ($this->coach->name ?? '—')
            : (($this->coach_name ?? '—').' (pelatih luar)');
    }
}
