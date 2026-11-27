<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\AcademicYear;
use App\Models\Unit;
use Illuminate\Contracts\Session\Session;

/**
 * Unit & tahun ajaran yang sedang dipilih staf di topbar (T-111), disimpan di sesi.
 * Bawaan: unit aktif pertama (urut kode) dan tahun ajaran yang sedang aktif.
 */
final class SchoolContext
{
    public const SESSION_UNIT = 'active_unit';

    public const SESSION_ACADEMIC_YEAR = 'active_academic_year_id';

    /** Pemilih hanya menampilkan beberapa tahun ajaran terbaru. */
    private const RECENT_YEARS = 6;

    /**
     * @return array{
     *     unit: string|null,
     *     units: list<string>,
     *     academicYear: string|null,
     *     academicYearId: int|null,
     *     academicYears: list<array{id: int, label: string, isActive: bool}>
     * }
     */
    public static function resolve(Session $session): array
    {
        /** @var list<string> $units */
        $units = Unit::query()->where('is_active', true)->orderBy('code')->pluck('code')->all();

        $years = AcademicYear::query()
            ->orderByDesc('name')
            ->orderByDesc('semester')
            ->limit(self::RECENT_YEARS)
            ->get();

        $chosenUnit = $session->get(self::SESSION_UNIT);
        $unit = in_array($chosenUnit, $units, true) ? $chosenUnit : ($units[0] ?? null);

        $chosenYearId = $session->get(self::SESSION_ACADEMIC_YEAR);
        $year = $years->firstWhere('id', $chosenYearId);

        // Tahun lama yang dipilih tetap dipakai dan ditambahkan ke daftar agar pemilih menampilkannya.
        if ($year === null && is_int($chosenYearId)) {
            $year = AcademicYear::query()->find($chosenYearId);

            if ($year !== null) {
                $years->push($year);
            }
        }

        $year ??= $years->firstWhere('is_active', true)
            ?? AcademicYear::query()->where('is_active', true)->orderBy('id')->first();

        return [
            'unit' => $unit,
            'units' => $units,
            'academicYear' => $year?->label(),
            'academicYearId' => $year?->id,
            'academicYears' => array_values($years->map(fn (AcademicYear $item): array => [
                'id' => $item->id,
                'label' => $item->label(),
                'isActive' => $item->is_active,
            ])->all()),
        ];
    }
}
