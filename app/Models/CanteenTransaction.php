<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CanteenTransactionStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Transaksi belanja POS kantin (T-803, SCHEMA §F).
 *
 * @property int $id
 * @property int $student_id
 * @property int $cashier_id
 * @property string $client_uuid
 * @property int $total
 * @property int $paid_from_vouchers
 * @property int $paid_from_wallet
 * @property list<array{name: string, amount: int}>|null $items
 * @property CanteenTransactionStatus $status
 * @property CarbonImmutable|null $reversed_at
 * @property int|null $reversed_by
 * @property string|null $reversal_reason
 * @property CarbonImmutable $created_at
 * @property-read Student $student
 * @property-read User $cashier
 * @property-read User|null $reverser
 * @property-read Collection<int, VoucherRedemption> $voucherRedemptions
 */
#[Fillable([
    'student_id',
    'cashier_id',
    'client_uuid',
    'total',
    'paid_from_vouchers',
    'paid_from_wallet',
    'items',
    'status',
    'reversed_at',
    'reversed_by',
    'reversal_reason',
    'created_at',
])]
class CanteenTransaction extends Model
{
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'total' => 'integer',
            'paid_from_vouchers' => 'integer',
            'paid_from_wallet' => 'integer',
            'items' => 'array',
            'status' => CanteenTransactionStatus::class,
            'reversed_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reverser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by');
    }

    /**
     * @return HasMany<VoucherRedemption, $this>
     */
    public function voucherRedemptions(): HasMany
    {
        return $this->hasMany(VoucherRedemption::class);
    }
}
