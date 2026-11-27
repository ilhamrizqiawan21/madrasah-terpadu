<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * PIN ujian CBT siswa (T-1502). PIN mentah tidak pernah disimpan.
 *
 * @property int $id
 * @property int $student_id
 * @property string $pin_hash
 * @property int $reset_by
 * @property CarbonImmutable $reset_at
 */
class StudentExamPin extends Model
{
    protected $fillable = [
        'student_id',
        'pin_hash',
        'reset_by',
        'reset_at',
    ];

    protected $hidden = [
        'pin_hash',
    ];

    protected function casts(): array
    {
        return [
            'reset_at' => 'immutable_datetime',
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
    public function resetBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reset_by');
    }
}
