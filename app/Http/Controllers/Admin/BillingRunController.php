<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\BillingTargetType;
use App\Enums\StudentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Billing\BillingRunRequest;
use App\Models\AcademicYear;
use App\Models\BillingRun;
use App\Models\Classroom;
use App\Models\FeeType;
use App\Models\Invoice;
use App\Models\Student;
use App\Models\Unit;
use App\Models\User;
use App\Services\Billing\BillingRunService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class BillingRunController extends Controller
{
    public function __construct(
        private readonly BillingRunService $service,
    ) {}

    public function create(Request $request): Response
    {
        Gate::authorize('issue', Invoice::class);

        $activeYear = AcademicYear::query()->where('is_active', true)->first();

        $classrooms = $activeYear !== null
            ? Classroom::query()
                ->where('academic_year_id', $activeYear->id)
                ->where('is_active', true)
                ->orderBy('grade')
                ->orderBy('name')
                ->get(['id', 'unit_id', 'grade', 'name'])
            : collect();

        $students = Student::query()
            ->where('status', StudentStatus::Active)
            ->orderBy('name')
            ->get(['id', 'unit_id', 'nis', 'name'])
            ->map(static fn (Student $s): array => [
                'id' => $s->id,
                'unit_id' => $s->unit_id,
                'nis' => $s->nis,
                'name' => $s->name,
            ])
            ->values()
            ->all();

        $recentRuns = BillingRun::query()
            ->with(['feeType:id,name', 'creator:id,name'])
            ->orderByDesc('id')
            ->limit(10)
            ->get()
            ->map(static fn (BillingRun $run): array => [
                'id' => $run->id,
                'fee_type_name' => $run->feeType->name,
                'target_type_label' => $run->target_type->label(),
                'invoice_count' => $run->invoice_count,
                'total_amount' => $run->total_amount,
                'creator_name' => $run->creator->name,
                'created_at' => $run->created_at->toIso8601String(),
            ])
            ->values()
            ->all();

        return Inertia::render('admin/billing/issue', [
            'feeTypes' => FeeType::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'unit_id', 'name', 'category', 'recurrence', 'default_amount'])
                ->map(static fn (FeeType $ft): array => [
                    'id' => $ft->id,
                    'unit_id' => $ft->unit_id,
                    'name' => $ft->name,
                    'category' => $ft->category,
                    'recurrence' => $ft->recurrence->value,
                    'default_amount' => $ft->default_amount,
                ])
                ->values()
                ->all(),
            'units' => Unit::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
            'activeYear' => $activeYear !== null ? [
                'id' => $activeYear->id,
                'label' => $activeYear->label(),
                'starts_on' => $activeYear->starts_on->toDateString(),
            ] : null,
            'classrooms' => $classrooms,
            'students' => $students,
            'targetTypes' => array_map(
                static fn (BillingTargetType $type): array => ['value' => $type->value, 'label' => $type->label()],
                BillingTargetType::cases(),
            ),
            'recentRuns' => $recentRuns,
        ]);
    }

    public function preview(BillingRunRequest $request): JsonResponse
    {
        return response()->json($this->service->preview($request->validated()));
    }

    public function store(BillingRunRequest $request): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $run = $this->service->execute($actor, $request->validated());

        return back()->with(
            'success',
            "Berhasil menerbitkan {$run->invoice_count} tagihan.",
        );
    }
}
