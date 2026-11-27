<?php

declare(strict_types=1);

namespace App\Services\Behavior;

use App\Models\RewardOffer;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RewardOfferService
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $actor, array $data): RewardOffer
    {
        $payload = $this->validateAndNormalize($data);

        return DB::transaction(function () use ($actor, $payload): RewardOffer {
            $offer = RewardOffer::query()->create($payload);

            $this->audit->record(
                action: 'reward_offer.created',
                subject: $offer,
                after: $offer->only(['unit_id', 'name', 'cost_points', 'voucher_amount', 'voucher_valid_days', 'stock', 'is_active']),
                userId: $actor->id,
            );

            return $offer;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $actor, RewardOffer $offer, array $data): RewardOffer
    {
        $payload = $this->validateAndNormalize($data);

        return DB::transaction(function () use ($actor, $offer, $payload): RewardOffer {
            $before = $offer->only(['unit_id', 'name', 'cost_points', 'voucher_amount', 'voucher_valid_days', 'stock', 'is_active']);
            $offer->update($payload);

            $this->audit->record(
                action: 'reward_offer.updated',
                subject: $offer,
                before: $before,
                after: $offer->only(['unit_id', 'name', 'cost_points', 'voucher_amount', 'voucher_valid_days', 'stock', 'is_active']),
                userId: $actor->id,
            );

            return $offer;
        });
    }

    public function toggleActive(User $actor, RewardOffer $offer, bool $isActive): RewardOffer
    {
        return DB::transaction(function () use ($actor, $offer, $isActive): RewardOffer {
            $before = ['is_active' => $offer->is_active];
            $offer->update(['is_active' => $isActive]);

            $this->audit->record(
                action: $isActive ? 'reward_offer.activated' : 'reward_offer.deactivated',
                subject: $offer,
                before: $before,
                after: ['is_active' => $offer->is_active],
                userId: $actor->id,
            );

            return $offer;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{unit_id: int|null, name: string, cost_points: int, voucher_amount: int, voucher_valid_days: int, stock: int|null, is_active: bool}
     */
    private function validateAndNormalize(array $data): array
    {
        $costPoints = (int) ($data['cost_points'] ?? 0);
        $voucherAmount = (int) ($data['voucher_amount'] ?? 0);
        $validDays = (int) ($data['voucher_valid_days'] ?? 0);
        $stock = isset($data['stock']) && $data['stock'] !== '' ? (int) $data['stock'] : null;

        if ($costPoints <= 0 || $voucherAmount <= 0 || $validDays <= 0) {
            throw ValidationException::withMessages([
                'cost_points' => 'Biaya poin, nilai voucher, dan masa berlaku harus lebih dari 0.',
            ]);
        }

        if ($stock !== null && $stock < 0) {
            throw ValidationException::withMessages([
                'stock' => 'Stok tidak boleh negatif.',
            ]);
        }

        return [
            'unit_id' => isset($data['unit_id']) && $data['unit_id'] !== '' ? (int) $data['unit_id'] : null,
            'name' => trim((string) ($data['name'] ?? '')),
            'cost_points' => $costPoints,
            'voucher_amount' => $voucherAmount,
            'voucher_valid_days' => $validDays,
            'stock' => $stock,
            'is_active' => (bool) ($data['is_active'] ?? true),
        ];
    }
}
