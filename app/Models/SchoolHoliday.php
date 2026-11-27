<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Hari libur atau acara sekolah (per unit atau semua unit, T-505).
 *
 * @property int $id
 * @property int|null $unit_id
 * @property CarbonImmutable $holiday_date
 * @property string $title
 * @property bool $is_day_off
 * @property int|null $created_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Unit|null $unit
 * @property-read User|null $creator
 */
#[Fillable(['unit_id', 'holiday_date', 'title', 'is_day_off', 'created_by'])]
class SchoolHoliday extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'holiday_date' => 'immutable_date',
            'is_day_off' => 'boolean',
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
}
