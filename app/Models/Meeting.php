<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MeetingAudience;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Agenda rapat & notulen. Ditulis hanya lewat App\Services\Users\MeetingService.
 *
 * @property int $id
 * @property int|null $unit_id
 * @property string $title
 * @property CarbonImmutable $held_on
 * @property MeetingAudience $audience
 * @property string|null $minutes
 * @property int|null $created_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Unit|null $unit
 * @property-read User|null $creator
 */
#[Fillable(['unit_id', 'title', 'held_on', 'audience', 'minutes', 'created_by'])]
class Meeting extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'held_on' => 'immutable_date',
            'audience' => MeetingAudience::class,
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
