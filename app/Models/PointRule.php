<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PointCategory;
use Database\Factories\PointRuleFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property PointCategory $category
 * @property-read Unit|null $unit
 */
class PointRule extends Model
{
    /** @use HasFactory<PointRuleFactory> */
    use HasFactory;

    protected $fillable = [
        'unit_id',
        'code',
        'name',
        'category',
        'level',
        'points',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'category' => PointCategory::class,
            'points' => 'integer',
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

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeForUnit(Builder $query, ?int $unitId): Builder
    {
        if ($unitId === null) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($unitId): void {
            $q->where('unit_id', $unitId)->orWhereNull('unit_id');
        });
    }
}
