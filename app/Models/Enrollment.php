<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\EnrollmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Keanggotaan siswa di satu rombel untuk satu tahun ajaran (unik per siswa per tahun ajaran).
 * Baris tahun ajaran lain tidak disentuh, sehingga menjadi riwayat rombel siswa.
 *
 * @property int $id
 * @property int $student_id
 * @property int $classroom_id
 * @property int $academic_year_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Student $student
 * @property-read Classroom $classroom
 * @property-read AcademicYear $academicYear
 */
#[Fillable(['student_id', 'classroom_id', 'academic_year_id'])]
class Enrollment extends Model
{
    /** @use HasFactory<EnrollmentFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<Classroom, $this>
     */
    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    /**
     * @return BelongsTo<AcademicYear, $this>
     */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }
}
