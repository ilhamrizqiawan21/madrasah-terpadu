<?php

declare(strict_types=1);

namespace App\Http\Controllers\Guardian;

use App\Domain\Attendance\AttendanceClassifier;
use App\Enums\AttendanceStatus;
use App\Http\Controllers\Controller;
use App\Models\AttendanceDay;
use App\Models\Student;
use App\Services\Attendance\SchoolCalendarService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceController extends Controller
{
    public function __construct(private readonly SchoolCalendarService $calendar) {}

    public function index(Request $request, Student $student): Response
    {
        $student->loadMissing('unit');

        $tz = AttendanceClassifier::BUSINESS_TIMEZONE;
        $now = CarbonImmutable::now($tz);
        $todayStr = $now->toDateString();

        $month = (string) $request->query('month', $now->format('Y-m'));
        if (! preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = $now->format('Y-m');
        }

        $monthStart = CarbonImmutable::parse("{$month}-01", $tz)->startOfMonth();
        $monthEnd = $monthStart->endOfMonth();

        /** @var AttendanceDay|null $todayDay */
        $todayDay = AttendanceDay::query()
            ->where('student_id', $student->id)
            ->whereDate('school_date', $todayStr)
            ->first();

        /** @var Collection<string, AttendanceDay> $daysByDate */
        $daysByDate = AttendanceDay::query()
            ->where('student_id', $student->id)
            ->whereDate('school_date', '>=', $monthStart->toDateString())
            ->whereDate('school_date', '<=', $monthEnd->toDateString())
            ->orderBy('school_date')
            ->get()
            ->keyBy(fn (AttendanceDay $d): string => $d->school_date->toDateString());

        $summary = [
            AttendanceStatus::Present->value => 0,
            AttendanceStatus::Late->value => 0,
            AttendanceStatus::Excused->value => 0,
            AttendanceStatus::Sick->value => 0,
            AttendanceStatus::Absent->value => 0,
        ];

        $calendarDays = [];
        $records = [];

        $cursor = $monthStart;
        while ($cursor->lte($monthEnd)) {
            $dateStr = $cursor->toDateString();
            /** @var AttendanceDay|null $day */
            $day = $daysByDate->get($dateStr);
            $isHoliday = $this->calendar->isHoliday($student->unit, $dateStr);

            $inTime = $day?->checked_in_at?->setTimezone($tz)->format('H.i');
            $outTime = $day?->checked_out_at?->setTimezone($tz)->format('H.i');

            if ($day !== null) {
                $summary[$day->status->value]++;
                $records[] = [
                    'date' => $dateStr,
                    'status' => $day->status->value,
                    'status_label' => $day->status->label(),
                    'checked_in_at' => $inTime,
                    'checked_out_at' => $outTime,
                ];
            }

            $calendarDays[] = [
                'date' => $dateStr,
                'day' => $cursor->day,
                'weekday' => $cursor->dayOfWeekIso, // 1=Mon .. 7=Sun
                'status' => $day?->status->value,
                'status_label' => $day?->status->label() ?? ($isHoliday ? 'Libur' : 'Belum ada data'),
                'is_holiday' => $isHoliday,
                'checked_in_at' => $inTime,
                'checked_out_at' => $outTime,
            ];

            $cursor = $cursor->addDay();
        }

        return Inertia::render('guardian/attendance', [
            'student' => [
                'id' => $student->id,
                'name' => $student->name,
                'unit' => $student->unit->code,
            ],
            'today' => [
                'date' => $todayStr,
                'is_holiday' => $this->calendar->isHoliday($student->unit, $todayStr),
                'status' => $todayDay?->status->value,
                'status_label' => $todayDay?->status->label() ?? 'Belum tercatat',
                'checked_in_at' => $todayDay?->checked_in_at?->setTimezone($tz)->format('H.i'),
                'checked_out_at' => $todayDay?->checked_out_at?->setTimezone($tz)->format('H.i'),
            ],
            'month' => $month,
            'monthLabel' => $monthStart->translatedFormat('F Y'),
            'summary' => $summary,
            'calendarDays' => $calendarDays,
            'records' => array_reverse($records),
        ]);
    }
}
