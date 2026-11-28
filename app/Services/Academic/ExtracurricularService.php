<?php

declare(strict_types=1);

namespace App\Services\Academic;

use App\Models\Extracurricular;
use App\Models\ExtracurricularAttendance;
use App\Models\ExtracurricularMember;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Ekskul, keanggotaan per tahun ajaran, presensi pertemuan, dan predikat rapor.
 */
final class ExtracurricularService
{
    public const array GRADES = ['A', 'B', 'C', 'D'];

    public const array STATUSES = ['present' => 'Hadir', 'excused' => 'Izin', 'absent' => 'Alpa'];

    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array{unit_id: int, name: string, coach_type: string, coach_user_id: int|null, coach_name: string|null}  $data
     */
    public function save(?Extracurricular $ekskul, array $data, User $actor): Extracurricular
    {
        return DB::transaction(function () use ($ekskul, $data, $actor): Extracurricular {
            $before = $ekskul?->only(array_keys($data));
            $ekskul ??= new Extracurricular;
            $ekskul->fill($data)->save();
            $this->audit->record($before === null ? 'extracurricular.created' : 'extracurricular.updated', $ekskul, $before, $ekskul->only(array_keys($data)), $actor->id);

            return $ekskul;
        });
    }

    public function delete(Extracurricular $ekskul, User $actor): void
    {
        DB::transaction(function () use ($ekskul, $actor): void {
            $this->audit->record('extracurricular.deleted', $ekskul, $ekskul->only(['unit_id', 'name']), null, $actor->id);
            $ekskul->delete();
        });
    }

    /**
     * @param  list<int>  $studentIds
     */
    public function enroll(Extracurricular $ekskul, int $yearId, array $studentIds, User $actor): int
    {
        return DB::transaction(function () use ($ekskul, $yearId, $studentIds, $actor): int {
            $added = 0;
            foreach ($studentIds as $studentId) {
                $member = ExtracurricularMember::query()->firstOrCreate([
                    'extracurricular_id' => $ekskul->id, 'student_id' => $studentId, 'academic_year_id' => $yearId,
                ]);
                $added += $member->wasRecentlyCreated ? 1 : 0;
            }
            $this->audit->record('extracurricular.enrolled', $ekskul, null, ['student_ids' => $studentIds, 'academic_year_id' => $yearId], $actor->id);

            return $added;
        });
    }

    public function removeMember(ExtracurricularMember $member, User $actor): void
    {
        DB::transaction(function () use ($member, $actor): void {
            $this->audit->record('extracurricular.member_removed', $member, ['student_id' => $member->student_id], null, $actor->id);
            $member->delete();
        });
    }

    public function grade(ExtracurricularMember $member, ?string $grade, User $actor): void
    {
        DB::transaction(function () use ($member, $grade, $actor): void {
            $before = ['grade' => $member->grade];
            $member->forceFill(['grade' => $grade])->save();
            $this->audit->record('extracurricular.graded', $member, $before, ['grade' => $grade], $actor->id);
        });
    }

    /**
     * @param  array<int, string>  $statuses  member_id => present|excused|absent
     */
    public function recordAttendance(Extracurricular $ekskul, string $date, array $statuses, User $actor): void
    {
        DB::transaction(function () use ($ekskul, $date, $statuses, $actor): void {
            $memberIds = $ekskul->members()->whereIn('id', array_keys($statuses))->pluck('id')->all();
            foreach ($memberIds as $memberId) {
                ExtracurricularAttendance::query()->updateOrCreate(
                    ['extracurricular_member_id' => $memberId, 'met_on' => $date],
                    ['status' => $statuses[$memberId]],
                );
            }
            $this->audit->record('extracurricular.attendance', $ekskul, null, ['met_on' => $date, 'count' => count($memberIds)], $actor->id);
        });
    }
}
