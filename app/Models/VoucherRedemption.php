<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Penggunaan/pengembalian voucher kantin pada transaksi POS (T-803, append-only).
 *
 * @property int $id
 * @property int $canteen_voucher_id
 * @property int $canteen_transaction_id
 * @property int $amount
 * @property CarbonImmutable $created_at
 * @property-read CanteenVoucher $voucher
 * @property-read CanteenTransaction $transaction
 */
#[Fillable([
    'canteen_voucher_id',
    'canteen_transaction_id',
    'amount',
    'created_at',
])]
class VoucherRedemption extends Model
{
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'created_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<CanteenVoucher, $this>
     */
    public function voucher(): BelongsTo
    {
        return $this->belongsTo(CanteenVoucher::class, 'canteen_voucher_id');
    }

    /**
     * @return BelongsTo<CanteenTransaction, $this>
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(CanteenTransaction::class, 'canteen_transaction_id');
    }
}
