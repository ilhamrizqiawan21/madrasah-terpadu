<?php

declare(strict_types=1);

namespace App\Services\Quran;

use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\MemorizationRecord;
use App\Models\MemorizationTarget;
use App\Models\MemorizedAyah;
use App\Models\Student;

class MemorizationProgressService
{
    /**
     * @return array{
     *     quran: array{achieved: int, target: int, pct: float, total_memorized_ayahs: int},
     *     hadith: array{achieved: int, target: int, pct: float},
     *     prayer: array{achieved: int, target: int, pct: float},
     * }
     */
    public function progressForStudent(Student $student, ?AcademicYear $year): array
    {
        $totalMemorizedAyahs = MemorizedAyah::query()
            ->where('student_id', $student->id)
            ->count();

        if ($year === null) {
            return [
                'quran' => ['achieved' => 0, 'target' => 0, 'pct' => 0.0, 'total_memorized_ayahs' => $totalMemorizedAyahs],
                'hadith' => ['achieved' => 0, 'target' => 0, 'pct' => 0.0],
                'prayer' => ['achieved' => 0, 'target' => 0, 'pct' => 0.0],
            ];
        }

        $enrollment = Enrollment::query()
            ->with('classroom:id,grade')
            ->where('student_id', $student->id)
            ->where('academic_year_id', $year->id)
            ->first();

        $grade = $enrollment?->classroom->grade;

        $targets = $grade !== null
            ? MemorizationTarget::query()
                ->active()
                ->where('unit_id', $student->unit_id)
                ->where('grade', $grade)
                ->where('academic_year_id', $year->id)
                ->get()
            : collect();

        // Quran progress against target ranges
        $targetAyahKeys = [];
        foreach ($targets->where('kind', 'quran') as $target) {
            foreach ($target->ranges ?? [] as $r) {
                for ($a = $r['ayah_from']; $a <= $r['ayah_to']; $a++) {
                    $targetAyahKeys[$r['surah'].':'.$a] = true;
                }
            }
        }

        $achievedQuran = 0;
        if ($targetAyahKeys !== []) {
            $memorizedSet = MemorizedAyah::query()
                ->where('student_id', $student->id)
                ->get(['surah', 'ayah'])
                ->mapWithKeys(fn (MemorizedAyah $m): array => [$m->surah.':'.$m->ayah => true])
                ->all();

            foreach (array_keys($targetAyahKeys) as $key) {
                if (isset($memorizedSet[$key])) {
                    $achievedQuran++;
                }
            }
        }
        $targetQuranCount = count($targetAyahKeys);

        $passedMaterialIds = array_values(MemorizationRecord::query()
            ->where('student_id', $student->id)
            ->whereIn('kind', ['hadith', 'prayer'])
            ->where('passed', true)
            ->whereNotNull('memorization_material_id')
            ->pluck('memorization_material_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->unique()
            ->all());

        $hadithProgress = $this->materialProgress($targets->where('kind', 'hadith'), $passedMaterialIds);
        $prayerProgress = $this->materialProgress($targets->where('kind', 'prayer'), $passedMaterialIds);

        return [
            'quran' => [
                'achieved' => $achievedQuran,
                'target' => $targetQuranCount,
                'pct' => $targetQuranCount > 0 ? round(($achievedQuran / $targetQuranCount) * 100, 1) : 0.0,
                'total_memorized_ayahs' => $totalMemorizedAyahs,
            ],
            'hadith' => $hadithProgress,
            'prayer' => $prayerProgress,
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, MemorizationTarget>  $targets
     * @param  list<int>  $passedMaterialIds
     * @return array{achieved: int, target: int, pct: float}
     */
    private function materialProgress($targets, array $passedMaterialIds): array
    {
        $targetIds = [];
        foreach ($targets as $target) {
            foreach ($target->material_ids ?? [] as $id) {
                $targetIds[$id] = true;
            }
        }

        $targetCount = count($targetIds);
        $achieved = 0;
        foreach (array_keys($targetIds) as $id) {
            if (in_array($id, $passedMaterialIds, true)) {
                $achieved++;
            }
        }

        return [
            'achieved' => $achieved,
            'target' => $targetCount,
            'pct' => $targetCount > 0 ? round(($achieved / $targetCount) * 100, 1) : 0.0,
        ];
    }
}
