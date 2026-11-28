<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\StaffAttendance;
use App\Models\User;
use App\Services\Attendance\StaffAttendanceService;
use App\Support\AppTimezone;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Absensi > Absensi Pegawai (rekap harian guru & staf, T-1601).
 */
class StaffAttendanceController extends Controller
{
    public function __construct(
        private readonly StaffAttendanceService $service,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', StaffAttendance::class);

        $tz = AppTimezone::name();
        $workDate = $request->string('work_date')->toString();
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $workDate)) {
            $workDate = CarbonImmutable::now($tz)->toDateString();
        }
        $search = trim($request->string('search')->toString());

        $staffRoles = array_map(fn (Role $r): string => $r->value, Role::adminArea());

        $paginator = User::query()
            ->whereHas('roles', fn (Builder $q) => $q->whereIn('name', $staffRoles))
            ->when($search !== '', fn (Builder $q) => $q->where(fn (Builder $sq) => $sq
                ->where('name', 'like', "%{$search}%")
                ->orWhere('username', 'like', "%{$search}%")))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        $userIds = $paginator->getCollection()->pluck('id')->all();
        $records = StaffAttendance::query()
            ->whereDate('work_date', $workDate)
            ->whereIn('user_id', $userIds)
            ->get()
            ->keyBy('user_id');

        $paginator->through(function (User $staff) use ($records, $tz): array {
            /** @var StaffAttendance|null $record */
            $record = $records->get($staff->id);

            return [
                'user_id' => $staff->id,
                'name' => $staff->name,
                'username' => $staff->username,
                'attendance' => $record !== null ? [
                    'id' => $record->id,
                    'status' => $record->status,
                    'source' => $record->source,
                    'check_in_time' => $record->check_in_at?->setTimezone($tz)->format('H:i'),
                    'check_out_time' => $record->check_out_at?->setTimezone($tz)->format('H:i'),
                    'note' => $record->note,
                ] : null,
            ];
        });

        return Inertia::render('admin/attendance/staff', [
            'rows' => $paginator,
            'filters' => [
                'work_date' => $workDate,
                'search' => $search,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('manage', StaffAttendance::class);

        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'work_date' => ['required', 'date_format:Y-m-d'],
            'status' => ['required', 'in:present,late,leave,sick,absent'],
            'check_in_time' => ['nullable', 'date_format:H:i'],
            'check_out_time' => ['nullable', 'date_format:H:i'],
            'note' => ['nullable', 'string', 'max:255'],
        ], [], [
            'user_id' => 'pegawai',
            'work_date' => 'tanggal',
            'status' => 'status kehadiran',
            'check_in_time' => 'jam masuk',
            'check_out_time' => 'jam pulang',
            'note' => 'catatan',
        ]);

        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        $staff = User::query()->findOrFail((int) $validated['user_id']);

        $this->service->recordManual($actor, $staff, [
            'work_date' => (string) $validated['work_date'],
            'status' => (string) $validated['status'],
            'check_in_time' => $validated['check_in_time'] ?? null,
            'check_out_time' => $validated['check_out_time'] ?? null,
            'note' => $validated['note'] ?? null,
        ]);

        return back()->with('success', 'Presensi pegawai disimpan.');
    }
}
