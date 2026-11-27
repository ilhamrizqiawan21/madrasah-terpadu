<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\RewardOfferFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read Unit|null $unit
 */
class RewardOffer extends Model
{
    /** @use HasFactory<RewardOfferFactory> */
    use HasFactory;

    protected $fillable = [
        'unit_id',
        'name',
        'cost_points',
        'voucher_amount',
        'voucher_valid_days',
        'stock',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'cost_points' => 'integer',
            'voucher_amount' => 'integer',
            'voucher_valid_days' => 'integer',
            'stock' => 'integer',
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
