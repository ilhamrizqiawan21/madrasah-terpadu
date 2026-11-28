<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\StaffLeaveRequest;
use App\Models\User;
use App\Services\Attendance\StaffAttendanceService;
use App\Support\AppTimezone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Absensi > Izin & Cuti Pegawai (pengajuan oleh staf, keputusan oleh admin/kepala unit, T-1601).
 */
class StaffLeaveRequestController extends Controller
{
    public function __construct(
        private readonly StaffAttendanceService $service,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', StaffLeaveRequest::class);

        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $canDecide = $user->hasAnyRole([Role::Admin->value, Role::UnitHead->value]);
        $status = $request->string('status')->toString();
        if (! in_array($status, ['pending', 'approved', 'rejected'], true)) {
            $status = '';
        }

        $tz = AppTimezone::name();

        $paginator = StaffLeaveRequest::query()
            ->with(['user:id,name', 'decider:id,name'])
            ->when(! $canDecide, fn (Builder $q) => $q->where('user_id', $user->id))
            ->when($status !== '', fn (Builder $q) => $q->where('status', $status))
            ->orderByRaw("case when status = 'pending' then 0 else 1 end")
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $paginator->through(fn (StaffLeaveRequest $leave): array => [
            'id' => $leave->id,
            'staff_name' => $leave->user->name,
            'type' => $leave->type,
            'starts_on' => $leave->starts_on->toDateString(),
            'ends_on' => $leave->ends_on->toDateString(),
            'reason' => $leave->reason,
            'status' => $leave->status,
            'decider_name' => $leave->decider?->name,
            'decided_at' => $leave->decided_at?->setTimezone($tz)->toIso8601String(),
        ]);

        return Inertia::render('admin/attendance/staff-leaves', [
            'leaves' => $paginator,
            'canDecide' => $canDecide,
            'filters' => [
                'status' => $status,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', StaffLeaveRequest::class);

        $validated = $request->validate([
            'type' => ['required', 'in:leave,sick,duty'],
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'ends_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
            'reason' => ['required', 'string', 'max:500'],
        ], [], [
            'type' => 'jenis izin',
            'starts_on' => 'tanggal mulai',
            'ends_on' => 'tanggal selesai',
            'reason' => 'alasan',
        ]);

        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $this->service->submitLeave($user, [
            'type' => (string) $validated['type'],
            'starts_on' => (string) $validated['starts_on'],
            'ends_on' => (string) $validated['ends_on'],
            'reason' => (string) $validated['reason'],
        ]);

        return back()->with('success', 'Pengajuan izin pegawai dikirim.');
    }

    public function decide(Request $request, StaffLeaveRequest $leave): RedirectResponse
    {
        Gate::authorize('decide', StaffLeaveRequest::class);

        $validated = $request->validate([
            'decision' => ['required', 'in:approved,rejected'],
        ], [], [
            'decision' => 'keputusan',
        ]);

        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $this->service->decideLeave($user, $leave, (string) $validated['decision']);

        return back()->with('success', 'Keputusan izin pegawai disimpan.');
    }
}
