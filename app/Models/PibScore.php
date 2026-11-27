<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PibScore extends Model
{
    protected $fillable = [
        'student_id',
        'pib_material_id',
        'academic_year_id',
        'mistakes',
        'score',
        'assessed_by',
    ];

    protected function casts(): array
    {
        return [
            'mistakes' => 'integer',
            'score' => 'integer',
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
     * @return BelongsTo<PibMaterial, $this>
     */
    public function material(): BelongsTo
    {
        return $this->belongsTo(PibMaterial::class, 'pib_material_id');
    }
}
