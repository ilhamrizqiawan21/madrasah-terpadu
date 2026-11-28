<?php

declare(strict_types=1);

namespace App\Services\Academic;

use App\Models\Room;
use App\Models\RoomBooking;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Booking ruangan: satu ruangan tidak boleh dipakai dua booking yang jamnya beririsan di tanggal yang sama.
 */
final class RoomBookingService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function book(Room $room, string $date, string $startsAt, string $endsAt, ?string $purpose, User $actor): RoomBooking
    {
        if ($startsAt >= $endsAt) {
            throw ValidationException::withMessages(['ends_at' => 'Jam selesai harus setelah jam mulai.']);
        }

        return DB::transaction(function () use ($room, $date, $startsAt, $endsAt, $purpose, $actor): RoomBooking {
            // Kunci baris ruangan agar dua booking bersamaan diperiksa berurutan.
            Room::query()->whereKey($room->id)->lockForUpdate()->first();

            $clash = RoomBooking::query()->with('user:id,name')
                ->where('room_id', $room->id)
                ->whereDate('used_on', $date)
                ->where('starts_at', '<', $endsAt)
                ->where('ends_at', '>', $startsAt)
                ->first();

            if ($clash !== null) {
                throw ValidationException::withMessages([
                    'starts_at' => "Ruangan sudah dibooking {$clash->user->name} pukul ".str_replace(':', '.', $clash->starts_at).'–'.str_replace(':', '.', $clash->ends_at).'.',
                ]);
            }

            $booking = RoomBooking::query()->create([
                'room_id' => $room->id, 'user_id' => $actor->id, 'used_on' => $date,
                'starts_at' => $startsAt, 'ends_at' => $endsAt, 'purpose' => $purpose,
            ]);
            $this->audit->record('room_booking.created', $booking, null, $booking->only(['room_id', 'used_on', 'starts_at', 'ends_at']), $actor->id);

            return $booking;
        });
    }

    public function cancel(RoomBooking $booking, User $actor): void
    {
        DB::transaction(function () use ($booking, $actor): void {
            $this->audit->record('room_booking.cancelled', $booking, $booking->only(['room_id', 'user_id', 'starts_at', 'ends_at']), null, $actor->id);
            $booking->delete();
        });
    }
}
