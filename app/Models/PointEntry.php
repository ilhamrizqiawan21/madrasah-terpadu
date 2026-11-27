<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PointAccount;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Buku besar poin append-only per siswa per tahun ajaran per akun (ADR-006).
 *
 * @property PointAccount $account
 * @property CarbonImmutable $created_at
 */
class PointEntry extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'student_id',
        'academic_year_id',
        'account',
        'delta',
        'balance_after',
        'source_type',
        'source_id',
        'idempotency_key',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'account' => PointAccount::class,
            'delta' => 'integer',
            'balance_after' => 'integer',
            'source_id' => 'integer',
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
     * @return BelongsTo<AcademicYear, $this>
     */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }
}
