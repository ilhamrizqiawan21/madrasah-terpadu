<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\HabitAspect;
use Database\Factories\HabitItemFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property HabitAspect $aspect
 * @property-read Unit|null $unit
 */
class HabitItem extends Model
{
    /** @use HasFactory<HabitItemFactory> */
    use HasFactory;

    protected $fillable = [
        'unit_id',
        'name',
        'aspect',
        'sort_order',
        'is_active',
        'is_locked',
    ];

    protected function casts(): array
    {
        return [
            'aspect' => HabitAspect::class,
            'sort_order' => 'integer',
            'is_active' => 'boolean',
            'is_locked' => 'boolean',
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
