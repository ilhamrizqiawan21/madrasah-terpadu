<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\GateScanResult;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Log scan gerbang append-only (T-502).
 *
 * @property int $id
 * @property int $student_id
 * @property CarbonImmutable $scanned_at
 * @property CarbonImmutable $school_date
 * @property string $direction
 * @property GateScanResult $result
 * @property string|null $device_label
 * @property int|null $scanned_by
 * @property CarbonImmutable $created_at
 * @property-read Student $student
 * @property-read User|null $scanner
 */
#[Fillable(['student_id', 'scanned_at', 'school_date', 'direction', 'result', 'device_label', 'scanned_by', 'created_at'])]
class GateScan extends Model
{
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scanned_at' => 'immutable_datetime',
            'school_date' => 'immutable_date',
            'result' => GateScanResult::class,
            'created_at' => 'immutable_datetime',
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
    public function scanner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scanned_by');
    }
}
