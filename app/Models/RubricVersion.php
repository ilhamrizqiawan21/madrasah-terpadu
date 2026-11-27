<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property CarbonImmutable|null $published_at
 * @property-read Rubric $rubric
 */
class RubricVersion extends Model
{
    protected $fillable = [
        'rubric_id',
        'version_number',
        'pass_threshold',
        'published_at',
        'published_by',
    ];

    protected function casts(): array
    {
        return [
            'version_number' => 'integer',
            'pass_threshold' => 'float',
            'published_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Rubric, $this>
     */
    public function rubric(): BelongsTo
    {
        return $this->belongsTo(Rubric::class);
    }

    /**
     * @return HasMany<RubricCriterion, $this>
     */
    public function criteria(): HasMany
    {
        return $this->hasMany(RubricCriterion::class)->orderBy('sort_order');
    }
}
