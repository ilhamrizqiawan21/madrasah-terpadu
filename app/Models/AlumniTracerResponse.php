<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AlumniActivity;
use App\Enums\AlumniIncomeRange;
use App\Enums\CurriculumRelevance;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Jawaban tracer study satu alumni. Ditulis lewat App\Services\Students\AlumniTracerService.
 *
 * @property int $id
 * @property int $student_id
 * @property int $graduation_year
 * @property AlumniActivity $activity
 * @property string|null $institution
 * @property string|null $program
 * @property AlumniIncomeRange|null $income_range
 * @property CurriculumRelevance $relevance
 * @property string|null $feedback
 * @property int|null $recorded_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Student $student
 */
#[Fillable(['student_id', 'graduation_year', 'activity', 'institution', 'program', 'income_range', 'relevance', 'feedback', 'recorded_by'])]
class AlumniTracerResponse extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'graduation_year' => 'integer',
            'activity' => AlumniActivity::class,
            'income_range' => AlumniIncomeRange::class,
            'relevance' => CurriculumRelevance::class,
        ];
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
