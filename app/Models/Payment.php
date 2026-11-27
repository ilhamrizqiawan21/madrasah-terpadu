<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Pembayaran tagihan siswa (T-605). Ditulis hanya lewat ManualPaymentService atau ProcessMidtransNotification.
 *
 * @property int $id
 * @property int $student_id
 * @property PaymentMethod $method
 * @property int $amount
 * @property Carbon $paid_at
 * @property int|null $received_by
 * @property int|null $payment_order_id
 * @property string|null $reference
 * @property string $receipt_number
 * @property Carbon|null $reversed_at
 * @property string|null $reversal_reason
 * @property int|null $reversed_by
 * @property Carbon $created_at
 * @property-read Student $student
 * @property-read User|null $receiver
 * @property-read User|null $reverser
 * @property-read Collection<int, PaymentAllocation> $allocations
 */
#[Fillable([
    'student_id',
    'method',
    'amount',
    'paid_at',
    'received_by',
    'payment_order_id',
    'reference',
    'receipt_number',
    'reversed_at',
    'reversal_reason',
    'reversed_by',
    'created_at',
])]
class Payment extends Model
{
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'method' => PaymentMethod::class,
            'amount' => 'integer',
            'paid_at' => 'datetime',
            'reversed_at' => 'datetime',
            'created_at' => 'datetime',
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
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reverser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by');
    }

    /**
     * @return HasMany<PaymentAllocation, $this>
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }
}
