<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSchoolHolidayRequest;
use App\Http\Requests\Admin\UpdateUnitWeekendRequest;
use App\Models\SchoolHoliday;
use App\Models\Unit;
use App\Models\User;
use App\Services\Attendance\SchoolCalendarService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SchoolCalendarController extends Controller
{
    public function __construct(private readonly SchoolCalendarService $calendar) {}

    public function index(Request $request): Response
    {
        $units = Unit::query()
            ->where('is_active', true)
            ->orderBy('code')
            ->get();

        $holidays = SchoolHoliday::query()
            ->with('unit:id,code,name')
            ->orderByDesc('holiday_date')
            ->orderBy('id')
            ->paginate(25)
            ->withQueryString();

        $actor = $this->actor($request);

        return Inertia::render('admin/attendance/calendar', [
            'holidays' => [
                'data' => $holidays->getCollection()->map(fn (SchoolHoliday $h): array => [
                    'id' => $h->id,
                    'unit_id' => $h->unit_id,
                    'unit_label' => $h->unit !== null ? "{$h->unit->code} — {$h->unit->name}" : 'Semua unit',
                    'holiday_date' => $h->holiday_date->toDateString(),
                    'title' => $h->title,
                    'is_day_off' => $h->is_day_off,
                ])->values()->all(),
                'meta' => [
                    'current_page' => $holidays->currentPage(),
                    'last_page' => $holidays->lastPage(),
                    'per_page' => $holidays->perPage(),
                    'total' => $holidays->total(),
                    'from' => $holidays->firstItem(),
                    'to' => $holidays->lastItem(),
                ],
            ],
            'units' => $units->map(fn (Unit $unit): array => [
                'id' => $unit->id,
                'code' => $unit->code,
                'name' => $unit->name,
                'weekend_days' => $this->calendar->weekendDaysForUnit($unit),
            ])->values()->all(),
            'canManage' => $actor->can(Permission::AttendanceManage->value),
        ]);
    }

    public function store(StoreSchoolHolidayRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $this->calendar->createHoliday([
            'unit_id' => isset($validated['unit_id']) ? (int) $validated['unit_id'] : null,
            'holiday_date' => (string) $validated['holiday_date'],
            'title' => (string) $validated['title'],
            'is_day_off' => (bool) $validated['is_day_off'],
        ], $this->actor($request));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Tanggal kalender sekolah disimpan.',
        ]);

        return back();
    }

    public function destroy(Request $request, SchoolHoliday $holiday): RedirectResponse
    {
        $this->calendar->deleteHoliday($holiday, $this->actor($request));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Tanggal kalender dihapus.',
        ]);

        return back();
    }

    public function updateWeekend(UpdateUnitWeekendRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        /** @var Unit $unit */
        $unit = Unit::query()->findOrFail((int) $validated['unit_id']);

        /** @var list<int> $days */
        $days = array_values(array_map(static fn (mixed $d): int => (int) $d, (array) $validated['weekend_days']));

        $this->calendar->updateUnitWeekendDays($unit, $days, $this->actor($request));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Hari akhir pekan unit {$unit->code} diperbarui.",
        ]);

        return back();
    }

    private function actor(Request $request): User
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        return $user;
    }
}
