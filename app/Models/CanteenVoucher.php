<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property CarbonImmutable $expires_on
 * @property CarbonImmutable $created_at
 * @property-read Student $student
 * @property-read RewardRedemption $redemption
 */
class CanteenVoucher extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'student_id',
        'reward_redemption_id',
        'amount',
        'remaining',
        'expires_on',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'remaining' => 'integer',
            'expires_on' => 'date',
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
     * @return BelongsTo<RewardRedemption, $this>
     */
    public function redemption(): BelongsTo
    {
        return $this->belongsTo(RewardRedemption::class, 'reward_redemption_id');
    }
}
