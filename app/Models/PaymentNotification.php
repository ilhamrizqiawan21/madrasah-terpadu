<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Notifikasi webhook Midtrans apa adanya (T-703). Append-only; pemroses hanya mengisi
 * `processed_at` dan `process_result` satu kali.
 *
 * @property int $id
 * @property string|null $order_id
 * @property string|null $transaction_status
 * @property string|null $status_code
 * @property bool $signature_valid
 * @property array<string, mixed> $payload
 * @property Carbon|null $processed_at
 * @property string|null $process_result
 * @property Carbon $received_at
 */
#[Fillable([
    'order_id',
    'transaction_status',
    'status_code',
    'signature_valid',
    'payload',
    'processed_at',
    'process_result',
    'received_at',
])]
class PaymentNotification extends Model
{
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'signature_valid' => 'boolean',
            'payload' => 'array',
            'processed_at' => 'datetime',
            'received_at' => 'datetime',
        ];
    }
}
