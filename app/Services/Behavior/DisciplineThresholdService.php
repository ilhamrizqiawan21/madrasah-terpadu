<?php

declare(strict_types=1);

namespace App\Services\Behavior;

use App\Models\DisciplineThreshold;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DisciplineThresholdService
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $actor, array $data): DisciplineThreshold
    {
        $normalized = $this->normalizeAndValidate($data);

        return DB::transaction(function () use ($actor, $normalized): DisciplineThreshold {
            $threshold = DisciplineThreshold::query()->create($normalized);

            $this->audit->record(
                action: 'threshold.created',
                subject: $threshold,
                after: $threshold->only(['unit_id', 'points', 'action_name', 'notify_guardian', 'is_active']),
                userId: $actor->id,
            );

            return $threshold;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $actor, DisciplineThreshold $threshold, array $data): DisciplineThreshold
    {
        $normalized = $this->normalizeAndValidate($data, $threshold->id);

        return DB::transaction(function () use ($actor, $threshold, $normalized): DisciplineThreshold {
            $before = $threshold->only(['unit_id', 'points', 'action_name', 'notify_guardian', 'is_active']);
            $threshold->update($normalized);

            $this->audit->record(
                action: 'threshold.updated',
                subject: $threshold,
                before: $before,
                after: $threshold->only(['unit_id', 'points', 'action_name', 'notify_guardian', 'is_active']),
                userId: $actor->id,
            );

            return $threshold;
        });
    }

    public function toggleActive(User $actor, DisciplineThreshold $threshold, bool $isActive): DisciplineThreshold
    {
        return DB::transaction(function () use ($actor, $threshold, $isActive): DisciplineThreshold {
            $before = ['is_active' => $threshold->is_active];
            $threshold->update(['is_active' => $isActive]);

            $this->audit->record(
                action: $isActive ? 'threshold.activated' : 'threshold.deactivated',
                subject: $threshold,
                before: $before,
                after: ['is_active' => $threshold->is_active],
                userId: $actor->id,
            );

            return $threshold;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{unit_id: int|null, points: int, action_name: string, notify_guardian: bool, is_active: bool}
     */
    private function normalizeAndValidate(array $data, ?int $ignoreId = null): array
    {
        $unitId = isset($data['unit_id']) && $data['unit_id'] !== '' ? (int) $data['unit_id'] : null;
        $points = (int) ($data['points'] ?? 0);

        if ($points <= 0) {
            throw ValidationException::withMessages([
                'points' => 'Ambang poin harus lebih dari 0.',
            ]);
        }

        $duplicate = DisciplineThreshold::query()
            ->where('points', $points)
            ->when(
                $unitId === null,
                fn ($q) => $q->whereNull('unit_id'),
                fn ($q) => $q->where('unit_id', $unitId)
            )
            ->when($ignoreId !== null, fn ($q) => $q->whereKeyNot($ignoreId))
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'points' => 'Ambang poin tersebut sudah ada pada unit ini.',
            ]);
        }

        return [
            'unit_id' => $unitId,
            'points' => $points,
            'action_name' => trim((string) ($data['action_name'] ?? '')),
            'notify_guardian' => (bool) ($data['notify_guardian'] ?? true),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ];
    }
}
