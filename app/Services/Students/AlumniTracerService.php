<?php

declare(strict_types=1);

namespace App\Services\Students;

use App\Enums\StudentStatus;
use App\Models\AlumniTracerResponse;
use App\Models\Student;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Tracer study alumni: satu jawaban per alumni, disimpan ulang bila disurvei lagi.
 */
final class AlumniTracerService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array{student_id: int, graduation_year: int, activity: string, institution: ?string, program: ?string, income_range: ?string, relevance: string, feedback: ?string}  $data
     */
    public function record(array $data, User $actor): AlumniTracerResponse
    {
        $student = Student::query()->findOrFail($data['student_id']);

        if ($student->status !== StudentStatus::Graduated) {
            throw ValidationException::withMessages(['student_id' => 'Survei alumni hanya untuk siswa berstatus lulus.']);
        }

        return DB::transaction(function () use ($data, $actor): AlumniTracerResponse {
            $existing = AlumniTracerResponse::query()->where('student_id', $data['student_id'])->lockForUpdate()->first();
            $before = $existing?->only(['graduation_year', 'activity', 'institution', 'program', 'relevance']);

            $response = AlumniTracerResponse::query()->updateOrCreate(
                ['student_id' => $data['student_id']],
                [...$data, 'recorded_by' => $actor->id],
            );

            $this->audit->record($existing === null ? 'alumni_tracer.created' : 'alumni_tracer.updated', $response, $before, [
                'student_id' => $response->student_id,
                'graduation_year' => $response->graduation_year,
                'activity' => $response->activity->value,
            ], $actor->id);

            return $response;
        });
    }

    public function delete(AlumniTracerResponse $response, User $actor): void
    {
        DB::transaction(function () use ($response, $actor): void {
            $this->audit->record('alumni_tracer.deleted', $response, ['student_id' => $response->student_id], null, $actor->id);
            $response->delete();
        });
    }
}
