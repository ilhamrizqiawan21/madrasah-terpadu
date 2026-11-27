<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\LeaveRequestStatus;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DecideLeaveRequest;
use App\Models\AcademicYear;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Services\Attendance\LeaveRequestService;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class LeaveRequestController extends Controller
{
    public function __construct(private readonly LeaveRequestService $service) {}

    public function index(Request $request): Response
    {
        $status = (string) $request->query('status', LeaveRequestStatus::Pending->value);
        $allowed = ['pending', 'approved', 'rejected', 'all'];

        if (! in_array($status, $allowed, true)) {
            $status = LeaveRequestStatus::Pending->value;
        }

        $activeYearId = AcademicYear::query()->where('is_active', true)->value('id');

        $leaves = LeaveRequest::query()
            ->with([
                'student.unit',
                'student.enrollments' => fn (HasMany $q) => $activeYearId !== null
                    ? $q->where('academic_year_id', $activeYearId)->with('classroom')
                    : $q->whereRaw('1 = 0'),
                'requester:id,name',
                'decider:id,name',
            ])
            ->when(
                $status !== 'all',
                fn (Builder $q) => $q->where('status', $status),
            )
            ->orderByRaw("case when status = 'pending' then 0 else 1 end")
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        $actor = $this->actor($request);

        return Inertia::render('admin/attendance/leaves', [
            'leaves' => [
                'data' => $leaves->getCollection()->map(fn (LeaveRequest $item): array => [
                    'id' => $item->id,
                    'student_name' => $item->student->name,
                    'nis' => $item->student->nis,
                    'unit' => $item->student->unit->code,
                    'classroom' => $item->student->enrollments->first()?->classroom->name ?? '-',
                    'type_label' => $item->type->label(),
                    'starts_on' => $item->starts_on->toDateString(),
                    'ends_on' => $item->ends_on->toDateString(),
                    'reason' => $item->reason,
                    'requester_name' => $item->requester->name,
                    'status' => $item->status->value,
                    'status_label' => $item->status->label(),
                    'decision_note' => $item->decision_note,
                    'decider_name' => $item->decider?->name,
                    'attachment_url' => $item->attachment_path !== null
                        ? route('admin.attendance.leaves.attachment', $item)
                        : null,
                ])->values()->all(),
                'meta' => [
                    'current_page' => $leaves->currentPage(),
                    'last_page' => $leaves->lastPage(),
                    'per_page' => $leaves->perPage(),
                    'total' => $leaves->total(),
                    'from' => $leaves->firstItem(),
                    'to' => $leaves->lastItem(),
                ],
            ],
            'filters' => ['status' => $status],
            'canManage' => $actor->can(Permission::AttendanceManage->value),
        ]);
    }

    public function decide(DecideLeaveRequest $request, LeaveRequest $leaveRequest): RedirectResponse
    {
        $validated = $request->validated();

        try {
            $this->service->decide(
                leave: $leaveRequest,
                actor: $this->actor($request),
                approved: (bool) $validated['approved'],
                note: isset($validated['decision_note']) ? (string) $validated['decision_note'] : null,
            );
        } catch (DomainException $exception) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => $exception->getMessage(),
            ]);

            return back();
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => (bool) $validated['approved']
                ? 'Pengajuan izin disetujui.'
                : 'Pengajuan izin ditolak.',
        ]);

        return back();
    }

    public function attachment(LeaveRequest $leaveRequest): BinaryFileResponse
    {
        abort_if($leaveRequest->attachment_path === null || ! Storage::disk('local')->exists($leaveRequest->attachment_path), 404);

        return response()->file(Storage::disk('local')->path($leaveRequest->attachment_path));
    }

    private function actor(Request $request): User
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        return $user;
    }
}
