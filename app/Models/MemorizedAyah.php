<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemorizedAyah extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'student_id',
        'surah',
        'ayah',
        'first_passed_record_id',
    ];

    protected function casts(): array
    {
        return [
            'surah' => 'integer',
            'ayah' => 'integer',
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
     * @return BelongsTo<MemorizationRecord, $this>
     */
    public function firstPassedRecord(): BelongsTo
    {
        return $this->belongsTo(MemorizationRecord::class, 'first_passed_record_id');
    }
}
