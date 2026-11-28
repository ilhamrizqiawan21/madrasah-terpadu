<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\WalletEntryType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Entri ledger append-only dompet kantin (T-801, ADR-006).
 *
 * @property int $id
 * @property int $wallet_id
 * @property WalletEntryType $type
 * @property int $amount
 * @property int $balance_after
 * @property string $idempotency_key
 * @property string|null $reference_type
 * @property int|null $reference_id
 * @property string|null $description
 * @property int|null $created_by
 * @property CarbonImmutable $created_at
 * @property-read Wallet $wallet
 * @property-read User|null $creator
 */
#[Fillable([
    'wallet_id',
    'type',
    'amount',
    'balance_after',
    'idempotency_key',
    'reference_type',
    'reference_id',
    'description',
    'created_by',
    'created_at',
])]
class WalletEntry extends Model
{
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => WalletEntryType::class,
            'amount' => 'integer',
            'balance_after' => 'integer',
            'created_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<Wallet, $this>
     */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
