<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentOrderPurpose;
use App\Enums\PaymentOrderStatus;
use App\Enums\PaymentReviewReason;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * Order pembayaran online Midtrans (T-702, PAYMENTS §3). Ditulis hanya lewat PaymentOrderService
 * dan MidtransNotificationProcessor.
 *
 * @property int $id
 * @property string $order_id
 * @property PaymentOrderPurpose $purpose
 * @property int $student_id
 * @property int $guardian_id
 * @property int $gross_amount
 * @property PaymentOrderStatus $status
 * @property string|null $snap_token
 * @property string|null $snap_redirect_url
 * @property Carbon $expires_at
 * @property Carbon|null $paid_at
 * @property string|null $midtrans_transaction_id
 * @property string|null $payment_type
 * @property array<string, mixed>|null $last_status_payload
 * @property PaymentReviewReason|null $review_reason
 * @property Carbon|null $reviewed_at
 * @property int|null $reviewed_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Student $student
 * @property-read Guardian $guardian
 * @property-read User|null $reviewer
 * @property-read Payment|null $payment
 * @property-read Collection<int, PaymentOrderItem> $items
 */
#[Fillable([
    'order_id',
    'purpose',
    'student_id',
    'guardian_id',
    'gross_amount',
    'status',
    'snap_token',
    'snap_redirect_url',
    'expires_at',
    'paid_at',
    'midtrans_transaction_id',
    'payment_type',
    'last_status_payload',
    'review_reason',
    'reviewed_at',
    'reviewed_by',
])]
class PaymentOrder extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'purpose' => PaymentOrderPurpose::class,
            'status' => PaymentOrderStatus::class,
            'review_reason' => PaymentReviewReason::class,
            'gross_amount' => 'integer',
            'expires_at' => 'datetime',
            'paid_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'last_status_payload' => 'array',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'order_id';
    }

    public function isAwaitingPayment(): bool
    {
        return $this->status === PaymentOrderStatus::Pending && $this->expires_at->isFuture();
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<Guardian, $this>
     */
    public function guardian(): BelongsTo
    {
        return $this->belongsTo(Guardian::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * @return HasMany<PaymentOrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(PaymentOrderItem::class);
    }

    /**
     * @return HasOne<Payment, $this>
     */
    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }
}
