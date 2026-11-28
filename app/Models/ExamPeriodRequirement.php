<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ExamRequirementScope;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Syarat jenis tagihan yang wajib lunas pada satu periode ujian (T-611).
 *
 * @property int $id
 * @property int $exam_period_id
 * @property int $fee_type_id
 * @property CarbonImmutable|null $period_until
 * @property ExamRequirementScope $invoice_scope
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read ExamPeriod $examPeriod
 * @property-read FeeType $feeType
 */
#[Fillable([
    'exam_period_id',
    'fee_type_id',
    'period_until',
    'invoice_scope',
])]
class ExamPeriodRequirement extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'period_until' => 'immutable_date',
            'invoice_scope' => ExamRequirementScope::class,
        ];
    }

    /**
     * @return BelongsTo<ExamPeriod, $this>
     */
    public function examPeriod(): BelongsTo
    {
        return $this->belongsTo(ExamPeriod::class);
    }

    /**
     * @return BelongsTo<FeeType, $this>
     */
    public function feeType(): BelongsTo
    {
        return $this->belongsTo(FeeType::class);
    }
}
