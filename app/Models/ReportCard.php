<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * E-rapor PDF per siswa per semester (T-1604). Ditulis hanya lewat ReportCardService.
 *
 * @property int $id
 * @property int $student_id
 * @property int $academic_year_id
 * @property string $semester
 * @property string $file_path
 * @property CarbonImmutable|null $published_at
 * @property int $uploaded_by
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read Student $student
 * @property-read AcademicYear $academicYear
 * @property-read User $uploader
 */
#[Fillable(['student_id', 'academic_year_id', 'semester', 'file_path', 'published_at', 'uploaded_by'])]
class ReportCard extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'published_at' => 'immutable_datetime',
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

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
