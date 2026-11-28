<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ExamDelivery;
use App\Enums\ExamPeriodStatus;
use Carbon\CarbonImmutable;
use Database\Factories\ExamPeriodFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Periode ujian untuk penentuan syarat kelayakan tagihan (T-611, ADR-016).
 *
 * @property int $id
 * @property int $unit_id
 * @property string $name
 * @property ExamDelivery $delivery
 * @property CarbonImmutable $starts_on
 * @property CarbonImmutable $ends_on
 * @property ExamPeriodStatus $status
 * @property int $created_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Unit $unit
 * @property-read User $creator
 * @property-read Collection<int, ExamPeriodRequirement> $requirements
 */
#[Fillable([
    'unit_id',
    'name',
    'delivery',
    'starts_on',
    'ends_on',
    'status',
    'created_by',
])]
class ExamPeriod extends Model
{
    /** @use HasFactory<ExamPeriodFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'delivery' => ExamDelivery::class,
            'starts_on' => 'immutable_date',
            'ends_on' => 'immutable_date',
            'status' => ExamPeriodStatus::class,
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
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<ExamPeriodRequirement, $this>
     */
    public function requirements(): HasMany
    {
        return $this->hasMany(ExamPeriodRequirement::class);
    }
}
