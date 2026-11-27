<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Services\Settings\SettingsRepository;
use App\Support\ExcelCell;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportExportService
{
    public function __construct(
        private readonly SettingsRepository $settings,
    ) {}

    /**
     * @param  list<string>  $headers
     * @param  list<list<string|int|float|null>>  $rows
     */
    public function toPdfResponse(
        string $filename,
        string $title,
        string $subtitle,
        array $headers,
        array $rows,
        string $orientation = 'landscape',
    ): Response {
        $identity = $this->settings->identity();
        $schoolName = $identity['app_name'] ?: $identity['foundation_name'];

        $pdf = Pdf::loadView('pdf.report-table', [
            'schoolName' => $schoolName,
            'title' => $title,
            'subtitle' => $subtitle,
            'generatedAt' => now('Asia/Makassar')->format('d/m/Y H.i').' WITA',
            'headers' => $headers,
            'rows' => $rows,
        ])->setPaper('a4', $orientation);

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * @param  list<string>  $headers
     * @param  list<list<string|int|float|null>>  $rows
     */
    public function toXlsxResponse(string $filename, array $headers, array $rows): BinaryFileResponse
    {
        $path = tempnam(sys_get_temp_dir(), 'report_').'.xlsx';
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues($headers));

        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues(array_map(
                static fn (string|int|float|null $cell): string|int|float => match (true) {
                    is_string($cell) => ExcelCell::neutralize($cell),
                    $cell === null => '-',
                    default => $cell,
                },
                $row,
            )));
        }

        $writer->close();

        return response()
            ->download($path, $filename)
            ->deleteFileAfterSend(true);
    }
}
