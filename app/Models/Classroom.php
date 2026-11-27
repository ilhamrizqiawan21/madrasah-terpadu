<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ClassroomFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Rombel (kelas) milik satu unit dan satu tahun ajaran. Ditulis hanya lewat
 * App\Services\Classrooms\ClassroomService.
 *
 * @property int $id
 * @property int $unit_id
 * @property int $academic_year_id
 * @property string $grade
 * @property string $name
 * @property int|null $homeroom_teacher_id
 * @property bool $is_active
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Unit $unit
 * @property-read AcademicYear $academicYear
 * @property-read User|null $homeroomTeacher
 */
#[Fillable(['unit_id', 'academic_year_id', 'grade', 'name', 'homeroom_teacher_id', 'is_active'])]
class Classroom extends Model
{
    /** @use HasFactory<ClassroomFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Unit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
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
    public function homeroomTeacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'homeroom_teacher_id');
    }
}
