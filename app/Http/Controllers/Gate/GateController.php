<?php

declare(strict_types=1);

namespace App\Http\Controllers\Gate;

use App\Domain\Attendance\AttendanceClassifier;
use App\Enums\GateScanResult;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Gate\GateScanRequest;
use App\Models\AcademicYear;
use App\Models\GateScan;
use App\Models\User;
use App\Services\Attendance\GateScanService;
use App\Services\Settings\SettingsRepository;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GateController extends Controller
{
    public function __construct(
        private readonly GateScanService $gateScan,
        private readonly SettingsRepository $settings,
    ) {}

    public function index(Request $request): Response
    {
        $user = $this->actor($request);
        $today = CarbonImmutable::now(AttendanceClassifier::BUSINESS_TIMEZONE)->toDateString();
        $activeYearId = AcademicYear::query()->where('is_active', true)->value('id');

        $recentScans = GateScan::query()
            ->with([
                'student.unit',
                'student.enrollments' => fn (HasMany $q) => $activeYearId !== null
                    ? $q->where('academic_year_id', $activeYearId)->with('classroom')
                    : $q->whereRaw('1 = 0'),
            ])
            ->whereDate('school_date', $today)
            ->orderByDesc('scanned_at')
            ->orderByDesc('id')
            ->limit(10)
            ->get()
            ->map(fn (GateScan $scan): array => [
                'id' => $scan->id,
                'student_name' => $scan->student->name,
                'nis' => $scan->student->nis,
                'unit' => $scan->student->unit->code,
                'classroom' => $scan->student->enrollments->first()?->classroom->name ?? '-',
                'result' => $scan->result->value,
                'result_label' => $scan->result->label(),
                'time_label' => $scan->scanned_at->setTimezone(AttendanceClassifier::BUSINESS_TIMEZONE)->format('H.i'),
            ])
            ->values()
            ->all();

        $identity = $this->settings->identity();

        return Inertia::render('gate/index', [
            'schoolName' => $identity['app_name'] ?: $identity['foundation_name'],
            'operatorName' => $user->name,
            'hasAdminAccess' => $user->hasAnyRole(array_map(fn (Role $r): string => $r->value, Role::adminArea())),
            'recentScans' => $recentScans,
        ]);
    }

    public function scan(GateScanRequest $request): JsonResponse
    {
        $outcome = $this->gateScan->scan(
            rawToken: $request->string('token')->toString(),
            actor: $this->actor($request),
            deviceLabel: $request->string('device_label')->toString() ?: 'Gerbang',
        );

        $studentData = null;

        if ($outcome->student !== null) {
            $activeYearId = AcademicYear::query()->where('is_active', true)->value('id');
            $student = $outcome->student;

            if ($activeYearId !== null) {
                $student->loadMissing([
                    'unit',
                    'enrollments' => fn (HasMany $q) => $q->where('academic_year_id', $activeYearId)->with('classroom'),
                ]);
            }

            $studentData = [
                'id' => $student->id,
                'name' => $student->name,
                'nis' => $student->nis,
                'unit' => $student->unit->code,
                'classroom' => $student->enrollments->first()?->classroom->name ?? '-',
                'photo_url' => $student->photo_path !== null
                    ? route('admin.students.photo', ['student' => $student->id])
                    : null,
            ];
        }

        $tone = match ($outcome->result) {
            GateScanResult::OnTime, GateScanResult::CheckOut => 'success',
            GateScanResult::Late => 'warning',
            default => 'error',
        };

        return response()->json([
            'accepted' => $outcome->accepted,
            'code' => $outcome->code,
            'message' => $outcome->message,
            'tone' => $tone,
            'time_label' => $outcome->timeLabel ?? CarbonImmutable::now(AttendanceClassifier::BUSINESS_TIMEZONE)->format('H.i'),
            'scan_id' => $outcome->scan?->id,
            'student' => $studentData,
        ]);
    }

    private function actor(Request $request): User
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        return $user;
    }
}
