<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Dimensi penilaian karakter per unit. Tidak dihapus bila sudah dipakai, hanya dinonaktifkan.
 *
 * @property int $id
 * @property int $unit_id
 * @property string $code
 * @property string $name
 * @property int $position
 * @property bool $is_active
 * @property array<string, string> $description_templates
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, CharacterComponent> $components
 */
#[Fillable(['unit_id', 'code', 'name', 'position', 'is_active', 'description_templates'])]
class CharacterDimension extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'is_active' => 'boolean',
            'description_templates' => 'array',
        ];
    }

    /**
     * @return HasMany<CharacterComponent, $this>
     */
    public function components(): HasMany
    {
        return $this->hasMany(CharacterComponent::class)->orderBy('id');
    }
}
