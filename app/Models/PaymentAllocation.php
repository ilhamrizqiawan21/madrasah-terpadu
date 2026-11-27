<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Alokasi nominal pembayaran ke satu tagihan (T-605).
 *
 * @property int $id
 * @property int $payment_id
 * @property int $invoice_id
 * @property int $amount
 * @property-read Payment $payment
 * @property-read Invoice $invoice
 */
#[Fillable(['payment_id', 'invoice_id', 'amount'])]
class PaymentAllocation extends Model
{
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
