<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Attendance\AttendanceClassifier;
use App\Enums\AttendanceDecider;
use App\Enums\AttendanceStatus;
use App\Enums\LeaveRequestStatus;
use App\Enums\StudentStatus;
use App\Models\AttendanceDay;
use App\Models\LeaveRequest;
use App\Models\Student;
use App\Models\Unit;
use App\Notifications\StudentMarkedAbsentNotification;
use App\Services\Attendance\SchoolCalendarService;
use App\Services\Guardian\GuardianHomeSummaryService;
use App\Services\Notifications\GuardianNotifier;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

/**
 * Penandaan alpa otomatis harian setelah batas jam `absent_after` per unit (T-507).
 * Idempoten dan menghormati hari libur / akhir pekan unit.
 */
final class MarkDailyAbsences implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly ?string $atIso = null) {}

    public function handle(
        SchoolCalendarService $calendar,
        GuardianNotifier $notifier,
        GuardianHomeSummaryService $homeCache,
    ): int {
        $now = $this->atIso !== null
            ? CarbonImmutable::parse($this->atIso, AttendanceClassifier::BUSINESS_TIMEZONE)
            : CarbonImmutable::now(AttendanceClassifier::BUSINESS_TIMEZONE);
        $now = $now->setTimezone(AttendanceClassifier::BUSINESS_TIMEZONE);

        $schoolDate = $now->toDateString();
        $dateLabel = $now->translatedFormat('d M Y');
        $nowTime = $now->format('H:i:s');
        $markedCount = 0;

        $units = Unit::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->get();

        foreach ($units as $unit) {
            if ($calendar->isHoliday($unit, $schoolDate)) {
                continue;
            }

            $absentAfterRaw = (string) ($unit->attendance_settings['absent_after']
                ?? config('sekolah.attendance.defaults.absent_after', '09:00'));
            $absentAfter = strlen($absentAfterRaw) === 5 ? $absentAfterRaw.':00' : $absentAfterRaw;

            if ($nowTime < $absentAfter) {
                continue;
            }

            $absentAfterLabel = str_replace(':', '.', substr($absentAfterRaw, 0, 5));

            Student::query()
                ->where('unit_id', $unit->id)
                ->where('status', StudentStatus::Active->value)
                ->orderBy('id')
                ->chunkById(200, function (Collection $students) use (
                    &$markedCount,
                    $schoolDate,
                    $absentAfterLabel,
                    $dateLabel,
                    $notifier,
                    $homeCache,
                ): void {
                    $studentIds = $students->modelKeys();

                    $existingDayIds = AttendanceDay::query()
                        ->whereIn('student_id', $studentIds)
                        ->whereDate('school_date', $schoolDate)
                        ->pluck('student_id')
                        ->all();

                    $approvedLeaveIds = LeaveRequest::query()
                        ->whereIn('student_id', $studentIds)
                        ->where('status', LeaveRequestStatus::Approved->value)
                        ->whereDate('starts_on', '<=', $schoolDate)
                        ->whereDate('ends_on', '>=', $schoolDate)
                        ->pluck('student_id')
                        ->all();

                    $skipIds = array_flip(array_merge($existingDayIds, $approvedLeaveIds));

                    DB::transaction(function () use (
                        $students,
                        $skipIds,
                        $schoolDate,
                        $absentAfterLabel,
                        $dateLabel,
                        $notifier,
                        $homeCache,
                        &$markedCount,
                    ): void {
                        foreach ($students as $student) {
                            if (isset($skipIds[$student->id])) {
                                continue;
                            }

                            $day = AttendanceDay::query()->firstOrCreate(
                                [
                                    'student_id' => $student->id,
                                    'school_date' => $schoolDate,
                                ],
                                [
                                    'status' => AttendanceStatus::Absent,
                                    'checked_in_at' => null,
                                    'checked_out_at' => null,
                                    'leave_request_id' => null,
                                    'decided_by' => AttendanceDecider::System,
                                ],
                            );

                            if (! $day->wasRecentlyCreated) {
                                continue;
                            }

                            $markedCount++;
                            $homeCache->invalidate($student->id);

                            $notifier->notifyStudentGuardians(
                                $student,
                                new StudentMarkedAbsentNotification(
                                    studentId: $student->id,
                                    studentName: $student->nickname ?? $student->name,
                                    schoolDate: $schoolDate,
                                    absentAfterLabel: $absentAfterLabel,
                                    dateLabel: $dateLabel,
                                ),
                            );
                        }
                    });
                });
        }

        return $markedCount;
    }
}
