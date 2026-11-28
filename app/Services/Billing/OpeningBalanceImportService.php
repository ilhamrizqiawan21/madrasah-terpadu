<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\FeeRecurrence;
use App\Enums\InvoiceStatus;
use App\Models\FeeType;
use App\Models\Invoice;
use App\Models\Student;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Exception\OpenSpoutException;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Impor saldo awal tunggakan dari aplikasi lama via Excel/CSV (T-610).
 */
final class OpeningBalanceImportService
{
    private const MAX_ROWS = 1500;

    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    public function downloadTemplate(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            $writer = new Writer;
            $writer->openToFile('php://output');
            $writer->addRow(Row::fromValues(['nis', 'deskripsi', 'nominal', 'jatuh_tempo']));
            $writer->addRow(Row::fromValues(['2026001', 'Tunggakan SPP 2025/2026', 150000, '2026-07-15']));
            $writer->close();
        }, 'template-saldo-awal.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * @return array{
     *   rows: list<array{
     *     row: int,
     *     nis: string,
     *     student_name: string|null,
     *     description: string,
     *     amount: int,
     *     due_date: string,
     *     status: string,
     *     error: string|null
     *   }>,
     *   summary: array{will_create: int, already_exists: int, error: int, total_amount: int}
     * }
     */
    public function preview(UploadedFile $file): array
    {
        $parsed = $this->parseFile($file);
        $openingFeeType = FeeType::query()->where('name', 'Saldo Awal')->first();

        $nisList = array_values(array_unique(array_filter(array_column($parsed, 'nis'))));
        $studentsByNis = $nisList === []
            ? collect()
            : Student::query()->whereIn('nis', $nisList)->get(['id', 'nis', 'name'])->keyBy('nis');

        $existingKeys = [];
        if ($openingFeeType !== null && $studentsByNis->isNotEmpty()) {
            $existing = Invoice::query()
                ->where('fee_type_id', $openingFeeType->id)
                ->whereIn('student_id', $studentsByNis->pluck('id')->all())
                ->get(['student_id', 'description']);

            foreach ($existing as $inv) {
                $existingKeys[$this->rowKey($inv->student_id, $inv->description)] = true;
            }
        }

        $seenKeys = [];
        $rows = [];
        $willCreate = 0;
        $alreadyExists = 0;
        $errorCount = 0;
        $totalAmount = 0;

        foreach ($parsed as $item) {
            $student = $studentsByNis->get($item['nis']);
            $error = $item['error'];

            if ($error === null && $student === null) {
                $error = "NIS {$item['nis']} tidak ditemukan.";
            }

            if ($error !== null) {
                $status = 'error';
                $errorCount++;
            } else {
                /** @var Student $student */
                $key = $this->rowKey($student->id, $item['description']);
                if (isset($existingKeys[$key]) || isset($seenKeys[$key])) {
                    $status = 'already_exists';
                    $alreadyExists++;
                } else {
                    $status = 'will_create';
                    $seenKeys[$key] = true;
                    $willCreate++;
                    $totalAmount += $item['amount'];
                }
            }

            $rows[] = [
                'row' => $item['row'],
                'nis' => $item['nis'],
                'student_name' => $student?->name,
                'description' => $item['description'],
                'amount' => $item['amount'],
                'due_date' => $item['due_date'],
                'status' => $status,
                'error' => $error,
            ];
        }

        return [
            'rows' => $rows,
            'summary' => [
                'will_create' => $willCreate,
                'already_exists' => $alreadyExists,
                'error' => $errorCount,
                'total_amount' => $totalAmount,
            ],
        ];
    }

    /**
     * @return array{created: int, skipped: int, failed: list<array{row: int, error: string}>}
     */
    public function import(User $actor, UploadedFile $file): array
    {
        $parsed = $this->parseFile($file);
        $openingFeeType = $this->ensureOpeningFeeType();

        $nisList = array_values(array_unique(array_filter(array_column($parsed, 'nis'))));
        $studentsByNis = $nisList === []
            ? collect()
            : Student::query()->whereIn('nis', $nisList)->get(['id', 'nis', 'name'])->keyBy('nis');

        $created = 0;
        $skipped = 0;
        $failed = [];
        $totalAmount = 0;

        foreach (array_chunk($parsed, 100) as $chunk) {
            DB::transaction(function () use (
                $chunk,
                $openingFeeType,
                $studentsByNis,
                &$created,
                &$skipped,
                &$failed,
                &$totalAmount,
            ): void {
                $studentIds = $studentsByNis->pluck('id')->all();
                $existing = $studentIds === []
                    ? collect()
                    : Invoice::query()
                        ->where('fee_type_id', $openingFeeType->id)
                        ->whereIn('student_id', $studentIds)
                        ->lockForUpdate()
                        ->get(['student_id', 'description']);

                $existingKeys = [];
                foreach ($existing as $inv) {
                    $existingKeys[$this->rowKey($inv->student_id, $inv->description)] = true;
                }

                foreach ($chunk as $item) {
                    if ($item['error'] !== null) {
                        $failed[] = ['row' => $item['row'], 'error' => $item['error']];

                        continue;
                    }

                    $student = $studentsByNis->get($item['nis']);
                    if ($student === null) {
                        $failed[] = ['row' => $item['row'], 'error' => "NIS {$item['nis']} tidak ditemukan."];

                        continue;
                    }

                    $key = $this->rowKey($student->id, $item['description']);
                    if (isset($existingKeys[$key])) {
                        $skipped++;

                        continue;
                    }

                    Invoice::query()->create([
                        'student_id' => $student->id,
                        'fee_type_id' => $openingFeeType->id,
                        'billing_run_id' => null,
                        'period' => null,
                        'description' => $item['description'],
                        'amount' => $item['amount'],
                        'adjustment_total' => 0,
                        'paid_total' => 0,
                        'due_date' => $item['due_date'],
                        'status' => InvoiceStatus::Unpaid,
                    ]);

                    $existingKeys[$key] = true;
                    $created++;
                    $totalAmount += $item['amount'];
                }
            });
        }

        DB::transaction(function () use ($actor, $openingFeeType, $created, $skipped, $failed, $totalAmount): void {
            $this->audit->record(
                action: 'opening_balance.imported',
                subject: $openingFeeType,
                after: [
                    'created' => $created,
                    'skipped' => $skipped,
                    'failed_count' => count($failed),
                    'total_amount' => $totalAmount,
                ],
                userId: $actor->id,
            );
        });

        return [
            'created' => $created,
            'skipped' => $skipped,
            'failed' => $failed,
        ];
    }

    private function ensureOpeningFeeType(): FeeType
    {
        return FeeType::query()->firstOrCreate(
            ['unit_id' => null, 'name' => 'Saldo Awal'],
            [
                'category' => 'Saldo Awal',
                'recurrence' => FeeRecurrence::Once,
                'default_amount' => 0,
                'is_active' => true,
            ],
        );
    }

    private function rowKey(int $studentId, string $description): string
    {
        return $studentId.'|'.mb_strtolower(trim($description));
    }

    /**
     * @return list<array{row: int, nis: string, description: string, amount: int, due_date: string, error: string|null}>
     */
    private function parseFile(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $reader = $extension === 'csv' ? new CsvReader : new XlsxReader;
        $defaultDueDate = CarbonImmutable::now('Asia/Makassar')->toDateString();

        $results = [];

        try {
            $reader->open($file->getRealPath());
            $headerMap = null;
            $dataCount = 0;

            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $rowIndex => $row) {
                    $rawCells = $row->toArray();

                    if ($headerMap === null) {
                        $headerCells = array_map(
                            static fn (mixed $v): string => (is_scalar($v) || $v === null) ? mb_strtolower(trim((string) $v)) : '',
                            $rawCells,
                        );
                        $headerMap = $this->resolveHeaderMap($headerCells);
                        if ($headerMap === null) {
                            throw ValidationException::withMessages([
                                'file' => 'Header wajib memuat kolom nis, deskripsi, dan nominal.',
                            ]);
                        }

                        continue;
                    }

                    $nis = $this->cellString($rawCells[$headerMap['nis']] ?? null);
                    $description = $this->cellString($rawCells[$headerMap['deskripsi']] ?? null);
                    $amountRaw = $this->cellString($rawCells[$headerMap['nominal']] ?? null);
                    $dueDateRaw = $headerMap['jatuh_tempo'] !== null
                        ? ($rawCells[$headerMap['jatuh_tempo']] ?? null)
                        : null;

                    if ($nis === '' && $description === '' && $amountRaw === '') {
                        continue;
                    }

                    $dataCount++;
                    if ($dataCount > self::MAX_ROWS) {
                        throw ValidationException::withMessages([
                            'file' => 'Jumlah baris melebihi batas maksimal 1.500 baris.',
                        ]);
                    }

                    $error = null;
                    $amount = (int) preg_replace('/[^0-9-]/', '', $amountRaw);
                    $dueDate = $defaultDueDate;

                    if ($nis === '') {
                        $error = 'NIS wajib diisi.';
                    } elseif ($description === '') {
                        $error = 'Deskripsi tagihan wajib diisi.';
                    } elseif ($amount <= 0) {
                        $error = 'Nominal harus lebih dari nol.';
                    } else {
                        $parsedDue = $this->parseDateCell($dueDateRaw, $defaultDueDate);
                        if ($parsedDue === null) {
                            $error = 'Format tanggal jatuh tempo tidak valid (gunakan YYYY-MM-DD).';
                        } else {
                            $dueDate = $parsedDue;
                        }
                    }

                    $results[] = [
                        'row' => $rowIndex,
                        'nis' => $nis,
                        'description' => mb_substr($description, 0, 160),
                        'amount' => max(0, $amount),
                        'due_date' => $dueDate,
                        'error' => $error,
                    ];
                }

                break;
            }
        } catch (OpenSpoutException) {
            throw ValidationException::withMessages([
                'file' => 'Berkas tidak dapat dibaca. Pastikan format Excel (.xlsx) atau CSV valid.',
            ]);
        } finally {
            $reader->close();
        }

        if ($results === []) {
            throw ValidationException::withMessages([
                'file' => 'Berkas tidak berisi baris data.',
            ]);
        }

        return $results;
    }

    /**
     * @param  array<int, string>  $headers
     * @return array{nis: int, deskripsi: int, nominal: int, jatuh_tempo: int|null}|null
     */
    private function resolveHeaderMap(array $headers): ?array
    {
        $nis = null;
        $deskripsi = null;
        $nominal = null;
        $jatuhTempo = null;

        foreach ($headers as $idx => $h) {
            $clean = str_replace(['_', '-'], ' ', $h);
            if ($nis === null && in_array($clean, ['nis'], true)) {
                $nis = $idx;
            } elseif ($deskripsi === null && in_array($clean, ['deskripsi', 'description', 'keterangan'], true)) {
                $deskripsi = $idx;
            } elseif ($nominal === null && in_array($clean, ['nominal', 'amount', 'jumlah', 'sisa'], true)) {
                $nominal = $idx;
            } elseif ($jatuhTempo === null && in_array($clean, ['jatuh tempo', 'due date'], true)) {
                $jatuhTempo = $idx;
            }
        }

        if ($nis === null || $deskripsi === null || $nominal === null) {
            return null;
        }

        return [
            'nis' => $nis,
            'deskripsi' => $deskripsi,
            'nominal' => $nominal,
            'jatuh_tempo' => $jatuhTempo,
        ];
    }

    private function cellString(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        return is_scalar($value) ? trim((string) $value) : '';
    }

    private function parseDateCell(mixed $value, string $fallback): ?string
    {
        if ($value === null || $value === '') {
            return $fallback;
        }

        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value)->toDateString();
        }

        $text = $this->cellString($value);
        if ($text === '') {
            return $fallback;
        }

        try {
            return CarbonImmutable::parse($text)->toDateString();
        } catch (Throwable) {
            return null;
        }
    }
}
