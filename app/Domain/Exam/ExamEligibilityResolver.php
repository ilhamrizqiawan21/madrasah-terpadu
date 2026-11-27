<?php

declare(strict_types=1);

namespace App\Domain\Exam;

use App\Enums\Feature;
use App\Models\Exam;
use App\Models\Student;
use App\Services\Features\FeatureFlags;

/**
 * Penentu kelayakan siswa mengikuti ujian CBT berdasarkan syarat tagihan (ADR-016 / ADR-021).
 * Selama masa paralel (exam_period_id null atau fitur exam_eligibility nonaktif), semua siswa layak.
 */
final class ExamEligibilityResolver
{
    public function isEligible(Exam $exam, Student $student): bool
    {
        if ($exam->exam_period_id === null) {
            return true;
        }

        if (! app(FeatureFlags::class)->enabled(Feature::ExamEligibility)) {
            return true;
        }

        // Implementasi penuh syarat tagihan & dispensasi menyusul di T-611/T-612 (Rilis peralihan).
        return true;
    }
}
