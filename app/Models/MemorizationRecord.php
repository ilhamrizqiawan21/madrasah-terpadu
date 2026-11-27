<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property array<int|string, float|int> $scores
 * @property CarbonImmutable $recorded_on
 * @property-read Student $student
 * @property-read User $teacher
 * @property-read RubricVersion $rubricVersion
 * @property-read MemorizationMaterial|null $material
 */
class MemorizationRecord extends Model
{
    protected $fillable = [
        'student_id',
        'teacher_id',
        'rubric_version_id',
        'kind',
        'type',
        'memorization_material_id',
        'surah_from',
        'ayah_from',
        'surah_to',
        'ayah_to',
        'scores',
        'final_score',
        'predicate',
        'passed',
        'note',
        'recorded_on',
    ];

    protected function casts(): array
    {
        return [
            'surah_from' => 'integer',
            'ayah_from' => 'integer',
            'surah_to' => 'integer',
            'ayah_to' => 'integer',
            'scores' => 'array',
            'final_score' => 'float',
            'passed' => 'boolean',
            'recorded_on' => 'date',
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
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    /**
     * @return BelongsTo<RubricVersion, $this>
     */
    public function rubricVersion(): BelongsTo
    {
        return $this->belongsTo(RubricVersion::class);
    }

    /**
     * @return BelongsTo<MemorizationMaterial, $this>
     */
    public function material(): BelongsTo
    {
        return $this->belongsTo(MemorizationMaterial::class, 'memorization_material_id');
    }
}
