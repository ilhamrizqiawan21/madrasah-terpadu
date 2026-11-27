<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Billing\InvoiceStatusResolver;
use App\Enums\InvoiceStatus;
use Carbon\CarbonImmutable;
use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Tagihan siswa (T-602). Ditulis hanya lewat App\Services\Billing\*.
 *
 * @property int $id
 * @property int $student_id
 * @property int $fee_type_id
 * @property int|null $billing_run_id
 * @property CarbonImmutable|null $period
 * @property string $description
 * @property int $amount
 * @property int $adjustment_total
 * @property int $paid_total
 * @property CarbonImmutable $due_date
 * @property InvoiceStatus $status
 * @property string|null $cancelled_reason
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Student $student
 * @property-read FeeType $feeType
 * @property-read BillingRun|null $billingRun
 */
#[Fillable([
    'student_id',
    'fee_type_id',
    'billing_run_id',
    'period',
    'description',
    'amount',
    'adjustment_total',
    'paid_total',
    'due_date',
    'status',
    'cancelled_reason',
])]
class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'period' => 'immutable_date',
            'amount' => 'integer',
            'adjustment_total' => 'integer',
            'paid_total' => 'integer',
            'due_date' => 'immutable_date',
            'status' => InvoiceStatus::class,
        ];
    }

    public function remaining(): int
    {
        if ($this->status === InvoiceStatus::Cancelled) {
            return 0;
        }

        return InvoiceStatusResolver::remaining($this->amount, $this->adjustment_total, $this->paid_total);
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<FeeType, $this>
     */
    public function feeType(): BelongsTo
    {
        return $this->belongsTo(FeeType::class);
    }

    /**
     * @return BelongsTo<BillingRun, $this>
     */
    public function billingRun(): BelongsTo
    {
        return $this->belongsTo(BillingRun::class);
    }

    /**
     * @return HasMany<InvoiceAdjustment, $this>
     */
    public function adjustments(): HasMany
    {
        return $this->hasMany(InvoiceAdjustment::class);
    }
}
