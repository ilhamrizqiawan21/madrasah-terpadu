<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Modul yang dapat dinyalakan/dimatikan admin di Pengaturan > Kunci fitur (T-211, ADR-021/022).
 * Modul tanpa baris di `feature_flags` memakai defaultEnabled().
 */
enum Feature: string
{
    case Attendance = 'attendance';
    case Behavior = 'behavior';
    case Rewards = 'rewards';
    case Habits = 'habits';
    case Quran = 'quran';
    case Attitude = 'attitude';
    case Cbt = 'cbt';

    case StaffAttendance = 'staff_attendance';
    case LessonSchedule = 'lesson_schedule';
    case Assignments = 'assignments';
    case ReportCards = 'report_cards';
    case CashBook = 'cash_book';
    case Dormitory = 'dormitory';

    case Billing = 'billing';
    case Midtrans = 'midtrans';
    case Wallet = 'wallet';
    case ExamEligibility = 'exam_eligibility';

    public function label(): string
    {
        return match ($this) {
            self::Attendance => 'Presensi siswa',
            self::Behavior => 'Catatan perilaku (BK)',
            self::Rewards => 'Reward & voucher kantin',
            self::Habits => 'Pembiasaan',
            self::Quran => 'Setoran hafalan',
            self::Attitude => 'Nilai sikap',
            self::Cbt => 'Ujian CBT',
            self::StaffAttendance => 'Presensi & izin pegawai',
            self::LessonSchedule => 'Jadwal pelajaran',
            self::Assignments => 'Tugas & PR',
            self::ReportCards => 'E-rapor',
            self::CashBook => 'Buku kas',
            self::Dormitory => 'Asrama',
            self::Billing => 'Tagihan & kasir',
            self::Midtrans => 'Pembayaran online (Midtrans)',
            self::Wallet => 'Saldo kantin',
            self::ExamEligibility => 'Kelayakan ujian',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Attendance => 'Scan kartu di gerbang, rekap harian, izin, dan notifikasi ke wali.',
            self::Behavior => 'Poin pelanggaran dan kebaikan, ambang, dan tindak lanjut BK.',
            self::Rewards => 'Penukaran poin kebaikan menjadi voucher kantin.',
            self::Habits => 'Mutabaah harian yang dicentang wali dan diverifikasi guru.',
            self::Quran => "Setoran Qur'an, hadits, dan doa beserta target dan progres.",
            self::Attitude => 'Nilai sikap per semester dari catatan perilaku.',
            self::Cbt => 'Bank soal, ujian berbasis komputer, dan penilaian.',
            self::StaffAttendance => 'Rekap kehadiran pegawai dan pengajuan izin.',
            self::LessonSchedule => 'Jam pelajaran per unit dan jadwal mingguan per rombel.',
            self::Assignments => 'Tugas per mapel dengan tenggat yang dapat dilihat wali.',
            self::ReportCards => 'Unggah rapor PDF per siswa per semester.',
            self::CashBook => 'Catatan kas masuk dan keluar per unit.',
            self::Dormitory => 'Kamar asrama dan penempatan siswa.',
            self::Billing => 'Jenis tagihan, penerbitan, kasir, dan kuitansi.',
            self::Midtrans => 'Pembayaran tagihan dan isi saldo lewat Midtrans.',
            self::Wallet => 'Saldo jajan siswa, isi saldo, dan kasir kantin.',
            self::ExamEligibility => 'Syarat tagihan lunas untuk mengikuti ujian.',
        };
    }

    public function defaultEnabled(): bool
    {
        // Asrama menunggu konfirmasi cakupan dari klien (T-1606).
        return ! $this->heldUntilCutover() && $this !== self::Dormitory;
    }

    /**
     * Alur uang tetap di aplikasi lama selama masa paralel (ADR-021); kelayakan ujian
     * bergantung pada tagihan, jadi ikut ditunda.
     */
    public function heldUntilCutover(): bool
    {
        return in_array($this, [self::Billing, self::Midtrans, self::Wallet, self::ExamEligibility], true);
    }
}
