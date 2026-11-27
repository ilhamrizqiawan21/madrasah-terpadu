<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InvoiceAdjustmentType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Penyesuaian nominal tagihan append-only (T-604). Ditulis hanya lewat InvoiceAdjustmentService.
 *
 * @property int $id
 * @property int $invoice_id
 * @property InvoiceAdjustmentType $type
 * @property int $amount
 * @property string $reason
 * @property int $created_by
 * @property Carbon $created_at
 * @property-read Invoice $invoice
 * @property-read User $creator
 */
#[Fillable([
    'invoice_id',
    'type',
    'amount',
    'reason',
    'created_by',
    'created_at',
])]
class InvoiceAdjustment extends Model
{
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => InvoiceAdjustmentType::class,
            'amount' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
