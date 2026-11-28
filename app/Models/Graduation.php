<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Keputusan kelulusan satu siswa pada satu tahun ajaran beserta nomor SKL.
 *
 * @property int $id
 * @property int $student_id
 * @property int $academic_year_id
 * @property string $status passed|failed
 * @property string|null $letter_number
 * @property int $decided_by
 * @property CarbonImmutable $decided_at
 * @property-read Student $student
 * @property-read AcademicYear $academicYear
 */
#[Fillable(['student_id', 'academic_year_id', 'status', 'letter_number', 'decided_by', 'decided_at'])]
class Graduation extends Model
{
    public const string PASSED = 'passed';

    public const string FAILED = 'failed';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['decided_at' => 'immutable_datetime'];
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
