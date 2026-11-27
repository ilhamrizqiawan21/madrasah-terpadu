<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\MemorizationMaterialFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read Unit|null $unit
 */
class MemorizationMaterial extends Model
{
    /** @use HasFactory<MemorizationMaterialFactory> */
    use HasFactory;

    protected $fillable = [
        'unit_id',
        'kind',
        'title',
        'arabic_text',
        'translation',
        'source',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Unit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
