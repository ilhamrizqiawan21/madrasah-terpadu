<?php

declare(strict_types=1);

namespace App\Services\Cbt;

use App\Enums\Permission;
use App\Enums\StudentStatus;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Student;
use App\Models\StudentExamPin;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Pengelolaan PIN ujian siswa (T-1502). PIN 6 digit di-hash dan tidak pernah disimpan plaintext.
 */
final class StudentExamPinService
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function canManageClassroom(User $actor, Classroom $classroom): bool
    {
        if ($actor->can(Permission::CbtManage->value)) {
            return true;
        }

        return $actor->can(Permission::CbtTeach->value)
            && $classroom->homeroom_teacher_id === $actor->id;
    }

    public function canManageStudent(User $actor, Student $student): bool
    {
        if ($actor->can(Permission::CbtManage->value)) {
            return true;
        }

        if (! $actor->can(Permission::CbtTeach->value)) {
            return false;
        }

        $activeYearId = AcademicYear::query()->where('is_active', true)->value('id');
        if ($activeYearId === null) {
            return false;
        }

        return $student->enrollments()
            ->where('academic_year_id', $activeYearId)
            ->whereHas('classroom', fn ($query) => $query->where('homeroom_teacher_id', $actor->id))
            ->exists();
    }

    public function resetForStudent(Student $student, User $actor): string
    {
        $pin = $this->generatePin();

        DB::transaction(function () use ($student, $actor, $pin): void {
            $record = StudentExamPin::query()->updateOrCreate(
                ['student_id' => $student->id],
                [
                    'pin_hash' => Hash::make($pin),
                    'reset_by' => $actor->id,
                    'reset_at' => now(),
                ],
            );

            $this->auditLogger->record(
                action: 'exam_pin_reset',
                subject: $record,
                after: ['student_id' => $student->id],
                userId: $actor->id,
            );
        });

        return $pin;
    }

    /**
     * @return list<array{student_id: int, nis: string, name: string, pin: string}>
     */
    public function resetForClassroom(Classroom $classroom, User $actor): array
    {
        $students = Student::query()
            ->where('status', StudentStatus::Active)
            ->whereHas('enrollments', function ($query) use ($classroom): void {
                $query->where('classroom_id', $classroom->id)
                    ->where('academic_year_id', $classroom->academic_year_id);
            })
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        $results = [];

        DB::transaction(function () use ($students, $actor, &$results): void {
            $now = now();

            foreach ($students as $student) {
                $pin = $this->generatePin();

                $record = StudentExamPin::query()->updateOrCreate(
                    ['student_id' => $student->id],
                    [
                        'pin_hash' => Hash::make($pin),
                        'reset_by' => $actor->id,
                        'reset_at' => $now,
                    ],
                );

                $this->auditLogger->record(
                    action: 'exam_pin_reset',
                    subject: $record,
                    after: ['student_id' => $student->id],
                    userId: $actor->id,
                );

                $results[] = [
                    'student_id' => $student->id,
                    'nis' => $student->nis,
                    'name' => $student->name,
                    'pin' => $pin,
                ];
            }
        });

        return $results;
    }

    private function generatePin(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }
}
