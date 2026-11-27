<?php

declare(strict_types=1);

namespace App\Services\Character;

use App\Enums\CharacterReviewStatus;
use App\Models\CharacterAssessment;
use App\Models\CharacterDimension;
use App\Models\CharacterProfile;
use App\Models\CharacterReview;
use App\Models\Student;
use App\Services\Settings\SettingsRepository;
use App\Support\ExcelCell;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Keluaran penilaian karakter (ADR-029, T-1204): data rapor per siswa, PDF lampiran, dan Excel untuk RDM.
 */
final class CharacterReportService
{
    public function __construct(private readonly SettingsRepository $settings) {}

    /**
     * @return list<array{student_id: int, name: string, nis: string, dimensions: list<array{name: string, level: string|null, level_label: string, description: string|null}>}>
     */
    public function rows(CharacterReview $review, ?int $onlyStudentId = null): array
    {
        $unitId = $review->classroom()->value('unit_id');
        $scale = CharacterProfile::query()->where('unit_id', $unitId)->first()->scale ?? [];
        $labels = collect($scale)->pluck('label', 'code');

        $dimensions = CharacterDimension::query()
            ->whereIn('id', $review->assessments()->select('character_dimension_id'))
            ->orderBy('position')
            ->orderBy('id')
            ->get(['id', 'name']);

        /** @var Collection<int, Collection<int, CharacterAssessment>> $byStudent */
        $byStudent = $review->assessments()
            ->with('student:id,name,nis')
            ->when($onlyStudentId !== null, fn ($q) => $q->where('student_id', $onlyStudentId))
            ->get()
            ->groupBy('student_id');

        $rows = [];

        foreach ($byStudent as $assessments) {
            /** @var Student $student */
            $student = $assessments->first()->student;
            $byDimension = $assessments->keyBy('character_dimension_id');

            $rows[] = [
                'student_id' => $student->id,
                'name' => $student->name,
                'nis' => $student->nis,
                'dimensions' => array_values($dimensions->map(function (CharacterDimension $d) use ($byDimension, $labels): array {
                    /** @var CharacterAssessment|null $a */
                    $a = $byDimension->get($d->id);
                    $level = $a?->effectiveLevel();

                    return [
                        'name' => $d->name,
                        'level' => $level,
                        'level_label' => $level !== null ? (string) ($labels[$level] ?? $level) : '-',
                        'description' => $a?->description,
                    ];
                })->all()),
            ];
        }

        usort($rows, fn (array $a, array $b): int => strcmp($a['name'], $b['name']));

        return $rows;
    }

    public function pdf(CharacterReview $review, ?int $onlyStudentId = null): string
    {
        $review->loadMissing(['classroom.unit', 'academicYear', 'approver:id,name']);
        $identity = $this->settings->identity();

        return Pdf::loadView('pdf.character-report', [
            'schoolName' => $identity['app_name'] ?: $identity['foundation_name'],
            'foundationName' => $identity['foundation_name'],
            'classroom' => $review->classroom->name,
            'unit' => $review->classroom->unit->name,
            'semester' => $review->academicYear->name.' · '.$review->academicYear->semester->label(),
            'approved' => $review->status === CharacterReviewStatus::Approved,
            'statusLabel' => $review->status->label(),
            'approvedBy' => $review->approver?->name,
            'approvedAt' => $review->approved_at?->setTimezone('Asia/Makassar')->translatedFormat('j F Y'),
            'students' => $this->rows($review, $onlyStudentId),
        ])->setPaper('a4')->output();
    }

    /**
     * Excel satu baris per siswa per dimensi, siap disalin ke RDM.
     */
    public function excel(CharacterReview $review): string
    {
        $path = tempnam(sys_get_temp_dir(), 'karakter_').'.xlsx';
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues(['NIS', 'Nama siswa', 'Dimensi', 'Capaian', 'Kode capaian', 'Deskripsi']));

        foreach ($this->rows($review) as $row) {
            foreach ($row['dimensions'] as $dimension) {
                $writer->addRow(Row::fromValues([
                    ExcelCell::neutralize($row['nis']),
                    ExcelCell::neutralize($row['name']),
                    ExcelCell::neutralize($dimension['name']),
                    ExcelCell::neutralize($dimension['level_label']),
                    ExcelCell::neutralize((string) ($dimension['level'] ?? '')),
                    ExcelCell::neutralize((string) ($dimension['description'] ?? '')),
                ]));
            }
        }

        $writer->close();

        return $path;
    }
}
