<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Keanggotaan siswa di satu ekskul per tahun ajaran beserta predikat rapor (A–D).
 *
 * @property int $id
 * @property int $extracurricular_id
 * @property int $student_id
 * @property int $academic_year_id
 * @property string|null $grade
 * @property string|null $notes
 * @property-read Extracurricular $extracurricular
 * @property-read Student $student
 */
#[Fillable(['extracurricular_id', 'student_id', 'academic_year_id', 'grade', 'notes'])]
class ExtracurricularMember extends Model
{
    /**
     * @return BelongsTo<Extracurricular, $this>
     */
    public function extracurricular(): BelongsTo
    {
        return $this->belongsTo(Extracurricular::class);
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return HasMany<ExtracurricularAttendance, $this>
     */
    public function attendances(): HasMany
    {
        return $this->hasMany(ExtracurricularAttendance::class);
    }
}
