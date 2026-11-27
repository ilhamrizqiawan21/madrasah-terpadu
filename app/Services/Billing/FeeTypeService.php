<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\FeeRecurrence;
use App\Models\FeeType;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Pengelolaan master jenis tagihan (T-601).
 */
final class FeeTypeService
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $actor, array $data): FeeType
    {
        $normalized = $this->normalizeAndValidate($data);

        return DB::transaction(function () use ($actor, $normalized): FeeType {
            $feeType = FeeType::query()->create($normalized);

            $this->audit->record(
                action: 'fee_type.created',
                subject: $feeType,
                after: $feeType->only(['unit_id', 'name', 'category', 'recurrence', 'default_amount', 'is_active']),
                userId: $actor->id,
            );

            return $feeType;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $actor, FeeType $feeType, array $data): FeeType
    {
        $normalized = $this->normalizeAndValidate($data, $feeType->id);

        return DB::transaction(function () use ($actor, $feeType, $normalized): FeeType {
            $before = $feeType->only(['unit_id', 'name', 'category', 'recurrence', 'default_amount', 'is_active']);
            $feeType->update($normalized);

            $this->audit->record(
                action: 'fee_type.updated',
                subject: $feeType,
                before: $before,
                after: $feeType->only(['unit_id', 'name', 'category', 'recurrence', 'default_amount', 'is_active']),
                userId: $actor->id,
            );

            return $feeType;
        });
    }

    public function toggleActive(User $actor, FeeType $feeType, bool $isActive): FeeType
    {
        return DB::transaction(function () use ($actor, $feeType, $isActive): FeeType {
            $before = ['is_active' => $feeType->is_active];
            $feeType->update(['is_active' => $isActive]);

            $this->audit->record(
                action: $isActive ? 'fee_type.activated' : 'fee_type.deactivated',
                subject: $feeType,
                before: $before,
                after: ['is_active' => $feeType->is_active],
                userId: $actor->id,
            );

            return $feeType;
        });
    }

    public function delete(User $actor, FeeType $feeType): void
    {
        if (Schema::hasTable('invoices') && DB::table('invoices')->where('fee_type_id', $feeType->id)->exists()) {
            throw ValidationException::withMessages([
                'fee_type' => 'Jenis tagihan sudah dipakai pada tagihan. Nonaktifkan saja.',
            ]);
        }

        DB::transaction(function () use ($actor, $feeType): void {
            $before = $feeType->only(['unit_id', 'name', 'category', 'recurrence', 'default_amount', 'is_active']);

            $this->audit->record(
                action: 'fee_type.deleted',
                subject: $feeType,
                before: $before,
                userId: $actor->id,
            );

            $feeType->delete();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizeAndValidate(array $data, ?int $ignoreId = null): array
    {
        $unitId = isset($data['unit_id']) && $data['unit_id'] !== '' ? (int) $data['unit_id'] : null;
        $name = trim((string) ($data['name'] ?? ''));
        $category = trim((string) ($data['category'] ?? ''));
        $recurrence = $data['recurrence'] instanceof FeeRecurrence
            ? $data['recurrence']->value
            : (string) ($data['recurrence'] ?? '');
        $defaultAmount = (int) ($data['default_amount'] ?? 0);

        $duplicate = FeeType::query()
            ->when($unitId === null, fn ($q) => $q->whereNull('unit_id'), fn ($q) => $q->where('unit_id', $unitId))
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->when($ignoreId !== null, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'name' => 'Nama jenis tagihan sudah digunakan pada unit ini.',
            ]);
        }

        return [
            'unit_id' => $unitId,
            'name' => $name,
            'category' => $category,
            'recurrence' => $recurrence,
            'default_amount' => $defaultAmount,
        ];
    }
}
