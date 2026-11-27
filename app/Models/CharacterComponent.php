<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CharacterSource;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Komponen sumber data dalam satu dimensi; bobot semua komponen satu dimensi = 100.
 *
 * @property int $id
 * @property int $character_dimension_id
 * @property CharacterSource $source
 * @property int $weight
 * @property array<string, mixed> $params
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read CharacterDimension $dimension
 */
#[Fillable(['character_dimension_id', 'source', 'weight', 'params'])]
class CharacterComponent extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source' => CharacterSource::class,
            'weight' => 'integer',
            'params' => 'array',
        ];
    }

    /**
     * @return BelongsTo<CharacterDimension, $this>
     */
    public function dimension(): BelongsTo
    {
        return $this->belongsTo(CharacterDimension::class, 'character_dimension_id');
    }
}
