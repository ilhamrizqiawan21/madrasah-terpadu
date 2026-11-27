<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\MemorizationTargetFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property list<array{surah: int, ayah_from: int, ayah_to: int}>|null $ranges
 * @property list<int>|null $material_ids
 * @property-read Unit $unit
 * @property-read AcademicYear $academicYear
 */
class MemorizationTarget extends Model
{
    /** @use HasFactory<MemorizationTargetFactory> */
    use HasFactory;

    protected $fillable = [
        'unit_id',
        'grade',
        'academic_year_id',
        'kind',
        'title',
        'ranges',
        'material_ids',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'ranges' => 'array',
            'material_ids' => 'array',
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
     * @return BelongsTo<AcademicYear, $this>
     */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
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
