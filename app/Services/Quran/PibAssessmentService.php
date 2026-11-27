<?php

declare(strict_types=1);

namespace App\Services\Quran;

use App\Domain\Quran\PibScore as PibScoreCalculator;
use App\Models\AcademicYear;
use App\Models\PibMaterial;
use App\Models\PibScore;
use App\Models\Student;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class PibAssessmentService
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    public function recordScore(
        User $actor,
        Student $student,
        PibMaterial $material,
        AcademicYear $year,
        int $mistakes,
    ): PibScore {
        try {
            $calculatedScore = PibScoreCalculator::calculate($mistakes);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['mistakes' => $e->getMessage()]);
        }

        return DB::transaction(function () use ($actor, $student, $material, $year, $mistakes, $calculatedScore): PibScore {
            $score = PibScore::query()->updateOrCreate(
                [
                    'student_id' => $student->id,
                    'pib_material_id' => $material->id,
                    'academic_year_id' => $year->id,
                ],
                [
                    'mistakes' => $mistakes,
                    'score' => $calculatedScore,
                    'assessed_by' => $actor->id,
                ]
            );

            $this->audit->record(
                action: 'pib_score.recorded',
                subject: $score,
                after: ['student_id' => $student->id, 'mistakes' => $mistakes, 'score' => $calculatedScore],
                userId: $actor->id,
            );

            return $score;
        });
    }
}
