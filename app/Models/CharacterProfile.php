<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Curriculum;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Profil penilaian karakter satu unit (ADR-029). Ditulis lewat CharacterSettingsService.
 *
 * @property int $id
 * @property int $unit_id
 * @property Curriculum $curriculum
 * @property list<array{code: string, label: string, min: int}> $scale
 * @property int $coverage_min_pct
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Unit $unit
 */
#[Fillable(['unit_id', 'curriculum', 'scale', 'coverage_min_pct', 'updated_by'])]
class CharacterProfile extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'curriculum' => Curriculum::class,
            'scale' => 'array',
            'coverage_min_pct' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Unit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
