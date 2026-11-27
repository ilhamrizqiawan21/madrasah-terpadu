<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Sumber data komponen penilaian karakter (ADR-029). Setiap sumber dinormalkan ke 0–100.
 */
enum CharacterSource: string
{
    case HabitsAspect = 'habits_aspect';
    case HabitsItems = 'habits_items';
    case AttendanceOnTime = 'attendance_on_time';
    case ViolationPoints = 'violation_points';
    case MeritPoints = 'merit_points';
    case Memorization = 'memorization';
    case TeacherObservation = 'teacher_observation';

    public function label(): string
    {
        return match ($this) {
            self::HabitsAspect => 'Pembiasaan per aspek',
            self::HabitsItems => 'Pembiasaan item terpilih',
            self::AttendanceOnTime => 'Kehadiran tepat waktu',
            self::ViolationPoints => 'Poin pelanggaran',
            self::MeritPoints => 'Poin kebaikan & prestasi',
            self::Memorization => 'Capaian hafalan',
            self::TeacherObservation => 'Observasi wali kelas',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::HabitsAspect => 'Persentase centang pembiasaan terverifikasi untuk satu aspek.',
            self::HabitsItems => 'Persentase centang terverifikasi untuk item yang dipilih, mis. sholat 5 waktu.',
            self::AttendanceOnTime => 'Hari hadir tepat waktu dibanding hari efektif; izin dan sakit tidak dihitung.',
            self::ViolationPoints => 'Jumlah poin pelanggaran semester, diubah ke skor lewat tingkatan.',
            self::MeritPoints => 'Poin kebaikan dan prestasi yang diperoleh semester ini (bukan saldo).',
            self::Memorization => 'Persentase capaian target hafalan semester.',
            self::TeacherObservation => 'Wali kelas memilih tingkat 1–4 saat penilaian.',
        };
    }

    /** Komponen yang bergantung pada kelengkapan data harian (aturan cakupan ADR-029). */
    public function needsCoverage(): bool
    {
        return in_array($this, [self::HabitsAspect, self::HabitsItems, self::AttendanceOnTime], true);
    }

    public function usesTiers(): bool
    {
        return in_array($this, [self::ViolationPoints, self::MeritPoints], true);
    }

    /**
     * Tingkatan bawaan: skor untuk jumlah poin ≤ `max`; `max` null = sisanya.
     *
     * @return list<array{max: int|null, score: int}>
     */
    public function defaultTiers(): array
    {
        return match ($this) {
            self::ViolationPoints => [
                ['max' => 0, 'score' => 100],
                ['max' => 10, 'score' => 90],
                ['max' => 25, 'score' => 75],
                ['max' => 50, 'score' => 60],
                ['max' => null, 'score' => 40],
            ],
            self::MeritPoints => [
                ['max' => 0, 'score' => 70],
                ['max' => 10, 'score' => 80],
                ['max' => 25, 'score' => 90],
                ['max' => null, 'score' => 100],
            ],
            default => [],
        };
    }
}
