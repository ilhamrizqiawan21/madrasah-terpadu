<?php

declare(strict_types=1);

namespace App\Services\Students;

use App\Enums\MutationType;
use App\Enums\Role;
use App\Enums\StudentStatus;
use App\Models\Guardian;
use App\Models\Student;
use App\Models\StudentMutation;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Mutasi keluar/masuk siswa dengan tanggal, alasan, dan penonaktifan wali
 * yang tidak lagi memiliki anak aktif (T-212).
 */
final class StudentMutationService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly StudentService $students,
    ) {}

    /**
     * @param  array{
     *     unit_id: int,
     *     nis: string,
     *     nisn: string|null,
     *     name: string,
     *     nickname: string|null,
     *     gender: string,
     *     birth_place: string|null,
     *     birth_date: string|null,
     *     address: string|null,
     *     classroom_id: int|null,
     *     mutated_on: string,
     *     school_name: string|null,
     *     reason: string
     * }  $data
     */
    public function mutateIn(array $data, User $actor): Student
    {
        return DB::transaction(function () use ($data, $actor): Student {
            $student = $this->students->create(
                $data['unit_id'],
                [
                    'nis' => $data['nis'],
                    'nisn' => $data['nisn'],
                    'name' => $data['name'],
                    'nickname' => $data['nickname'],
                    'gender' => $data['gender'],
                    'birth_place' => $data['birth_place'],
                    'birth_date' => $data['birth_date'],
                    'address' => $data['address'],
                    'status' => StudentStatus::Active->value,
                    'classroom_id' => $data['classroom_id'],
                ],
                null,
                $actor,
            );

            $mutation = StudentMutation::query()->create([
                'student_id' => $student->id,
                'type' => MutationType::In,
                'mutated_on' => $data['mutated_on'],
                'school_name' => $data['school_name'],
                'reason' => $data['reason'],
                'recorded_by' => $actor->id,
            ]);

            $this->auditLogger->record(
                action: 'student_mutated_in',
                subject: $student,
                after: [
                    'mutation_id' => $mutation->id,
                    'mutated_on' => $data['mutated_on'],
                    'school_name' => $data['school_name'],
                    'reason' => $data['reason'],
                ],
                userId: $actor->id,
            );

            return $student;
        });
    }

    public function mutateOut(
        Student $student,
        string $mutatedOn,
        ?string $schoolName,
        string $reason,
        User $actor,
        bool $graduate = false,
    ): Student {
        if ($student->status !== StudentStatus::Active) {
            throw new RuntimeException('Hanya siswa berstatus aktif yang dapat dimutasikan keluar.');
        }

        // Luluskan per siswa (aksi "Luluskan Siswa" aplikasi lama) memakai jalur yang sama dengan mutasi keluar.
        $newStatus = $graduate ? StudentStatus::Graduated : StudentStatus::Moved;
        $type = $graduate ? MutationType::Graduated : MutationType::Out;

        return DB::transaction(function () use ($student, $mutatedOn, $schoolName, $reason, $actor, $newStatus, $type, $graduate): Student {
            $before = ['status' => $student->status->value];

            $student->forceFill(['status' => $newStatus])->save();

            $mutation = StudentMutation::query()->create([
                'student_id' => $student->id,
                'type' => $type,
                'mutated_on' => $mutatedOn,
                'school_name' => $schoolName,
                'reason' => $reason,
                'recorded_by' => $actor->id,
            ]);

            $deactivatedGuardians = $this->syncGuardianActivityAfterDeparture($student, $actor);

            $this->auditLogger->record(
                action: $graduate ? 'student_graduated' : 'student_mutated_out',
                subject: $student,
                before: $before,
                after: [
                    'status' => $newStatus->value,
                    'mutation_id' => $mutation->id,
                    'mutated_on' => $mutatedOn,
                    'school_name' => $schoolName,
                    'reason' => $reason,
                    'deactivated_guardian_user_ids' => $deactivatedGuardians,
                ],
                userId: $actor->id,
            );

            return $student;
        });
    }

    /**
     * Menutup akun wali murni (bukan akun staf bersama) bila tidak lagi memiliki anak aktif.
     *
     * @return list<int> ID pengguna wali yang dinonaktifkan
     */
    public function syncGuardianActivityAfterDeparture(Student $student, User $actor): array
    {
        $deactivated = [];

        /** @var list<Guardian> $guardians */
        $guardians = $student->guardians()->with('user.roles')->get()->all();

        foreach ($guardians as $guardian) {
            $user = $guardian->user;

            if (! $user->is_active) {
                continue;
            }

            $hasStaffRole = $user->roles->pluck('name')->contains(
                fn (mixed $name): bool => $name !== Role::Guardian->value,
            );

            if ($hasStaffRole) {
                continue;
            }

            $hasActiveChild = $guardian->students()
                ->where('students.id', '!=', $student->id)
                ->where('students.status', StudentStatus::Active->value)
                ->exists();

            if ($hasActiveChild) {
                continue;
            }

            $user->forceFill([
                'is_active' => false,
                'remember_token' => Str::random(60),
            ])->save();

            $this->auditLogger->record(
                action: 'guardian_deactivated_no_active_children',
                subject: $guardian,
                before: ['is_active' => true],
                after: ['is_active' => false, 'student_id' => $student->id],
                userId: $actor->id,
            );

            $deactivated[] = $user->id;
        }

        return $deactivated;
    }
}
