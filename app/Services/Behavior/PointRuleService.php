<?php

declare(strict_types=1);

namespace App\Services\Behavior;

use App\Enums\PointCategory;
use App\Models\PointRule;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Exception\OpenSpoutException;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PointRuleService
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $actor, array $data): PointRule
    {
        $normalized = $this->normalizeAndValidate($data);

        return DB::transaction(function () use ($actor, $normalized): PointRule {
            $rule = PointRule::query()->create($normalized);

            $this->audit->record(
                action: 'point_rule.created',
                subject: $rule,
                after: $rule->only(['unit_id', 'code', 'name', 'category', 'level', 'points', 'is_active']),
                userId: $actor->id,
            );

            return $rule;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $actor, PointRule $rule, array $data): PointRule
    {
        $normalized = $this->normalizeAndValidate($data, $rule->id);

        return DB::transaction(function () use ($actor, $rule, $normalized): PointRule {
            $before = $rule->only(['unit_id', 'code', 'name', 'category', 'level', 'points', 'is_active']);
            $rule->update($normalized);

            $this->audit->record(
                action: 'point_rule.updated',
                subject: $rule,
                before: $before,
                after: $rule->only(['unit_id', 'code', 'name', 'category', 'level', 'points', 'is_active']),
                userId: $actor->id,
            );

            return $rule;
        });
    }

    public function toggleActive(User $actor, PointRule $rule, bool $isActive): PointRule
    {
        return DB::transaction(function () use ($actor, $rule, $isActive): PointRule {
            $before = ['is_active' => $rule->is_active];
            $rule->update(['is_active' => $isActive]);

            $this->audit->record(
                action: $isActive ? 'point_rule.activated' : 'point_rule.deactivated',
                subject: $rule,
                before: $before,
                after: ['is_active' => $rule->is_active],
                userId: $actor->id,
            );

            return $rule;
        });
    }

    /**
     * @return array{created: int, updated: int, errors: list<string>}
     */
    public function import(User $actor, UploadedFile $file, ?int $unitId): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $reader = $extension === 'csv' ? new CsvReader : new XlsxReader;

        $created = 0;
        $updated = 0;
        $errors = [];

        try {
            $reader->open($file->getRealPath());

            DB::transaction(function () use ($reader, $actor, $unitId, &$created, &$updated, &$errors): void {
                $headerMap = null;

                foreach ($reader->getSheetIterator() as $sheet) {
                    foreach ($sheet->getRowIterator() as $rowIndex => $row) {
                        $cells = array_values(array_map(
                            static fn (mixed $value): string => (is_scalar($value) || $value === null) ? trim((string) $value) : '',
                            $row->toArray()
                        ));

                        if ($headerMap === null) {
                            $headerMap = $this->resolveHeaderMap($cells);
                            if ($headerMap === null) {
                                throw ValidationException::withMessages([
                                    'file' => 'Header wajib memuat kolom kode, nama, kategori, dan poin.',
                                ]);
                            }

                            continue;
                        }

                        if (implode('', $cells) === '') {
                            continue;
                        }

                        $code = mb_strtoupper($cells[$headerMap['kode']] ?? '');
                        $name = $cells[$headerMap['nama']] ?? '';
                        $rawCategory = $cells[$headerMap['kategori']] ?? '';
                        $level = $headerMap['level'] !== null ? trim($cells[$headerMap['level']] ?? '') : '';
                        $rawPoints = $cells[$headerMap['poin']] ?? '';

                        $category = PointCategory::fromInput($rawCategory);
                        $points = is_numeric($rawPoints) ? (int) $rawPoints : 0;

                        if ($code === '' || $name === '' || $category === null || $points <= 0) {
                            $errors[] = "Baris {$rowIndex}: data tidak valid (kode, nama, kategori, atau poin <= 0).";

                            continue;
                        }

                        $existing = PointRule::query()
                            ->where('code', $code)
                            ->when(
                                $unitId === null,
                                fn ($q) => $q->whereNull('unit_id'),
                                fn ($q) => $q->where('unit_id', $unitId)
                            )
                            ->first();

                        $payload = [
                            'unit_id' => $unitId,
                            'code' => $code,
                            'name' => $name,
                            'category' => $category->value,
                            'level' => $category === PointCategory::Achievement && $level !== '' ? $level : null,
                            'points' => $points,
                            'is_active' => true,
                        ];

                        if ($existing !== null) {
                            $existing->update($payload);
                            $updated++;
                        } else {
                            PointRule::query()->create($payload);
                            $created++;
                        }
                    }

                    break;
                }

                $this->audit->record(
                    action: 'point_rule.imported',
                    subject: PointRule::class,
                    after: ['unit_id' => $unitId, 'created' => $created, 'updated' => $updated, 'errors_count' => count($errors)],
                    userId: $actor->id,
                );
            });
        } catch (OpenSpoutException $e) {
            throw ValidationException::withMessages([
                'file' => 'Berkas tidak dapat dibaca: '.$e->getMessage(),
            ]);
        } finally {
            $reader->close();
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'errors' => $errors,
        ];
    }

    public function downloadTemplate(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            $writer = new Writer;
            $writer->openToFile('php://output');
            $writer->addRow(Row::fromValues(['kode', 'nama', 'kategori', 'level', 'poin']));
            $writer->addRow(Row::fromValues(['P-01', 'Terlambat masuk sekolah', 'pelanggaran', '', 3]));
            $writer->addRow(Row::fromValues(['PR-01', 'Juara lomba tingkat kabupaten', 'prestasi', 'Kabupaten', 20]));
            $writer->addRow(Row::fromValues(['KB-01', 'Menjaga kebersihan kelas', 'kebaikan', '', 3]));
            $writer->close();
        }, 'template-aturan-poin.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{unit_id: int|null, code: string, name: string, category: string, level: string|null, points: int, is_active: bool}
     */
    private function normalizeAndValidate(array $data, ?int $ignoreId = null): array
    {
        $unitId = isset($data['unit_id']) && $data['unit_id'] !== '' ? (int) $data['unit_id'] : null;
        $code = mb_strtoupper(trim((string) ($data['code'] ?? '')));
        $points = (int) ($data['points'] ?? 0);
        $category = PointCategory::from((string) ($data['category'] ?? ''));

        if ($points <= 0) {
            throw ValidationException::withMessages([
                'points' => 'Bobot poin harus lebih dari 0.',
            ]);
        }

        $duplicateExists = PointRule::query()
            ->where('code', $code)
            ->when(
                $unitId === null,
                fn ($q) => $q->whereNull('unit_id'),
                fn ($q) => $q->where('unit_id', $unitId)
            )
            ->when($ignoreId !== null, fn ($q) => $q->whereKeyNot($ignoreId))
            ->exists();

        if ($duplicateExists) {
            throw ValidationException::withMessages([
                'code' => 'Kode aturan sudah dipakai pada unit ini.',
            ]);
        }

        $level = $category === PointCategory::Achievement && isset($data['level']) && trim((string) $data['level']) !== ''
            ? trim((string) $data['level'])
            : null;

        return [
            'unit_id' => $unitId,
            'code' => $code,
            'name' => trim($data['name']),
            'category' => $category->value,
            'level' => $level,
            'points' => $points,
            'is_active' => $data['is_active'] ?? true,
        ];
    }

    /**
     * @param  list<string>  $cells
     * @return array{kode: int, nama: int, kategori: int, level: int|null, poin: int}|null
     */
    private function resolveHeaderMap(array $cells): ?array
    {
        $normalized = array_map(static fn (string $h): string => mb_strtolower(trim($h)), $cells);
        $kode = array_search('kode', $normalized, true);
        $nama = array_search('nama', $normalized, true);
        $kategori = array_search('kategori', $normalized, true);
        $poin = array_search('poin', $normalized, true);
        $level = array_search('level', $normalized, true);

        if ($kode === false || $nama === false || $kategori === false || $poin === false) {
            return null;
        }

        return [
            'kode' => (int) $kode,
            'nama' => (int) $nama,
            'kategori' => (int) $kategori,
            'level' => $level !== false ? (int) $level : null,
            'poin' => (int) $poin,
        ];
    }
}
