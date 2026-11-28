<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Booking satu ruangan/lab pada satu tanggal dan rentang jam.
 *
 * @property int $id
 * @property int $room_id
 * @property int $user_id
 * @property CarbonImmutable $used_on
 * @property string $starts_at
 * @property string $ends_at
 * @property string|null $purpose
 * @property-read Room $room
 * @property-read User $user
 */
#[Fillable(['room_id', 'user_id', 'used_on', 'starts_at', 'ends_at', 'purpose'])]
class RoomBooking extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['used_on' => 'immutable_date'];
    }

    /**
     * @return BelongsTo<Room, $this>
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
