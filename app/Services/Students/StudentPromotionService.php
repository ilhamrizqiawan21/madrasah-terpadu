<?php

declare(strict_types=1);

namespace App\Services\Students;

use App\Enums\MutationType;
use App\Enums\StudentStatus;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\StudentMutation;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Kenaikan kelas dan kelulusan massal per rombel (T-212).
 */
final class StudentPromotionService
{
    public const MODE_PROMOTE = 'promote';

    public const MODE_GRADUATE = 'graduate';

    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly StudentMutationService $mutations,
    ) {}

    /**
     * @param  list<int>  $studentIds
     * @return array{promoted: int, graduated: int, skipped: int}
     */
    public function execute(
        int $unitId,
        int $sourceYearId,
        int $sourceClassroomId,
        int $targetYearId,
        string $mode,
        ?int $targetClassroomId,
        array $studentIds,
        User $actor,
    ): array {
        $sourceClassroom = Classroom::query()->find($sourceClassroomId);

        if ($sourceClassroom === null || $sourceClassroom->unit_id !== $unitId || $sourceClassroom->academic_year_id !== $sourceYearId) {
            throw new RuntimeException('Rombel asal tidak sesuai unit atau tahun ajaran asal.');
        }

        if ($mode === self::MODE_PROMOTE) {
            if ($sourceYearId === $targetYearId) {
                throw new RuntimeException('Tahun ajaran tujuan harus berbeda dari tahun ajaran asal.');
            }

            $targetYear = AcademicYear::query()->find($targetYearId);
            $targetClassroom = $targetClassroomId === null ? null : Classroom::query()->find($targetClassroomId);

            if (
                $targetYear === null
                || $targetClassroom === null
                || ! $targetClassroom->is_active
                || $targetClassroom->unit_id !== $unitId
                || $targetClassroom->academic_year_id !== $targetYear->id
            ) {
                throw new RuntimeException('Rombel tujuan harus aktif pada unit dan tahun ajaran tujuan.');
            }
        }

        $uniqueIds = array_values(array_unique(array_map('intval', $studentIds)));

        return DB::transaction(function () use (
            $unitId,
            $sourceYearId,
            $sourceClassroom,
            $sourceClassroomId,
            $targetYearId,
            $mode,
            $targetClassroomId,
            $uniqueIds,
            $actor,
        ): array {
            $eligibleIds = Enrollment::query()
                ->where('academic_year_id', $sourceYearId)
                ->where('classroom_id', $sourceClassroomId)
                ->whereIn('student_id', $uniqueIds)
                ->pluck('student_id')
                ->all();

            $students = Student::query()
                ->where('unit_id', $unitId)
                ->where('status', StudentStatus::Active)
                ->whereIn('id', $eligibleIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $promoted = 0;
            $graduated = 0;
            $skipped = count($uniqueIds) - $students->count();
            $today = CarbonImmutable::now('Asia/Makassar')->toDateString();

            if ($mode === self::MODE_PROMOTE && $targetClassroomId !== null) {
                $existingTargetIds = Enrollment::query()
                    ->where('academic_year_id', $targetYearId)
                    ->whereIn('student_id', $students->modelKeys())
                    ->pluck('student_id')
                    ->all();

                $existingLookup = array_flip($existingTargetIds);

                foreach ($students as $student) {
                    if (isset($existingLookup[$student->id])) {
                        $skipped++;

                        continue;
                    }

                    Enrollment::query()->create([
                        'student_id' => $student->id,
                        'classroom_id' => $targetClassroomId,
                        'academic_year_id' => $targetYearId,
                    ]);
                    $promoted++;
                }
            } elseif ($mode === self::MODE_GRADUATE) {
                foreach ($students as $student) {
                    $student->forceFill(['status' => StudentStatus::Graduated])->save();

                    StudentMutation::query()->create([
                        'student_id' => $student->id,
                        'type' => MutationType::Graduated,
                        'mutated_on' => $today,
                        'school_name' => null,
                        'reason' => 'Kelulusan massal dari rombel.',
                        'recorded_by' => $actor->id,
                    ]);

                    $this->mutations->syncGuardianActivityAfterDeparture($student, $actor);
                    $graduated++;
                }
            }

            $this->auditLogger->record(
                action: $mode === self::MODE_GRADUATE ? 'students_graduated' : 'students_promoted',
                subject: $sourceClassroom,
                after: [
                    'unit_id' => $unitId,
                    'source_year_id' => $sourceYearId,
                    'source_classroom_id' => $sourceClassroomId,
                    'target_year_id' => $targetYearId,
                    'target_classroom_id' => $targetClassroomId,
                    'mode' => $mode,
                    'promoted' => $promoted,
                    'graduated' => $graduated,
                    'skipped' => $skipped,
                ],
                userId: $actor->id,
            );

            return [
                'promoted' => $promoted,
                'graduated' => $graduated,
                'skipped' => $skipped,
            ];
        });
    }
}
