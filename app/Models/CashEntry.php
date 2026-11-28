<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Satu baris Buku Kas Umum. Append-only: tidak pernah diubah/dihapus; koreksi = entri pembalik (ADR-030).
 * Hanya ditulis lewat App\Services\Accounting\CashLedgerService.
 *
 * @property int $id
 * @property int $unit_id
 * @property CarbonImmutable $entry_date
 * @property int $cash_account_id
 * @property string $direction in|out
 * @property int $amount
 * @property string $cash_box cash|bank
 * @property int $funding_account_id
 * @property string|null $reference_no
 * @property string|null $counterparty
 * @property string $description
 * @property string|null $source_type
 * @property int|null $source_id
 * @property int|null $reverses_entry_id
 * @property string|null $reversal_reason
 * @property int $recorded_by
 * @property CarbonImmutable $created_at
 * @property-read CashAccount $account
 * @property-read CashAccount $fundingAccount
 * @property-read User $recorder
 * @property-read CashEntry|null $reversedBy
 */
#[Fillable([
    'unit_id', 'entry_date', 'cash_account_id', 'direction', 'amount', 'cash_box', 'funding_account_id',
    'reference_no', 'counterparty', 'description', 'source_type', 'source_id', 'reverses_entry_id',
    'reversal_reason', 'recorded_by', 'created_at',
])]
class CashEntry extends Model
{
    public const UPDATED_AT = null;

    public const array BOXES = ['cash' => 'Kas tunai bendahara', 'bank' => 'Rekening bank unit'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['entry_date' => 'immutable_date', 'amount' => 'integer', 'created_at' => 'immutable_datetime'];
    }

    /**
     * @return BelongsTo<CashAccount, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(CashAccount::class, 'cash_account_id');
    }

    /**
     * @return BelongsTo<CashAccount, $this>
     */
    public function fundingAccount(): BelongsTo
    {
        return $this->belongsTo(CashAccount::class, 'funding_account_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * @return HasOne<CashEntry, $this>
     */
    public function reversedBy(): HasOne
    {
        return $this->hasOne(self::class, 'reverses_entry_id');
    }

    /** Nilai bertanda untuk saldo: masuk positif, keluar negatif. */
    public function signedAmount(): int
    {
        return $this->direction === CashAccount::IN ? $this->amount : -$this->amount;
    }
}
