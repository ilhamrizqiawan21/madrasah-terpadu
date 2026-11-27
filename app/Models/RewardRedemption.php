<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property CarbonImmutable|null $decided_at
 * @property-read Student $student
 * @property-read AcademicYear $academicYear
 * @property-read RewardOffer $offer
 * @property-read User $requester
 * @property-read User|null $decider
 * @property-read CanteenVoucher|null $voucher
 */
class RewardRedemption extends Model
{
    protected $fillable = [
        'student_id',
        'academic_year_id',
        'reward_offer_id',
        'cost_points',
        'voucher_amount',
        'requested_by',
        'status',
        'decided_by',
        'decided_at',
        'decision_note',
    ];

    protected function casts(): array
    {
        return [
            'cost_points' => 'integer',
            'voucher_amount' => 'integer',
            'decided_at' => 'datetime',
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
     * @return BelongsTo<AcademicYear, $this>
     */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    /**
     * @return BelongsTo<RewardOffer, $this>
     */
    public function offer(): BelongsTo
    {
        return $this->belongsTo(RewardOffer::class, 'reward_offer_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    /**
     * @return HasOne<CanteenVoucher, $this>
     */
    public function voucher(): HasOne
    {
        return $this->hasOne(CanteenVoucher::class);
    }
}
