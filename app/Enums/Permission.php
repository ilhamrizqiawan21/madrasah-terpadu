<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Application permissions per module.
 * Mapped to roles in RolesAndPermissionsSeeder according to docs/PRD.md §3.
 */
enum Permission: string
{
    case SettingsManage = 'settings.manage';
    case UsersManage = 'users.manage';
    case UnitsManage = 'units.manage';
    case AcademicYearsManage = 'academic_years.manage';
    case ClassroomsManage = 'classrooms.manage';
    case StudentsView = 'students.view';
    case StudentsManage = 'students.manage';
    case GuardiansManage = 'guardians.manage';

    case AttendanceView = 'attendance.view';
    case AttendanceScan = 'attendance.scan';
    case AttendanceManage = 'attendance.manage';

    case FeeTypesManage = 'fee_types.manage';
    case InvoicesView = 'invoices.view';
    case InvoicesIssue = 'invoices.issue';
    case InvoicesAdjust = 'invoices.adjust';
    case PaymentsReceive = 'payments.receive';
    case PaymentsReverse = 'payments.reverse';
    case PaymentsReconcile = 'payments.reconcile';

    case CanteenPos = 'canteen.pos';
    case WalletTopup = 'wallet.topup';
    case WalletManage = 'wallet.manage';

    case BehaviorView = 'behavior.view';
    case BehaviorRecord = 'behavior.record';
    case BehaviorVoid = 'behavior.void';
    case PointRulesManage = 'point_rules.manage';
    case ThresholdsManage = 'thresholds.manage';
    case RewardsApprove = 'rewards.approve';

    case HabitsManage = 'habits.manage';
    case HabitsVerify = 'habits.verify';

    case QuranView = 'quran.view';
    case QuranRecord = 'quran.record';
    case QuranManage = 'quran.manage';

    case AttitudeView = 'attitude.view';
    case AttitudeLock = 'attitude.lock';
    case AttitudeApprove = 'attitude.approve';

    case CbtView = 'cbt.view';
    case CbtTeach = 'cbt.teach';
    case CbtManage = 'cbt.manage';

    case ReportsView = 'reports.view';
    case AuditView = 'audit.view';

    public function label(): string
    {
        return match ($this) {
            self::SettingsManage => 'Kelola pengaturan',
            self::UsersManage => 'Kelola pengguna staf',
            self::UnitsManage => 'Kelola unit sekolah',
            self::AcademicYearsManage => 'Kelola tahun ajaran',
            self::ClassroomsManage => 'Kelola rombel',
            self::StudentsView => 'Lihat data siswa',
            self::StudentsManage => 'Kelola data siswa',
            self::GuardiansManage => 'Kelola data wali',
            self::AttendanceView => 'Lihat presensi',
            self::AttendanceScan => 'Scan presensi gerbang',
            self::AttendanceManage => 'Kelola presensi dan izin',
            self::FeeTypesManage => 'Kelola jenis tagihan',
            self::InvoicesView => 'Lihat tagihan',
            self::InvoicesIssue => 'Terbitkan tagihan',
            self::InvoicesAdjust => 'Sesuaikan atau batalkan tagihan',
            self::PaymentsReceive => 'Terima pembayaran kasir',
            self::PaymentsReverse => 'Balikkan pembayaran kasir',
            self::PaymentsReconcile => 'Rekonsiliasi pembayaran online',
            self::CanteenPos => 'Operasikan POS kantin',
            self::WalletTopup => 'Isi saldo tunai dompet',
            self::WalletManage => 'Kelola dompet dan koreksi kantin',
            self::BehaviorView => 'Lihat catatan perilaku',
            self::BehaviorRecord => 'Catat perilaku siswa',
            self::BehaviorVoid => 'Batalkan catatan perilaku',
            self::PointRulesManage => 'Kelola aturan poin',
            self::ThresholdsManage => 'Kelola ambang tindakan BK',
            self::RewardsApprove => 'Setujui penukaran reward',
            self::HabitsManage => 'Kelola item pembiasaan',
            self::HabitsVerify => 'Verifikasi pembiasaan siswa',
            self::QuranView => 'Lihat progres hafalan dan PIB',
            self::QuranRecord => 'Input setoran hafalan dan PIB',
            self::QuranManage => 'Kelola target dan rubrik hafalan',
            self::AttitudeView => 'Lihat nilai sikap',
            self::AttitudeLock => 'Koreksi dan kunci nilai sikap',
            self::AttitudeApprove => 'Setujui nilai sikap final',
            self::CbtView => 'Lihat mapel, penugasan, dan ujian CBT',
            self::CbtTeach => 'Kelola ujian dan soal pada penugasan sendiri',
            self::CbtManage => 'Kelola mapel, penugasan guru, dan semua ujian CBT',
            self::ReportsView => 'Lihat dan ekspor laporan',
            self::AuditView => 'Lihat jejak audit',
        };
    }
}
