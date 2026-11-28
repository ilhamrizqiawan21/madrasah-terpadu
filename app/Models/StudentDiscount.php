<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DiscountKind;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Potongan diskon/beasiswa berulang per siswa per jenis tagihan (T-615).
 *
 * @property int $id
 * @property int $student_id
 * @property int $fee_type_id
 * @property DiscountKind $kind
 * @property int $value
 * @property CarbonImmutable $valid_from
 * @property CarbonImmutable|null $valid_until
 * @property string $reason
 * @property bool $is_active
 * @property int $created_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Student $student
 * @property-read FeeType $feeType
 * @property-read User $creator
 */
#[Fillable([
    'student_id',
    'fee_type_id',
    'kind',
    'value',
    'valid_from',
    'valid_until',
    'reason',
    'is_active',
    'created_by',
])]
class StudentDiscount extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => DiscountKind::class,
            'value' => 'integer',
            'valid_from' => 'immutable_date',
            'valid_until' => 'immutable_date',
            'is_active' => 'boolean',
        ];
    }

    public function isValidOn(CarbonImmutable $date): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($date->lessThan($this->valid_from)) {
            return false;
        }

        return $this->valid_until === null || $date->lessThanOrEqualTo($this->valid_until);
    }

    public function computeAmount(int $invoiceAmount): int
    {
        if ($invoiceAmount <= 0) {
            return 0;
        }

        $raw = $this->kind === DiscountKind::Percent
            ? intdiv($invoiceAmount * min(100, max(0, $this->value)), 100)
            : max(0, $this->value);

        return min($invoiceAmount, $raw);
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
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
