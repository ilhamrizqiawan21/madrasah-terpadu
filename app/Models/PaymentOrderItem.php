<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Rincian order Midtrans: satu baris per tagihan, atau satu baris top-up (`invoice_id` null).
 *
 * @property int $id
 * @property int $payment_order_id
 * @property int|null $invoice_id
 * @property string $description
 * @property int $amount
 * @property-read PaymentOrder $order
 * @property-read Invoice|null $invoice
 */
#[Fillable(['payment_order_id', 'invoice_id', 'description', 'amount'])]
class PaymentOrderItem extends Model
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
     * @return BelongsTo<PaymentOrder, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(PaymentOrder::class, 'payment_order_id');
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
