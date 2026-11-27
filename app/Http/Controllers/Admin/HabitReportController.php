<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Attendance\AttendanceClassifier;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Unit;
use App\Models\User;
use App\Services\Reports\HabitReportService;
use App\Services\Reports\ReportExportService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class HabitReportController extends Controller
{
    public function __construct(
        private readonly HabitReportService $reports,
        private readonly ReportExportService $exporter,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorizeView($request);
        $filters = $this->resolveFilters($request);

        $data = $this->reports->build(
            $filters['mode'],
            $filters['unit_id'],
            $filters['classroom_id'],
            $filters['date_from'],
            $filters['date_to'],
        );

        $page = max(1, $request->integer('page', 1));
        $perPage = 20;
        $total = count($data['rows']);
        $sliced = array_slice($data['rows'], ($page - 1) * $perPage, $perPage);
        $paginator = new LengthAwarePaginator($sliced, $total, $perPage, $page, [
            'path' => $request->url(),
            'query' => $request->query(),
        ]);

        $activeYearId = AcademicYear::query()->where('is_active', true)->value('id');

        return Inertia::render('admin/reports/habits', [
            'filters' => $filters,
            'summary' => $data['summary'],
            'rows' => [
                'data' => $sliced,
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'from' => $paginator->firstItem(),
                    'to' => $paginator->lastItem(),
                ],
            ],
            'units' => Unit::query()->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']),
            'classrooms' => Classroom::query()
                ->with('unit:id,code')
                ->where('is_active', true)
                ->when($activeYearId !== null, fn ($q) => $q->where('academic_year_id', $activeYearId))
                ->when($filters['unit_id'] !== null, fn ($q) => $q->where('unit_id', $filters['unit_id']))
                ->orderBy('grade')
                ->orderBy('name')
                ->get(['id', 'unit_id', 'name'])
                ->map(fn (Classroom $c): array => [
                    'id' => $c->id,
                    'label' => ($c->unit->code ?? '').' · '.$c->name,
                ])
                ->values()
                ->all(),
        ]);
    }

    public function exportPdf(Request $request): HttpResponse
    {
        $this->authorizeView($request);
        $filters = $this->resolveFilters($request);

        $data = $this->reports->build(
            $filters['mode'],
            $filters['unit_id'],
            $filters['classroom_id'],
            $filters['date_from'],
            $filters['date_to'],
        );

        $modeLabel = $filters['mode'] === 'classroom' ? 'Per Rombel' : 'Per Item Pembiasaan';

        return $this->exporter->toPdfResponse(
            "laporan-pembiasaan-{$filters['mode']}.pdf",
            "Laporan Pembiasaan ({$modeLabel})",
            "Periode {$filters['date_from']} s.d. {$filters['date_to']}",
            $data['headers'],
            $this->exportRows($filters['mode'], $data['rows']),
        );
    }

    public function exportExcel(Request $request): BinaryFileResponse
    {
        $this->authorizeView($request);
        $filters = $this->resolveFilters($request);

        $data = $this->reports->build(
            $filters['mode'],
            $filters['unit_id'],
            $filters['classroom_id'],
            $filters['date_from'],
            $filters['date_to'],
        );

        return $this->exporter->toXlsxResponse(
            "laporan-pembiasaan-{$filters['mode']}.xlsx",
            $data['headers'],
            $this->exportRows($filters['mode'], $data['rows']),
        );
    }

    private function authorizeView(Request $request): void
    {
        $actor = $request->user();
        abort_unless($actor instanceof User && $actor->can(Permission::ReportsView->value), 403);
    }

    /**
     * @return array{mode: string, unit_id: int|null, classroom_id: int|null, date_from: string, date_to: string}
     */
    private function resolveFilters(Request $request): array
    {
        $today = CarbonImmutable::now(AttendanceClassifier::BUSINESS_TIMEZONE);
        $mode = $request->query('mode') === 'classroom' ? 'classroom' : 'item';

        $dateFrom = (string) $request->query('date_from', $today->startOfMonth()->toDateString());
        $dateTo = (string) $request->query('date_to', $today->toDateString());

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
            $dateFrom = $today->startOfMonth()->toDateString();
        }
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
            $dateTo = $today->toDateString();
        }

        return [
            'mode' => $mode,
            'unit_id' => $request->filled('unit_id') ? $request->integer('unit_id') : null,
            'classroom_id' => $request->filled('classroom_id') ? $request->integer('classroom_id') : null,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ];
    }

    /**
     * @param  list<array<string, string|int|float>>  $rows
     * @return list<list<string|int|float>>
     */
    private function exportRows(string $mode, array $rows): array
    {
        if ($mode === 'classroom') {
            return array_map(fn (array $r): array => [
                $r['unit_code'], $r['classroom_name'], $r['student_count'],
                $r['total_done'], $r['verified'], $r['avg_per_student'],
            ], $rows);
        }

        return array_map(fn (array $r): array => [
            $r['item_name'], $r['category'], $r['total_done'],
            $r['verified'], $r['unique_students'], $r['coverage_pct'],
        ], $rows);
    }
}
