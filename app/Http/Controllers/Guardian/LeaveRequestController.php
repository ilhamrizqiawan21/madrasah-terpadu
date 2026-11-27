<?php

declare(strict_types=1);

namespace App\Http\Controllers\Guardian;

use App\Enums\LeaveType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Guardian\StoreLeaveRequest;
use App\Models\LeaveRequest;
use App\Models\Student;
use App\Models\User;
use App\Services\Attendance\LeaveRequestService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class LeaveRequestController extends Controller
{
    public function __construct(private readonly LeaveRequestService $service) {}

    public function create(Student $student): Response
    {
        $requests = LeaveRequest::query()
            ->where('student_id', $student->id)
            ->orderByDesc('starts_on')
            ->orderByDesc('id')
            ->limit(15)
            ->get()
            ->map(fn (LeaveRequest $item): array => [
                'id' => $item->id,
                'type_label' => $item->type->label(),
                'starts_on' => $item->starts_on->toDateString(),
                'ends_on' => $item->ends_on->toDateString(),
                'reason' => $item->reason,
                'status' => $item->status->value,
                'status_label' => $item->status->label(),
                'decision_note' => $item->decision_note,
            ])
            ->values()
            ->all();

        return Inertia::render('guardian/leave-create', [
            'student' => [
                'id' => $student->id,
                'name' => $student->name,
            ],
            'leaveTypes' => array_map(
                fn (LeaveType $t): array => ['value' => $t->value, 'label' => $t->label()],
                LeaveType::cases(),
            ),
            'backdateDays' => (int) config('sekolah.attendance.leave_backdate_days', 3),
            'requests' => $requests,
        ]);
    }

    public function store(StoreLeaveRequest $request, Student $student): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $validated = $request->validated();

        try {
            $this->service->submit(
                student: $student,
                guardianUser: $user,
                data: [
                    'type' => LeaveType::from((string) $validated['type']),
                    'starts_on' => (string) $validated['starts_on'],
                    'ends_on' => (string) $validated['ends_on'],
                    'reason' => (string) $validated['reason'],
                ],
                attachment: $request->file('attachment'),
            );
        } catch (DomainException $exception) {
            return back()->withErrors(['starts_on' => $exception->getMessage()]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Pengajuan izin berhasil dikirim dan menunggu persetujuan.',
        ]);

        return back();
    }

    public function attachment(Student $student, LeaveRequest $leaveRequest): BinaryFileResponse
    {
        abort_if($leaveRequest->student_id !== $student->id, 404);
        abort_if($leaveRequest->attachment_path === null || ! Storage::disk('local')->exists($leaveRequest->attachment_path), 404);

        return response()->file(Storage::disk('local')->path($leaveRequest->attachment_path));
    }
}
