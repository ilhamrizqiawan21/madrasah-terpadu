<?php

declare(strict_types=1);

namespace App\Services\Units;

use App\Models\Unit;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Satu-satunya penulis unit dari aplikasi (T-202). Unit tidak dihapus, hanya dinonaktifkan,
 * karena siswa, rombel, dan tagihan menunjuk ke unit.
 */
final class UnitService
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /**
     * @param  array{code: string, name: string, attendance_settings: array{check_in_opens_at: string, late_after: string, absent_after: string, check_out_opens_at: string}}  $data
     */
    public function create(array $data, User $actor): Unit
    {
        return DB::transaction(function () use ($data, $actor): Unit {
            $unit = Unit::query()->create([...$data, 'is_active' => true]);

            $this->auditLogger->record(
                action: 'created',
                subject: $unit,
                after: $this->snapshot($unit),
                userId: $actor->id,
            );

            return $unit;
        });
    }

    /**
     * @param  array{code: string, name: string, attendance_settings: array{check_in_opens_at: string, late_after: string, absent_after: string, check_out_opens_at: string}}  $data
     */
    public function update(Unit $unit, array $data, User $actor): Unit
    {
        return DB::transaction(function () use ($unit, $data, $actor): Unit {
            $before = $this->snapshot($unit);

            $unit->fill($data)->save();

            $this->auditLogger->record(
                action: 'updated',
                subject: $unit,
                before: $before,
                after: $this->snapshot($unit),
                userId: $actor->id,
            );

            return $unit;
        });
    }

    public function setActive(Unit $unit, bool $active, User $actor): Unit
    {
        if ($unit->is_active === $active) {
            return $unit;
        }

        return DB::transaction(function () use ($unit, $active, $actor): Unit {
            $unit->forceFill(['is_active' => $active])->save();

            $this->auditLogger->record(
                action: $active ? 'activated' : 'deactivated',
                subject: $unit,
                before: ['is_active' => ! $active],
                after: ['is_active' => $active],
                userId: $actor->id,
            );

            return $unit;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Unit $unit): array
    {
        return [
            'code' => $unit->code,
            'name' => $unit->name,
            'attendance_settings' => $unit->attendance_settings,
            'is_active' => $unit->is_active,
        ];
    }
}
