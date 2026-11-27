<?php

declare(strict_types=1);

namespace App\Services\Attendance;

use App\Domain\Attendance\AttendanceClassifier;
use App\Enums\AttendanceDecider;
use App\Enums\AttendanceStatus;
use App\Enums\StudentStatus;
use App\Models\AttendanceDay;
use App\Models\GateScan;
use App\Models\Student;
use App\Models\User;
use App\Notifications\StudentCheckedInNotification;
use App\Notifications\StudentCheckedOutNotification;
use App\Services\Guardian\GuardianHomeSummaryService;
use App\Services\Notifications\GuardianNotifier;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Pencatat scan kartu gerbang siswa (T-502, ARCHITECTURE §8).
 *
 * Mengunci baris siswa dalam transaksi agar scan ganda bersamaan dari dua
 * perangkat hanya menghasilkan satu baris `attendance_days`.
 */
final class GateScanService
{
    public function __construct(
        private readonly StudentCardService $cards,
        private readonly AttendanceClassifier $classifier,
        private readonly GuardianNotifier $notifier,
        private readonly GuardianHomeSummaryService $homeCache,
        private readonly SchoolCalendarService $calendar,
    ) {}

    public function scan(
        string $rawToken,
        User $actor,
        ?string $deviceLabel = null,
        ?CarbonImmutable $at = null,
        bool $isHoliday = false,
    ): GateScanOutcome {
        $card = $this->cards->findActiveByRawToken($rawToken);

        if ($card === null) {
            return new GateScanOutcome(
                accepted: false,
                code: 'invalid_card',
                message: 'Kartu tidak dikenali atau sudah dicabut.',
            );
        }

        $scannedAt = ($at ?? CarbonImmutable::now(AttendanceClassifier::BUSINESS_TIMEZONE))
            ->setTimezone(AttendanceClassifier::BUSINESS_TIMEZONE);
        $schoolDate = $scannedAt->toDateString();
        $timeLabel = $scannedAt->format('H.i');
        $duplicateWindow = (int) config('sekolah.attendance.duplicate_scan_window_seconds', 120);

        return DB::transaction(function () use ($card, $actor, $deviceLabel, $scannedAt, $schoolDate, $timeLabel, $duplicateWindow, $isHoliday): GateScanOutcome {
            /** @var Student $student */
            $student = Student::query()
                ->with('unit')
                ->whereKey($card->student_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($student->status !== StudentStatus::Active) {
                return new GateScanOutcome(
                    accepted: false,
                    code: 'inactive_student',
                    message: "Status siswa {$student->name} tidak aktif.",
                    student: $student,
                );
            }

            /** @var GateScan|null $lastScan */
            $lastScan = GateScan::query()
                ->where('student_id', $student->id)
                ->whereDate('school_date', $schoolDate)
                ->orderByDesc('scanned_at')
                ->orderByDesc('id')
                ->first();

            /** @var AttendanceDay|null $day */
            $day = AttendanceDay::query()
                ->where('student_id', $student->id)
                ->whereDate('school_date', $schoolDate)
                ->lockForUpdate()
                ->first();

            $hasCheckedIn = $day !== null && $day->checked_in_at !== null;
            $hasCheckedOut = $day !== null && $day->checked_out_at !== null;
            $effectiveHoliday = $isHoliday || $this->calendar->isHoliday($student->unit, $schoolDate);

            $classification = $this->classifier->classify(
                scannedAt: $scannedAt,
                unitSchedule: $student->unit->attendance_settings,
                lastScanAt: $lastScan?->scanned_at,
                hasCheckedInToday: $hasCheckedIn,
                hasCheckedOutToday: $hasCheckedOut,
                duplicateWindowSeconds: $duplicateWindow,
                isHoliday: $effectiveHoliday,
            );

            $scan = GateScan::query()->create([
                'student_id' => $student->id,
                'scanned_at' => $scannedAt,
                'school_date' => $schoolDate,
                'direction' => $classification->direction,
                'result' => $classification->result,
                'device_label' => $deviceLabel !== null ? mb_substr(trim($deviceLabel), 0, 50) : null,
                'scanned_by' => $actor->id,
                'created_at' => now(),
            ]);

            if ($classification->shouldUpdateAttendanceDay) {
                if ($classification->isCheckIn) {
                    $day = $this->upsertCheckIn($student, $day, $schoolDate, $scannedAt, $classification->dayStatus ?? AttendanceStatus::Present);

                    $this->homeCache->invalidate($student->id);

                    $this->notifier->notifyStudentGuardians(
                        $student,
                        new StudentCheckedInNotification(
                            studentId: $student->id,
                            studentName: $student->nickname ?? $student->name,
                            schoolDate: $schoolDate,
                            timeLabel: $timeLabel,
                            status: $day->status,
                        ),
                    );
                } elseif ($classification->isCheckOut && $day !== null) {
                    $day->forceFill([
                        'checked_out_at' => $scannedAt,
                    ])->save();

                    $this->homeCache->invalidate($student->id);

                    $this->notifier->notifyStudentGuardians(
                        $student,
                        new StudentCheckedOutNotification(
                            studentId: $student->id,
                            studentName: $student->nickname ?? $student->name,
                            schoolDate: $schoolDate,
                            timeLabel: $timeLabel,
                        ),
                    );
                }
            }

            return new GateScanOutcome(
                accepted: $classification->shouldUpdateAttendanceDay,
                code: $classification->result->value,
                message: $classification->result->label(),
                student: $student,
                scan: $scan,
                attendanceDay: $day,
                result: $classification->result,
                timeLabel: $timeLabel,
            );
        });
    }

    private function upsertCheckIn(
        Student $student,
        ?AttendanceDay $existing,
        string $schoolDate,
        CarbonImmutable $scannedAt,
        AttendanceStatus $status,
    ): AttendanceDay {
        if ($existing === null) {
            return AttendanceDay::query()->create([
                'student_id' => $student->id,
                'school_date' => $schoolDate,
                'status' => $status,
                'checked_in_at' => $scannedAt,
                'checked_out_at' => null,
                'leave_request_id' => null,
                'decided_by' => AttendanceDecider::Scan,
            ]);
        }

        // Jika sebelumnya ditandai alpa otomatis atau izin, scan masuk menimpa dengan status scan.
        $existing->forceFill([
            'status' => $status,
            'checked_in_at' => $scannedAt,
            'decided_by' => AttendanceDecider::Scan,
        ])->save();

        return $existing;
    }
}
