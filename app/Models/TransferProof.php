<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TransferProofStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Bukti transfer yang diunggah wali murid untuk verifikasi bendahara (T-616).
 *
 * @property int $id
 * @property int $invoice_id
 * @property int $student_id
 * @property int $guardian_id
 * @property int $amount
 * @property string $proof_path
 * @property string|null $notes
 * @property TransferProofStatus $status
 * @property string|null $rejection_reason
 * @property int|null $payment_id
 * @property int|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Invoice $invoice
 * @property-read Student $student
 * @property-read Guardian $guardian
 * @property-read Payment|null $payment
 * @property-read User|null $reviewer
 */
#[Fillable([
    'invoice_id',
    'student_id',
    'guardian_id',
    'amount',
    'proof_path',
    'notes',
    'status',
    'rejection_reason',
    'payment_id',
    'reviewed_by',
    'reviewed_at',
])]
class TransferProof extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'status' => TransferProofStatus::class,
            'reviewed_at' => 'datetime',
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
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
