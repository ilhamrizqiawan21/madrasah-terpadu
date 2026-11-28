<?php

declare(strict_types=1);

namespace App\Services\Academic;

use App\Models\Building;
use App\Models\Room;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Penulis tunggal master gedung dan ruangan (Akademik).
 */
final class FacilityService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array{unit_id: int, code: string, name: string}  $data
     */
    public function saveBuilding(?Building $building, array $data, User $actor): Building
    {
        return DB::transaction(function () use ($building, $data, $actor): Building {
            $before = $building?->only(['unit_id', 'code', 'name']);
            $building ??= new Building;
            $building->fill($data)->save();
            $this->audit->record($before === null ? 'building.created' : 'building.updated', $building, $before, $building->only(['unit_id', 'code', 'name']), $actor->id);

            return $building;
        });
    }

    public function deleteBuilding(Building $building, User $actor): void
    {
        if ($building->rooms()->exists()) {
            throw ValidationException::withMessages(['building' => 'Gedung masih memiliki ruangan. Hapus atau pindahkan ruangannya dulu.']);
        }

        DB::transaction(function () use ($building, $actor): void {
            $this->audit->record('building.deleted', $building, $building->only(['unit_id', 'code', 'name']), null, $actor->id);
            $building->delete();
        });
    }

    /**
     * @param  array{building_id: int, code: string, name: string, capacity: int}  $data
     */
    public function saveRoom(?Room $room, array $data, User $actor): Room
    {
        return DB::transaction(function () use ($room, $data, $actor): Room {
            $before = $room?->only(['building_id', 'code', 'name', 'capacity']);
            $room ??= new Room;
            $room->fill($data)->save();
            $this->audit->record($before === null ? 'room.created' : 'room.updated', $room, $before, $room->only(['building_id', 'code', 'name', 'capacity']), $actor->id);

            return $room;
        });
    }

    public function deleteRoom(Room $room, User $actor): void
    {
        DB::transaction(function () use ($room, $actor): void {
            $this->audit->record('room.deleted', $room, $room->only(['building_id', 'code', 'name']), null, $actor->id);
            $room->delete();
        });
    }
}
