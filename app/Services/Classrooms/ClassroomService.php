<?php

declare(strict_types=1);

namespace App\Services\Classrooms;

use App\Enums\Role;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Unit;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Satu-satunya penulis rombel (T-204). Rombel tidak dihapus, hanya dinonaktifkan,
 * karena enrollment, presensi, dan tagihan menunjuk ke rombel.
 */
final class ClassroomService
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /**
     * Guru aktif yang boleh menjadi wali kelas.
     *
     * @return Builder<User>
     */
    public static function homeroomCandidates(): Builder
    {
        return User::query()->role(Role::Teacher->value)->where('is_active', true);
    }

    /**
     * @param  array{unit_id: int, academic_year_id: int, grade: string, name: string, homeroom_teacher_id: int|null}  $data
     */
    public function create(array $data, User $actor): Classroom
    {
        return DB::transaction(function () use ($data, $actor): Classroom {
            $classroom = Classroom::query()->create([...$data, 'is_active' => true]);

            $this->auditLogger->record(
                action: 'created',
                subject: $classroom,
                after: $this->snapshot($classroom),
                userId: $actor->id,
            );

            return $classroom;
        });
    }

    /**
     * Unit dan tahun ajaran rombel tetap; yang berubah hanya tingkat, nama, dan wali kelas.
     *
     * @param  array{grade: string, name: string, homeroom_teacher_id: int|null}  $data
     */
    public function update(Classroom $classroom, array $data, User $actor): Classroom
    {
        return DB::transaction(function () use ($classroom, $data, $actor): Classroom {
            $before = $this->snapshot($classroom);

            $classroom->fill([
                'grade' => $data['grade'],
                'name' => $data['name'],
                'homeroom_teacher_id' => $data['homeroom_teacher_id'],
            ])->save();

            $this->auditLogger->record(
                action: 'updated',
                subject: $classroom,
                before: $before,
                after: $this->snapshot($classroom),
                userId: $actor->id,
            );

            return $classroom;
        });
    }

    public function setActive(Classroom $classroom, bool $active, User $actor): Classroom
    {
        if ($classroom->is_active === $active) {
            return $classroom;
        }

        return DB::transaction(function () use ($classroom, $active, $actor): Classroom {
            $classroom->forceFill(['is_active' => $active])->save();

            $this->auditLogger->record(
                action: $active ? 'activated' : 'deactivated',
                subject: $classroom,
                before: ['is_active' => ! $active],
                after: ['is_active' => $active],
                userId: $actor->id,
            );

            return $classroom;
        });
    }

    /**
     * Salin rombel aktif satu unit dari tahun ajaran sumber ke tahun ajaran tujuan.
     * Nama yang sudah ada di tujuan dilewati; wali kelas ikut disalin hanya bila masih guru aktif.
     *
     * @return array{copied: int, skipped: int}
     */
    public function copyFromYear(Unit $unit, AcademicYear $source, AcademicYear $target, User $actor): array
    {
        return DB::transaction(function () use ($unit, $source, $target, $actor): array {
            $sourceClassrooms = Classroom::query()
                ->where('unit_id', $unit->id)
                ->where('academic_year_id', $source->id)
                ->where('is_active', true)
                ->orderBy('grade')
                ->orderBy('name')
                ->get();

            /** @var list<string> $existingNames */
            $existingNames = Classroom::query()
                ->where('unit_id', $unit->id)
                ->where('academic_year_id', $target->id)
                ->pluck('name')
                ->map(fn (string $name): string => mb_strtolower($name))
                ->all();

            /** @var list<int> $teacherIds */
            $teacherIds = self::homeroomCandidates()
                ->whereKey($sourceClassrooms->pluck('homeroom_teacher_id')->filter()->unique()->values()->all())
                ->pluck('id')
                ->all();

            $copied = 0;
            $skipped = 0;

            foreach ($sourceClassrooms as $classroom) {
                if (in_array(mb_strtolower($classroom->name), $existingNames, true)) {
                    $skipped++;

                    continue;
                }

                Classroom::query()->create([
                    'unit_id' => $unit->id,
                    'academic_year_id' => $target->id,
                    'grade' => $classroom->grade,
                    'name' => $classroom->name,
                    'homeroom_teacher_id' => in_array($classroom->homeroom_teacher_id, $teacherIds, true)
                        ? $classroom->homeroom_teacher_id
                        : null,
                    'is_active' => true,
                ]);
                $copied++;
            }

            $this->auditLogger->record(
                action: 'copied',
                subject: Classroom::class,
                after: [
                    'unit_id' => $unit->id,
                    'source_academic_year_id' => $source->id,
                    'target_academic_year_id' => $target->id,
                    'copied' => $copied,
                    'skipped' => $skipped,
                ],
                userId: $actor->id,
            );

            return ['copied' => $copied, 'skipped' => $skipped];
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Classroom $classroom): array
    {
        return [
            'unit_id' => $classroom->unit_id,
            'academic_year_id' => $classroom->academic_year_id,
            'grade' => $classroom->grade,
            'name' => $classroom->name,
            'homeroom_teacher_id' => $classroom->homeroom_teacher_id,
            'is_active' => $classroom->is_active,
        ];
    }
}
