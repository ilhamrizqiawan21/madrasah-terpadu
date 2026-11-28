<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\Permission as P;

/**
 * Susunan izin untuk halaman Peran & Hak Akses: dikelompokkan seperti sidebar, tiap fitur punya
 * kolom "Lihat" dan/atau "Kelola" (keputusan pemilik 2026-11-28). Setiap kasus Permission muncul
 * tepat satu kali (dijaga tes).
 *
 * @phpstan-type CatalogRow array{label: string, view: ?P, manage: ?P}
 */
final class PermissionCatalog
{
    /**
     * @return list<array{label: string, rows: list<CatalogRow>}>
     */
    public static function groups(): array
    {
        return [
            ['label' => 'Pengguna', 'rows' => [
                self::row('Guru & Staf', null, P::UsersManage),
                self::row('Siswa', P::StudentsView, P::StudentsManage),
                self::row('Orang Tua / Wali', null, P::GuardiansManage),
            ]],
            ['label' => 'Akademik', 'rows' => [
                self::row('Tahun Ajaran', null, P::AcademicYearsManage),
                self::row('Rombel', null, P::ClassroomsManage),
                self::row('Unggah modul ajar (RPP)', null, P::TeachingModulesUpload),
                self::row('Supervisi modul ajar', null, P::TeachingModulesReview),
                self::row('Booking ruangan & lab', null, P::RoomsBook),
            ]],
            ['label' => 'E-Learning', 'rows' => [
                self::row('Ujian milik sendiri', P::CbtView, P::CbtTeach),
                self::row('Semua ujian, mapel & penugasan guru', null, P::CbtManage),
            ]],
            ['label' => 'Absensi', 'rows' => [
                self::row('Kehadiran & izin siswa', P::AttendanceView, P::AttendanceManage),
                self::row('Scan kartu di gerbang', null, P::AttendanceScan),
            ]],
            ['label' => 'Kesiswaan & Asrama', 'rows' => [
                self::row('Buku Saku BK (catatan perilaku)', P::BehaviorView, P::BehaviorRecord),
                self::row('Batalkan catatan perilaku', null, P::BehaviorVoid),
                self::row('Tata tertib & poin', null, P::PointRulesManage),
                self::row('Tindak lanjut BK', null, P::ThresholdsManage),
                self::row('Persetujuan reward', null, P::RewardsApprove),
                self::row('Item mutabaah', null, P::HabitsManage),
                self::row('Verifikasi mutabaah', null, P::HabitsVerify),
                self::row('Setoran hafalan & PIB', P::QuranView, P::QuranRecord),
                self::row('Target, rubrik & daftar hafalan', null, P::QuranManage),
                self::row('Penilaian karakter', P::AttitudeView, P::AttitudeLock),
                self::row('Persetujuan penilaian karakter', null, P::AttitudeApprove),
            ]],
            ['label' => 'Akuntansi & Kas', 'rows' => [
                self::row('Pos akun, buku kas, pemasukan lain, BOS & ZIS', null, P::CashBookManage),
                self::row('Penggajian pegawai', null, P::PayrollManage),
            ]],
            ['label' => 'Keuangan Siswa', 'rows' => [
                self::row('Jenis & tarif biaya, diskon', null, P::FeeTypesManage),
                self::row('Tagihan', P::InvoicesView, P::InvoicesIssue),
                self::row('Penyesuaian & pembatalan tagihan', null, P::InvoicesAdjust),
                self::row('Kasir sekolah', null, P::PaymentsReceive),
                self::row('Pembalikan pembayaran', null, P::PaymentsReverse),
                self::row('Bukti transfer & pembayaran online', null, P::PaymentsReconcile),
            ]],
            ['label' => 'Kantin', 'rows' => [
                self::row('Kasir kantin', null, P::CanteenPos),
                self::row('Isi saldo tunai', null, P::WalletTopup),
                self::row('Saldo siswa & koreksi', null, P::WalletManage),
            ]],
            ['label' => 'Laporan', 'rows' => [
                self::row('Semua laporan & ekspor', P::ReportsView, null),
            ]],
            ['label' => 'Pengaturan', 'rows' => [
                self::row('Unit sekolah & jam absensi', null, P::UnitsManage),
                self::row('Identitas, tampilan & kunci fitur', null, P::SettingsManage),
                self::row('Log aktivitas', P::AuditView, null),
            ]],
        ];
    }

    /**
     * Izin "Lihat" yang wajib menyertai izin "Kelola" pada baris yang sama.
     *
     * @return array<string, string> manage => view
     */
    public static function impliedViews(): array
    {
        $map = [];

        foreach (self::groups() as $group) {
            foreach ($group['rows'] as $row) {
                if ($row['view'] !== null && $row['manage'] !== null) {
                    $map[$row['manage']->value] = $row['view']->value;
                }
            }
        }

        return $map;
    }

    /**
     * @return CatalogRow
     */
    private static function row(string $label, ?P $view, ?P $manage): array
    {
        return ['label' => $label, 'view' => $view, 'manage' => $manage];
    }
}
