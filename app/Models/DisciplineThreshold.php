<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\DisciplineThresholdFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read Unit|null $unit
 */
class DisciplineThreshold extends Model
{
    /** @use HasFactory<DisciplineThresholdFactory> */
    use HasFactory;

    protected $fillable = [
        'unit_id',
        'points',
        'action_name',
        'notify_guardian',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'points' => 'integer',
            'notify_guardian' => 'boolean',
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
