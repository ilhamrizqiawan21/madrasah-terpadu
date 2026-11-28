<?php

declare(strict_types=1);

namespace App\Services\Attendance;

use App\Models\StaffAttendance;
use App\Models\StaffLeaveRequest;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\AppTimezone;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Layanan presensi harian pegawai & pengajuan izin pegawai (T-1601).
 */
final class StaffAttendanceService
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array{
     *     work_date: string,
     *     status: string,
     *     check_in_time?: string|null,
     *     check_out_time?: string|null,
     *     note?: string|null,
     * }  $data
     */
    public function recordManual(User $actor, User $staff, array $data): StaffAttendance
    {
        $tz = AppTimezone::name();
        $workDate = $data['work_date'];

        $checkIn = isset($data['check_in_time']) && $data['check_in_time'] !== ''
            ? CarbonImmutable::createFromFormat('Y-m-d H:i', "{$workDate} {$data['check_in_time']}", $tz)->utc()
            : null;
        $checkOut = isset($data['check_out_time']) && $data['check_out_time'] !== ''
            ? CarbonImmutable::createFromFormat('Y-m-d H:i', "{$workDate} {$data['check_out_time']}", $tz)->utc()
            : null;

        return DB::transaction(function () use ($actor, $staff, $data, $workDate, $checkIn, $checkOut): StaffAttendance {
            $attendance = StaffAttendance::query()
                ->where('user_id', $staff->id)
                ->whereDate('work_date', $workDate)
                ->lockForUpdate()
                ->first();

            $isNew = $attendance === null;
            if ($attendance === null) {
                $attendance = new StaffAttendance([
                    'user_id' => $staff->id,
                    'work_date' => $workDate,
                ]);
            }

            $attendance->forceFill([
                'status' => $data['status'],
                'source' => 'manual',
                'check_in_at' => $checkIn,
                'check_out_at' => $checkOut,
                'note' => isset($data['note']) && trim($data['note']) !== '' ? trim($data['note']) : null,
            ])->save();

            $this->audit->record(
                $isNew ? 'staff_attendance.created' : 'staff_attendance.updated',
                $attendance,
                null,
                [
                    'user_id' => $staff->id,
                    'work_date' => $workDate,
                    'status' => $attendance->status,
                ],
                $actor->id,
            );

            return $attendance;
        });
    }

    /**
     * @param  array{type: string, starts_on: string, ends_on: string, reason: string}  $data
     */
    public function submitLeave(User $staff, array $data): StaffLeaveRequest
    {
        return DB::transaction(function () use ($staff, $data): StaffLeaveRequest {
            $leave = StaffLeaveRequest::query()->create([
                'user_id' => $staff->id,
                'type' => $data['type'],
                'starts_on' => $data['starts_on'],
                'ends_on' => $data['ends_on'],
                'reason' => trim($data['reason']),
                'status' => 'pending',
            ]);

            $this->audit->record('staff_leave.submitted', $leave, null, [
                'type' => $leave->type,
                'starts_on' => $leave->starts_on->toDateString(),
                'ends_on' => $leave->ends_on->toDateString(),
            ], $staff->id);

            return $leave;
        });
    }

    public function decideLeave(User $decider, StaffLeaveRequest $leave, string $decision): StaffLeaveRequest
    {
        return DB::transaction(function () use ($decider, $leave, $decision): StaffLeaveRequest {
            /** @var StaffLeaveRequest $locked */
            $locked = StaffLeaveRequest::query()->whereKey($leave->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'pending') {
                throw ValidationException::withMessages([
                    'decision' => 'Pengajuan izin ini sudah diputuskan sebelumnya.',
                ]);
            }

            $locked->forceFill([
                'status' => $decision,
                'decided_by' => $decider->id,
                'decided_at' => now(),
            ])->save();

            if ($decision === 'approved') {
                $mappedStatus = $locked->type === 'sick' ? 'sick' : 'leave';
                $cursor = $locked->starts_on;
                while ($cursor->lte($locked->ends_on)) {
                    StaffAttendance::query()->firstOrCreate(
                        [
                            'user_id' => $locked->user_id,
                            'work_date' => $cursor->toDateString(),
                        ],
                        [
                            'status' => $mappedStatus,
                            'source' => 'manual',
                            'note' => "Izin disetujui ({$locked->type})",
                        ],
                    );
                    $cursor = $cursor->addDay();
                }
            }

            $this->audit->record('staff_leave.decided', $locked, null, [
                'decision' => $decision,
                'user_id' => $locked->user_id,
            ], $decider->id);

            return $locked;
        });
    }
}
