<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\GrantExamDispensationRequest;
use App\Models\ExamEligibilityOverride;
use App\Models\ExamPeriod;
use App\Models\Student;
use App\Models\User;
use App\Services\Billing\ExamDispensationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Pemberian dan pencabutan dispensasi kelayakan ujian (khusus Admin, T-612).
 */
class ExamDispensationController extends Controller
{
    public function __construct(
        private readonly ExamDispensationService $service,
    ) {}

    public function store(GrantExamDispensationRequest $request, ExamPeriod $examPeriod): RedirectResponse
    {
        Gate::authorize('grant', ExamEligibilityOverride::class);

        $user = $request->user();
        abort_unless($user instanceof User, 403);

        /** @var array{student_id: int, reason: string} $validated */
        $validated = $request->validated();
        $student = Student::query()->findOrFail((int) $validated['student_id']);

        $this->service->grant($user, $examPeriod, $student, $validated['reason']);

        return back()->with('success', 'Dispensasi ujian berhasil diberikan.');
    }

    public function revoke(Request $request, ExamEligibilityOverride $override): RedirectResponse
    {
        Gate::authorize('revoke', $override);

        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $this->service->revoke($user, $override);

        return back()->with('success', 'Dispensasi ujian berhasil dicabut.');
    }
}
