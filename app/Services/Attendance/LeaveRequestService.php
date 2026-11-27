<?php

declare(strict_types=1);

namespace App\Services\Attendance;

use App\Domain\Attendance\AttendanceClassifier;
use App\Enums\AttendanceDecider;
use App\Enums\LeaveRequestStatus;
use App\Enums\LeaveType;
use App\Models\AttendanceDay;
use App\Models\LeaveRequest;
use App\Models\Student;
use App\Models\User;
use App\Notifications\LeaveRequestDecidedNotification;
use App\Services\Audit\AuditLogger;
use App\Services\Guardian\GuardianHomeSummaryService;
use App\Services\Notifications\GuardianNotifier;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Pengelolaan pengajuan izin/sakit dari wali dan keputusan wali kelas/admin (T-506).
 */
final class LeaveRequestService
{
    public function __construct(
        private readonly SchoolCalendarService $calendar,
        private readonly AuditLogger $auditLogger,
        private readonly GuardianNotifier $notifier,
        private readonly GuardianHomeSummaryService $homeCache,
    ) {}

    /**
     * @param  array{type: LeaveType, starts_on: string, ends_on: string, reason: string}  $data
     */
    public function submit(
        Student $student,
        User $guardianUser,
        array $data,
        ?UploadedFile $attachment = null,
    ): LeaveRequest {
        $tz = AttendanceClassifier::BUSINESS_TIMEZONE;
        $today = CarbonImmutable::now($tz)->startOfDay();
        $backdateDays = (int) config('sekolah.attendance.leave_backdate_days', 3);
        $minDate = $today->subDays($backdateDays);

        $startsOn = CarbonImmutable::parse($data['starts_on'], $tz)->startOfDay();
        $endsOn = CarbonImmutable::parse($data['ends_on'], $tz)->startOfDay();

        if ($startsOn->lt($minDate)) {
            throw new DomainException("Tanggal mulai izin maksimal {$backdateDays} hari ke belakang.");
        }

        if ($endsOn->lt($startsOn)) {
            throw new DomainException('Tanggal selesai tidak boleh lebih awal dari tanggal mulai.');
        }

        if ($startsOn->diffInDays($endsOn) > 14) {
            throw new DomainException('Rentang pengajuan izin maksimal 14 hari.');
        }

        $storedPath = null;

        if ($attachment !== null) {
            $storedPath = Storage::disk('local')->putFile('leave-attachments', $attachment) ?: null;
        }

        try {
            return DB::transaction(function () use ($student, $guardianUser, $data, $startsOn, $endsOn, $storedPath): LeaveRequest {
                Student::query()->whereKey($student->id)->lockForUpdate()->firstOrFail();

                $overlapExists = LeaveRequest::query()
                    ->where('student_id', $student->id)
                    ->whereIn('status', [
                        LeaveRequestStatus::Pending->value,
                        LeaveRequestStatus::Approved->value,
                    ])
                    ->whereDate('starts_on', '<=', $endsOn->toDateString())
                    ->whereDate('ends_on', '>=', $startsOn->toDateString())
                    ->exists();

                if ($overlapExists) {
                    throw new DomainException('Sudah ada pengajuan izin yang tumpang tindih pada rentang tanggal tersebut.');
                }

                $leave = LeaveRequest::query()->create([
                    'student_id' => $student->id,
                    'requested_by' => $guardianUser->id,
                    'type' => $data['type'],
                    'starts_on' => $startsOn->toDateString(),
                    'ends_on' => $endsOn->toDateString(),
                    'reason' => trim($data['reason']),
                    'attachment_path' => $storedPath,
                    'status' => LeaveRequestStatus::Pending,
                ]);

                $this->auditLogger->record(
                    action: 'leave_request_submitted',
                    subject: $leave,
                    after: [
                        'student_id' => $student->id,
                        'type' => $data['type']->value,
                        'starts_on' => $startsOn->toDateString(),
                        'ends_on' => $endsOn->toDateString(),
                    ],
                    userId: $guardianUser->id,
                );

                return $leave;
            });
        } catch (\Throwable $exception) {
            if ($storedPath !== null) {
                Storage::disk('local')->delete($storedPath);
            }

            throw $exception;
        }
    }

    public function decide(LeaveRequest $leave, User $actor, bool $approved, ?string $note = null): LeaveRequest
    {
        return DB::transaction(function () use ($leave, $actor, $approved, $note): LeaveRequest {
            /** @var LeaveRequest $locked */
            $locked = LeaveRequest::query()
                ->with('student.unit')
                ->whereKey($leave->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== LeaveRequestStatus::Pending) {
                throw new DomainException('Pengajuan izin ini sudah diputuskan sebelumnya.');
            }

            $decision = $approved ? LeaveRequestStatus::Approved : LeaveRequestStatus::Rejected;
            $cleanNote = $note !== null && trim($note) !== '' ? trim($note) : null;

            $locked->forceFill([
                'status' => $decision,
                'decided_by' => $actor->id,
                'decided_at' => now(),
                'decision_note' => $cleanNote,
            ])->save();

            $student = $locked->student;

            if ($approved) {
                $targetStatus = $locked->type->attendanceStatus();
                $cursor = $locked->starts_on;

                while ($cursor->lte($locked->ends_on)) {
                    $dateStr = $cursor->toDateString();

                    if (! $this->calendar->isHoliday($student->unit, $dateStr)) {
                        /** @var AttendanceDay|null $existing */
                        $existing = AttendanceDay::query()
                            ->where('student_id', $student->id)
                            ->whereDate('school_date', $dateStr)
                            ->lockForUpdate()
                            ->first();

                        if ($existing === null) {
                            AttendanceDay::query()->create([
                                'student_id' => $student->id,
                                'school_date' => $dateStr,
                                'status' => $targetStatus,
                                'checked_in_at' => null,
                                'checked_out_at' => null,
                                'leave_request_id' => $locked->id,
                                'decided_by' => AttendanceDecider::Leave,
                            ]);
                        } elseif ($existing->checked_in_at === null) {
                            // Izin yang disetujui menimpa status alpa atau izin sebelumnya.
                            $existing->forceFill([
                                'status' => $targetStatus,
                                'leave_request_id' => $locked->id,
                                'decided_by' => AttendanceDecider::Leave,
                            ])->save();
                        }
                    }

                    $cursor = $cursor->addDay();
                }

                $this->homeCache->invalidate($student->id);
            }

            $this->auditLogger->record(
                action: $approved ? 'leave_request_approved' : 'leave_request_rejected',
                subject: $locked,
                after: [
                    'status' => $decision->value,
                    'decision_note' => $cleanNote,
                ],
                userId: $actor->id,
            );

            $dateRangeLabel = $locked->starts_on->equalTo($locked->ends_on)
                ? $locked->starts_on->translatedFormat('d M Y')
                : $locked->starts_on->translatedFormat('d M').' – '.$locked->ends_on->translatedFormat('d M Y');

            $this->notifier->notifyStudentGuardians(
                $student,
                new LeaveRequestDecidedNotification(
                    leaveRequestId: $locked->id,
                    studentId: $student->id,
                    studentName: $student->nickname ?? $student->name,
                    type: $locked->type,
                    dateRangeLabel: $dateRangeLabel,
                    decision: $decision,
                    note: $cleanNote,
                ),
            );

            return $locked;
        });
    }
}
