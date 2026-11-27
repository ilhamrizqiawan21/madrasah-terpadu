<?php

declare(strict_types=1);

namespace App\Services\Guardian;

use App\Models\AcademicYear;
use App\Models\Student;
use Illuminate\Support\Facades\Cache;

/**
 * Menyusun ringkasan Beranda Wali per anak berdasarkan urgensi (T-303).
 *
 * Kartu modul yang belum dibangun atau belum memiliki data tidak dimasukkan
 * ke daftar `cards` (bukan placeholder). Di-cache 60 detik per anak.
 */
final class GuardianHomeSummaryService
{
    public const CACHE_TTL_SECONDS = 60;

    public static function cacheKey(int $studentId): string
    {
        return "guardian.home.student.{$studentId}";
    }

    public function invalidate(int $studentId): void
    {
        Cache::forget(self::cacheKey($studentId));
    }

    /**
     * @return array{
     *     student: array{id: int, nis: string, name: string, nickname: ?string, unit: string, classroom: ?string, status_label: string, photo_url: ?string},
     *     cards: list<array{key: string, priority: int, title: string, summary: string, tone: string, href: ?string}>
     * }
     */
    public function forStudent(Student $student): array
    {
        /** @var array{student: array{id: int, nis: string, name: string, nickname: ?string, unit: string, classroom: ?string, status_label: string, photo_url: ?string}, cards: list<array{key: string, priority: int, title: string, summary: string, tone: string, href: ?string}>} */
        return Cache::remember(
            self::cacheKey($student->id),
            self::CACHE_TTL_SECONDS,
            function () use ($student): array {
                $activeYearId = AcademicYear::query()->where('is_active', true)->value('id');
                $student->load([
                    'unit',
                    'enrollments' => fn ($q) => $activeYearId !== null
                        ? $q->where('academic_year_id', $activeYearId)->with('classroom')
                        : $q->whereRaw('1 = 0'),
                ]);

                $classroom = $student->enrollments->first()?->classroom->name ?? null;

                // Urutan urgensi kartu (DESIGN §3 Wali):
                // 1) Hari ini (presensi), 2) Perlu tindakan (tagihan/mutabaah/reward),
                // 3) Kantin, 4) Perilaku. Modul yang belum dibangun tidak ditampilkan.
                $cards = [];

                return [
                    'student' => [
                        'id' => $student->id,
                        'nis' => $student->nis,
                        'name' => $student->name,
                        'nickname' => $student->nickname,
                        'unit' => $student->unit->code,
                        'classroom' => $classroom,
                        'status_label' => $student->status->label(),
                        'photo_url' => $student->photo_path !== null
                            ? route('guardian.students.photo', ['student' => $student->id])
                            : null,
                    ],
                    'cards' => $cards,
                ];
            },
        );
    }
}
