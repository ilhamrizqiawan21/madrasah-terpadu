<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\ExamPeriod;
use App\Models\User;
use App\Services\Billing\ExamEligibilityListService;
use App\Services\Reports\ReportExportService;
use App\Support\Money;
use App\Support\SchoolContext;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Daftar kelayakan ujian per rombel dan ekspor PDF/Excel untuk panitia (T-613).
 */
class ExamEligibilityController extends Controller
{
    public function __construct(
        private readonly ExamEligibilityListService $listService,
        private readonly ReportExportService $exporter,
    ) {}

    public function index(Request $request, ExamPeriod $examPeriod): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $context = SchoolContext::resolve($request->session());
        $classrooms = $this->listService->accessibleClassrooms($examPeriod, $user, $context['academicYearId']);
        abort_if($classrooms->isEmpty() && ! $user->can('view', $examPeriod), 403);

        $classroomId = $request->integer('classroom_id') ?: null;
        if ($classroomId !== null && $classrooms->firstWhere('id', $classroomId) === null) {
            abort(403);
        }

        $statusFilter = $request->string('status')->trim()->toString();
        $search = $request->string('search')->trim()->toString();

        $data = $this->listService->build(
            $examPeriod,
            $classrooms,
            $classroomId,
            $statusFilter !== '' ? $statusFilter : null,
            $search !== '' ? $search : null,
            $context['academicYearId'],
        );

        $examPeriod->loadMissing('unit:id,code,name');

        return Inertia::render('admin/billing/exam-eligibility', [
            'period' => [
                'id' => $examPeriod->id,
                'name' => $examPeriod->name,
                'unit_code' => $examPeriod->unit->code,
                'unit_name' => $examPeriod->unit->name,
                'delivery' => [
                    'value' => $examPeriod->delivery->value,
                    'label' => $examPeriod->delivery->label(),
                ],
                'starts_on' => $examPeriod->starts_on->toDateString(),
                'ends_on' => $examPeriod->ends_on->toDateString(),
                'status' => [
                    'value' => $examPeriod->status->value,
                    'label' => $examPeriod->status->label(),
                ],
            ],
            'classrooms' => $classrooms->map(fn ($c): array => ['id' => $c->id, 'name' => $c->name])->values()->all(),
            'summary' => $data['summary'],
            'rows' => $data['rows'],
            'filters' => [
                'classroom_id' => $classroomId !== null ? (string) $classroomId : '',
                'status' => $statusFilter,
                'search' => $search,
            ],
            'canGrantDispensation' => $user->hasRole(Role::Admin->value),
        ]);
    }

    public function exportPdf(Request $request, ExamPeriod $examPeriod): HttpResponse
    {
        [$headers, $rows, $subtitle] = $this->prepareExport($request, $examPeriod);

        return $this->exporter->toPdfResponse(
            "kelayakan-ujian-{$examPeriod->id}.pdf",
            "Daftar Kelayakan Ujian — {$examPeriod->name}",
            $subtitle,
            $headers,
            $rows,
        );
    }

    public function exportExcel(Request $request, ExamPeriod $examPeriod): BinaryFileResponse
    {
        [$headers, $rows] = $this->prepareExport($request, $examPeriod);

        return $this->exporter->toXlsxResponse(
            "kelayakan-ujian-{$examPeriod->id}.xlsx",
            $headers,
            $rows,
        );
    }

    /**
     * @return array{0: list<string>, 1: list<list<string|int>>, 2: string}
     */
    private function prepareExport(Request $request, ExamPeriod $examPeriod): array
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $context = SchoolContext::resolve($request->session());
        $classrooms = $this->listService->accessibleClassrooms($examPeriod, $user, $context['academicYearId']);
        abort_if($classrooms->isEmpty() && ! $user->can('view', $examPeriod), 403);

        $classroomId = $request->integer('classroom_id') ?: null;
        if ($classroomId !== null && $classrooms->firstWhere('id', $classroomId) === null) {
            abort(403);
        }

        $statusFilter = $request->string('status')->trim()->toString();
        $search = $request->string('search')->trim()->toString();

        $data = $this->listService->build(
            $examPeriod,
            $classrooms,
            $classroomId,
            $statusFilter !== '' ? $statusFilter : null,
            $search !== '' ? $search : null,
            $context['academicYearId'],
        );

        $headers = ['NIS', 'Nama Siswa', 'Rombel', 'Status', 'Tagihan Tertunggak', 'Nominal Tertunggak', 'Keterangan'];
        $rows = array_map(static fn (array $r): array => [
            $r['nis'],
            $r['name'],
            $r['classroom_name'],
            $r['status_label'],
            $r['blocking_count'],
            $r['blocking_amount'] > 0 ? Money::format($r['blocking_amount']) : '-',
            $r['dispensation_reason'] ?? '-',
        ], $data['rows']);

        $examPeriod->loadMissing('unit:id,code,name');
        $subtitle = "{$examPeriod->unit->code} · {$examPeriod->delivery->label()} · {$examPeriod->starts_on->format('d/m/Y')} - {$examPeriod->ends_on->format('d/m/Y')}";

        return [$headers, $rows, $subtitle];
    }
}
