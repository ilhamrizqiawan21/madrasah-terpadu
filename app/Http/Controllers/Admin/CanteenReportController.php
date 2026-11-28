<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Unit;
use App\Services\Reports\CanteenReportService;
use App\Services\Reports\ReportExportService;
use App\Support\Money;
use App\Support\SchoolContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Laporan penjualan kantin per kasir, top-up per metode, saldo mengendap, dan voucher (T-807 / T-1304).
 */
class CanteenReportController extends Controller
{
    public function __construct(
        private readonly CanteenReportService $reportService,
        private readonly ReportExportService $exporter,
    ) {}

    public function index(Request $request): Response
    {
        [$unit, $fromDate, $toDate, $unitCode] = $this->resolveFilters($request);
        $data = $this->reportService->build($unit, $fromDate, $toDate);
        $units = Unit::query()->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']);

        return Inertia::render('admin/reports/canteen', [
            'summary' => $data['summary'],
            'byCashier' => $data['by_cashier'],
            'units' => $units->map(fn (Unit $u): array => ['code' => $u->code, 'name' => $u->name])->values()->all(),
            'filters' => [
                'unit' => $unitCode,
                'from' => $fromDate,
                'to' => $toDate,
            ],
        ]);
    }

    public function exportPdf(Request $request): HttpResponse
    {
        [$unit, $fromDate, $toDate, $unitCode] = $this->resolveFilters($request);
        $data = $this->reportService->build($unit, $fromDate, $toDate);

        $headers = ['Kasir', 'Transaksi', 'Total Belanja', 'Dari Dompet', 'Subsidi Voucher'];
        $rows = array_map(static fn (array $r): array => [
            $r['cashier_name'],
            $r['tx_count'],
            Money::format($r['total']),
            Money::format($r['paid_from_wallet']),
            Money::format($r['paid_from_vouchers']),
        ], $data['by_cashier']);

        $subtitle = ($unitCode === 'all' ? 'Semua unit' : "Unit {$unitCode}").
            " · {$fromDate} s.d. {$toDate} · Saldo mengendap: ".Money::format($data['summary']['floating_balance']);

        return $this->exporter->toPdfResponse(
            "laporan-kantin-{$fromDate}-{$toDate}.pdf",
            'Laporan Kantin & Dompet Siswa',
            $subtitle,
            $headers,
            $rows,
        );
    }

    public function exportExcel(Request $request): BinaryFileResponse
    {
        [$unit, $fromDate, $toDate] = $this->resolveFilters($request);
        $data = $this->reportService->build($unit, $fromDate, $toDate);

        $headers = ['Kasir', 'Transaksi', 'Total Belanja', 'Dari Dompet', 'Subsidi Voucher'];
        $rows = array_map(static fn (array $r): array => [
            $r['cashier_name'],
            $r['tx_count'],
            $r['total'],
            $r['paid_from_wallet'],
            $r['paid_from_vouchers'],
        ], $data['by_cashier']);

        return $this->exporter->toXlsxResponse(
            "laporan-kantin-{$fromDate}-{$toDate}.xlsx",
            $headers,
            $rows,
        );
    }

    /**
     * @return array{0: ?Unit, 1: string, 2: string, 3: string}
     */
    private function resolveFilters(Request $request): array
    {
        $context = SchoolContext::resolve($request->session());
        $unitCode = $request->string('unit')->trim()->toString();
        if ($unitCode === '' && $context['unit'] !== null) {
            $unitCode = $context['unit'];
        }

        $unit = ($unitCode !== '' && $unitCode !== 'all')
            ? Unit::query()->where('code', $unitCode)->first()
            : null;

        $today = CarbonImmutable::now('Asia/Makassar')->toDateString();
        $fromDate = $request->string('from')->trim()->toString() ?: $today;
        $toDate = $request->string('to')->trim()->toString() ?: $today;

        return [$unit, $fromDate, $toDate, $unit !== null ? $unit->code : 'all'];
    }
}
