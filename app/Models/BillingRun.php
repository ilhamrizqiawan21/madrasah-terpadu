<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BillingTargetType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Ringkasan satu eksekusi penerbitan tagihan massal (T-602).
 *
 * @property int $id
 * @property int $fee_type_id
 * @property BillingTargetType $target_type
 * @property array<string, mixed> $target_payload
 * @property CarbonImmutable|null $period_from
 * @property CarbonImmutable|null $period_to
 * @property int|null $due_day
 * @property CarbonImmutable|null $due_date
 * @property int $amount
 * @property int $created_by
 * @property int $invoice_count
 * @property int $total_amount
 * @property Carbon $created_at
 * @property-read FeeType $feeType
 * @property-read User $creator
 */
#[Fillable([
    'fee_type_id',
    'target_type',
    'target_payload',
    'period_from',
    'period_to',
    'due_day',
    'due_date',
    'amount',
    'created_by',
    'invoice_count',
    'total_amount',
    'created_at',
])]
class BillingRun extends Model
{
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'target_type' => BillingTargetType::class,
            'target_payload' => 'array',
            'period_from' => 'immutable_date',
            'period_to' => 'immutable_date',
            'due_day' => 'integer',
            'due_date' => 'immutable_date',
            'amount' => 'integer',
            'invoice_count' => 'integer',
            'total_amount' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<FeeType, $this>
     */
    public function feeType(): BelongsTo
    {
        return $this->belongsTo(FeeType::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }
}
