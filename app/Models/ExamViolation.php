<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ExamViolationKind;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Log pelanggaran sesi ujian append-only (T-1508).
 *
 * @property int $id
 * @property int $exam_session_id
 * @property ExamViolationKind $kind
 * @property CarbonImmutable $occurred_at
 * @property bool $counted
 */
class ExamViolation extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'exam_session_id',
        'kind',
        'occurred_at',
        'counted',
    ];

    protected function casts(): array
    {
        return [
            'kind' => ExamViolationKind::class,
            'occurred_at' => 'immutable_datetime',
            'counted' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<ExamSession, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(ExamSession::class, 'exam_session_id');
    }
}
