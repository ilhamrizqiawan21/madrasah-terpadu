<?php

declare(strict_types=1);

namespace App\Services\Academic;

use App\Models\Classroom;
use App\Models\LessonPeriod;
use App\Models\LessonSchedule;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Jam pelajaran dan jadwal mingguan. Guru tidak boleh mengajar dua rombel pada hari & slot yang sama,
 * kecuali rombel paralel yang sengaja digabung dalam satu penyimpanan.
 */
final class LessonScheduleService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array{unit_id: int, name: string, starts_at: string, ends_at: string, sort_order: int, is_break: bool}  $data
     */
    public function savePeriod(?LessonPeriod $period, array $data, User $actor): LessonPeriod
    {
        if ($data['starts_at'] >= $data['ends_at']) {
            throw ValidationException::withMessages(['ends_at' => 'Jam selesai harus setelah jam mulai.']);
        }

        return DB::transaction(function () use ($period, $data, $actor): LessonPeriod {
            $before = $period?->only(array_keys($data));
            $period ??= new LessonPeriod;
            $period->fill($data)->save();
            $this->audit->record($before === null ? 'lesson_period.created' : 'lesson_period.updated', $period, $before, $period->only(array_keys($data)), $actor->id);

            return $period;
        });
    }

    public function deletePeriod(LessonPeriod $period, User $actor): void
    {
        if (LessonSchedule::query()->where('lesson_period_id', $period->id)->exists()) {
            throw ValidationException::withMessages(['master' => 'Slot ini masih dipakai jadwal. Kosongkan jadwalnya dulu.']);
        }

        DB::transaction(function () use ($period, $actor): void {
            $this->audit->record('lesson_period.deleted', $period, $period->only(['unit_id', 'name']), null, $actor->id);
            $period->delete();
        });
    }

    /**
     * Mengisi satu sel jadwal untuk rombel utama dan rombel paralel yang digabung.
     *
     * @param  list<int>  $classroomIds
     */
    public function plot(array $classroomIds, int $weekday, LessonPeriod $period, int $subjectId, int $teacherId, ?int $roomId, User $actor): void
    {
        $classroomIds = array_values(array_unique($classroomIds));

        DB::transaction(function () use ($classroomIds, $weekday, $period, $subjectId, $teacherId, $roomId, $actor): void {
            $clash = LessonSchedule::query()
                ->with('classroom:id,name')
                ->where('teacher_id', $teacherId)
                ->where('weekday', $weekday)
                ->where('lesson_period_id', $period->id)
                ->whereNotIn('classroom_id', $classroomIds)
                ->lockForUpdate()
                ->first();

            if ($clash !== null) {
                throw ValidationException::withMessages([
                    'teacher_id' => "Guru ini sudah mengajar {$clash->classroom->name} pada hari dan jam yang sama. Gabungkan sebagai kelas paralel bila memang bersamaan.",
                ]);
            }

            foreach ($classroomIds as $classroomId) {
                LessonSchedule::query()->updateOrCreate(
                    ['classroom_id' => $classroomId, 'weekday' => $weekday, 'lesson_period_id' => $period->id],
                    ['subject_id' => $subjectId, 'teacher_id' => $teacherId, 'room_id' => $roomId],
                );
            }

            $this->audit->record('lesson_schedule.plotted', Classroom::class, null, [
                'classroom_ids' => $classroomIds,
                'weekday' => $weekday,
                'lesson_period_id' => $period->id,
                'subject_id' => $subjectId,
                'teacher_id' => $teacherId,
                'room_id' => $roomId,
            ], $actor->id);
        });
    }

    public function clear(Classroom $classroom, int $weekday, LessonPeriod $period, User $actor): void
    {
        DB::transaction(function () use ($classroom, $weekday, $period, $actor): void {
            LessonSchedule::query()
                ->where('classroom_id', $classroom->id)
                ->where('weekday', $weekday)
                ->where('lesson_period_id', $period->id)
                ->delete();

            $this->audit->record('lesson_schedule.cleared', $classroom, null, ['weekday' => $weekday, 'lesson_period_id' => $period->id], $actor->id);
        });
    }
}
