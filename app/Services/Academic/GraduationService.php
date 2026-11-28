<?php

declare(strict_types=1);

namespace App\Services\Academic;

use App\Models\AcademicYear;
use App\Models\Graduation;
use App\Models\Student;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Menetapkan status kelulusan. Nomor SKL diberikan sekali saat siswa pertama kali dinyatakan lulus
 * dan tidak berubah bila status dibalik lalu dikembalikan.
 */
final class GraduationService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function decide(Student $student, AcademicYear $year, string $status, User $actor): Graduation
    {
        return DB::transaction(function () use ($student, $year, $status, $actor): Graduation {
            $graduation = Graduation::query()->lockForUpdate()
                ->firstOrNew(['student_id' => $student->id, 'academic_year_id' => $year->id]);
            $before = $graduation->exists ? ['status' => $graduation->status] : null;

            $graduation->fill(['status' => $status, 'decided_by' => $actor->id, 'decided_at' => now()]);

            if ($status === Graduation::PASSED && $graduation->letter_number === null) {
                $graduation->letter_number = $this->nextLetterNumber($student, $year);
            }

            $graduation->save();
            $this->audit->record('graduation.decided', $graduation, $before, ['status' => $status, 'letter_number' => $graduation->letter_number], $actor->id);

            return $graduation;
        });
    }

    private function nextLetterNumber(Student $student, AcademicYear $year): string
    {
        $student->loadMissing('unit:id,code');
        $suffix = '/SKL/'.$student->unit->code.'/'.$year->starts_on->format('Y');

        $last = Graduation::query()->lockForUpdate()
            ->where('academic_year_id', $year->id)
            ->whereLike('letter_number', '%'.$suffix)
            ->pluck('letter_number')
            ->map(fn (mixed $n): int => (int) strtok((string) $n, '/'))
            ->max() ?? 0;

        return str_pad((string) ($last + 1), 3, '0', STR_PAD_LEFT).$suffix;
    }
}
