<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\DiscountKind;
use App\Models\StudentDiscount;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Pengelolaan master diskon & beasiswa berulang per siswa (T-615).
 */
final class StudentDiscountService
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $actor, array $data): StudentDiscount
    {
        $normalized = $this->normalizeAndValidate($data);
        $normalized['created_by'] = $actor->id;

        return DB::transaction(function () use ($actor, $normalized): StudentDiscount {
            $discount = StudentDiscount::query()->create($normalized);

            $this->audit->record(
                action: 'student_discount.created',
                subject: $discount,
                after: $discount->only(['student_id', 'fee_type_id', 'kind', 'value', 'valid_from', 'valid_until', 'reason', 'is_active']),
                userId: $actor->id,
            );

            return $discount;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $actor, StudentDiscount $discount, array $data): StudentDiscount
    {
        $normalized = $this->normalizeAndValidate($data);

        return DB::transaction(function () use ($actor, $discount, $normalized): StudentDiscount {
            $before = $discount->only(['student_id', 'fee_type_id', 'kind', 'value', 'valid_from', 'valid_until', 'reason', 'is_active']);
            $discount->update($normalized);

            $this->audit->record(
                action: 'student_discount.updated',
                subject: $discount,
                before: $before,
                after: $discount->only(['student_id', 'fee_type_id', 'kind', 'value', 'valid_from', 'valid_until', 'reason', 'is_active']),
                userId: $actor->id,
            );

            return $discount;
        });
    }

    public function toggleActive(User $actor, StudentDiscount $discount, bool $isActive): StudentDiscount
    {
        return DB::transaction(function () use ($actor, $discount, $isActive): StudentDiscount {
            $before = ['is_active' => $discount->is_active];
            $discount->update(['is_active' => $isActive]);

            $this->audit->record(
                action: $isActive ? 'student_discount.activated' : 'student_discount.deactivated',
                subject: $discount,
                before: $before,
                after: ['is_active' => $discount->is_active],
                userId: $actor->id,
            );

            return $discount;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizeAndValidate(array $data): array
    {
        $kind = $data['kind'] instanceof DiscountKind
            ? $data['kind']
            : DiscountKind::from((string) ($data['kind'] ?? ''));

        $value = (int) ($data['value'] ?? 0);
        if ($value <= 0) {
            throw ValidationException::withMessages([
                'value' => 'Nilai potongan harus lebih dari nol.',
            ]);
        }

        if ($kind === DiscountKind::Percent && $value > 100) {
            throw ValidationException::withMessages([
                'value' => 'Potongan persentase maksimal 100%.',
            ]);
        }

        $validFrom = (string) ($data['valid_from'] ?? '');
        $validUntil = ! empty($data['valid_until']) ? (string) $data['valid_until'] : null;

        if ($validUntil !== null && $validUntil < $validFrom) {
            throw ValidationException::withMessages([
                'valid_until' => 'Tanggal berakhir harus sama atau setelah tanggal mulai.',
            ]);
        }

        return [
            'student_id' => (int) $data['student_id'],
            'fee_type_id' => (int) $data['fee_type_id'],
            'kind' => $kind->value,
            'value' => $value,
            'valid_from' => $validFrom,
            'valid_until' => $validUntil,
            'reason' => trim((string) ($data['reason'] ?? '')),
        ];
    }
}
