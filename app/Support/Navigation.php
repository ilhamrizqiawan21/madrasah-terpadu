<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\Feature;
use App\Enums\Permission;
use App\Models\User;
use App\Services\Features\FeatureFlags;

/**
 * Katalog menu staf (ADR-028): satu-satunya sumber grup, ikon, dan item sidebar.
 *
 * Item jadi menunjuk rute nyata. Item `soon` belum dibangun: tampil berlabel "Segera"
 * dan membuka `admin.soon` (atau rute bernama lama yang kini merender halaman yang sama).
 * Menu tidak pernah menampilkan rute yang akan ditolak: item disaring izin, dan item
 * ber-`feature` disembunyikan saat modulnya dimatikan (T-211).
 *
 * @phpstan-type CatalogItem array{
 *     label: string,
 *     permission: string,
 *     route?: string,
 *     feature?: Feature,
 *     soon?: string,
 *     description?: string,
 *     plan?: string,
 * }
 * @phpstan-type CatalogGroup array{key: string, label: string, icon: string, items: list<CatalogItem>}
 */
final class Navigation
{
    /**
     * @return list<array{
     *     key: string,
     *     label: string,
     *     icon: string,
     *     items: list<array{label: string, route: string, href: string, permission: string, soon: bool}>
     * }>
     */
    public static function forUser(?User $user): array
    {
        if ($user === null) {
            return [];
        }

        $flags = app(FeatureFlags::class);
        $result = [];

        foreach (self::catalog() as $group) {
            $visibleItems = [];

            foreach ($group['items'] as $item) {
                if (! $user->can($item['permission'])) {
                    continue;
                }

                if (isset($item['feature']) && ! $flags->enabled($item['feature'])) {
                    continue;
                }

                $route = $item['route'] ?? 'admin.soon';

                $visibleItems[] = [
                    'label' => $item['label'],
                    'route' => $route,
                    'href' => isset($item['route'])
                        ? route($item['route'], absolute: false)
                        : route('admin.soon', ['module' => $item['soon'] ?? ''], absolute: false),
                    'permission' => $item['permission'],
                    'soon' => isset($item['soon']),
                ];
            }

            if ($visibleItems !== []) {
                $result[] = [
                    'key' => $group['key'],
                    'label' => $group['label'],
                    'icon' => $group['icon'],
                    'items' => $visibleItems,
                ];
            }
        }

        return $result;
    }

    /**
     * Data halaman "sedang disiapkan" untuk satu item `soon`, atau null bila tidak dikenal.
     *
     * @return array{label: string, group: string, permission: string, feature: ?Feature, description: string, plan: string}|null
     */
    public static function soonItem(string $slug): ?array
    {
        foreach (self::catalog() as $group) {
            foreach ($group['items'] as $item) {
                if (($item['soon'] ?? null) === $slug) {
                    return [
                        'label' => $item['label'],
                        'group' => $group['label'],
                        'permission' => $item['permission'],
                        'feature' => $item['feature'] ?? null,
                        'description' => $item['description'] ?? '',
                        'plan' => $item['plan'] ?? 'Belum dijadwalkan',
                    ];
                }
            }
        }

        return null;
    }

    /**
     * @return list<CatalogGroup>
     */
    private static function catalog(): array
    {
        return [
            [
                'key' => 'data-induk',
                'label' => 'Data Induk',
                'icon' => 'users',
                'items' => [
                    ['label' => 'Siswa', 'route' => 'admin.students.index', 'permission' => Permission::StudentsView->value],
                    ['label' => 'Wali', 'route' => 'admin.guardians.index', 'permission' => Permission::GuardiansManage->value],
                    ['label' => 'Rombel', 'route' => 'admin.classrooms.index', 'permission' => Permission::ClassroomsManage->value],
                    ['label' => 'Tahun ajaran', 'route' => 'admin.academic-years.index', 'permission' => Permission::AcademicYearsManage->value],
                    ['label' => 'Kenaikan kelas', 'route' => 'admin.students.promotions.index', 'permission' => Permission::StudentsManage->value],
                    ['label' => 'Mutasi siswa', 'route' => 'admin.students.mutations.index', 'permission' => Permission::StudentsManage->value],
                    [
                        'label' => 'Kelulusan & SKL', 'soon' => 'kelulusan', 'permission' => Permission::StudentsManage->value,
                        'description' => 'Menetapkan kelulusan siswa tingkat akhir dan mencetak Surat Keterangan Lulus. Status lulus saat ini sudah tercatat lewat Mutasi siswa.',
                        'plan' => 'Celah dari aplikasi lama, menunggu keputusan klien (U-13)',
                    ],
                    [
                        'label' => 'Penelusuran alumni', 'soon' => 'alumni', 'permission' => Permission::StudentsManage->value,
                        'description' => 'Formulir survei alumni dan rekap jenjang lanjutan atau karier lulusan.',
                        'plan' => 'Celah dari aplikasi lama, menunggu keputusan klien (U-13)',
                    ],
                ],
            ],
            [
                'key' => 'akademik',
                'label' => 'Akademik',
                'icon' => 'book-open',
                'items' => [
                    ['label' => 'Mapel & penugasan guru', 'route' => 'admin.cbt.subjects.index', 'permission' => Permission::CbtView->value, 'feature' => Feature::Cbt],
                    [
                        'label' => 'Jadwal pelajaran', 'soon' => 'jadwal-pelajaran', 'permission' => Permission::StudentsView->value,
                        'description' => 'Jam pelajaran per unit dan jadwal mingguan per rombel (mapel + guru), juga tampil di beranda wali.',
                        'plan' => 'T-1602, Paket P-21 Kerangka paritas',
                    ],
                    [
                        'label' => 'Tugas & PR', 'soon' => 'tugas', 'permission' => Permission::StudentsView->value,
                        'description' => 'Guru membuat tugas per mapel dan rombel dengan tenggat; wali melihat daftar tugas anak.',
                        'plan' => 'T-1603, Paket P-21 Kerangka paritas',
                    ],
                    [
                        'label' => 'Ekstrakurikuler', 'soon' => 'ekstrakurikuler', 'permission' => Permission::StudentsView->value,
                        'description' => 'Daftar ekstrakurikuler, anggota per kegiatan, dan nilai ekskul untuk rapor.',
                        'plan' => 'Celah dari aplikasi lama, menunggu keputusan klien (U-13)',
                    ],
                    [
                        'label' => 'Target capaian belajar', 'soon' => 'capaian-belajar', 'permission' => Permission::StudentsView->value,
                        'description' => 'Tujuan pembelajaran (TP) per mapel dan tingkat sebagai acuan penilaian.',
                        'plan' => 'Celah dari aplikasi lama, menunggu keputusan klien (U-13)',
                    ],
                    [
                        'label' => 'Supervisi modul ajar', 'soon' => 'supervisi', 'permission' => Permission::StudentsView->value,
                        'description' => 'Guru mengunggah modul ajar (RPP); kepala unit memberi catatan supervisi.',
                        'plan' => 'Celah dari aplikasi lama, menunggu keputusan klien (U-13)',
                    ],
                    [
                        'label' => 'E-rapor', 'soon' => 'e-rapor', 'permission' => Permission::StudentsView->value,
                        'description' => 'Wali kelas mengunggah rapor PDF per siswa per semester; setelah terbit, wali dapat mengunduhnya.',
                        'plan' => 'T-1604, Paket P-21 Kerangka paritas',
                    ],
                ],
            ],
            [
                'key' => 'presensi',
                'label' => 'Presensi',
                'icon' => 'calendar-check',
                'items' => [
                    ['label' => 'Rekap presensi', 'route' => 'admin.attendance.index', 'permission' => Permission::AttendanceView->value, 'feature' => Feature::Attendance],
                    ['label' => 'Pengajuan izin', 'route' => 'admin.attendance.leaves.index', 'permission' => Permission::AttendanceView->value, 'feature' => Feature::Attendance],
                    ['label' => 'Kalender sekolah', 'route' => 'admin.attendance.calendar.index', 'permission' => Permission::AttendanceView->value, 'feature' => Feature::Attendance],
                    [
                        'label' => 'Presensi per mapel', 'soon' => 'presensi-mapel', 'permission' => Permission::AttendanceView->value,
                        'description' => 'Guru mencatat kehadiran siswa per jam pelajaran, melengkapi presensi harian di gerbang.',
                        'plan' => 'Celah dari aplikasi lama, menunggu keputusan klien (U-13)',
                    ],
                    [
                        'label' => 'Presensi & izin pegawai', 'soon' => 'presensi-pegawai', 'permission' => Permission::UsersManage->value,
                        'description' => 'Rekap kehadiran guru dan staf (manual atau dari gerbang) serta pengajuan izin dan cuti pegawai.',
                        'plan' => 'T-1601, Paket P-21 Kerangka paritas',
                    ],
                ],
            ],
            [
                'key' => 'kesiswaan',
                'label' => 'Kesiswaan',
                'icon' => 'heart-handshake',
                'items' => [
                    ['label' => 'Catatan perilaku', 'route' => 'admin.behavior.index', 'permission' => Permission::BehaviorView->value, 'feature' => Feature::Behavior],
                    ['label' => 'Catat keterlambatan', 'route' => 'admin.behavior.late.index', 'permission' => Permission::BehaviorRecord->value, 'feature' => Feature::Behavior],
                    ['label' => 'Tindak lanjut & ambang', 'route' => 'admin.behavior.discipline.index', 'permission' => Permission::ThresholdsManage->value, 'feature' => Feature::Behavior],
                    ['label' => 'Aturan poin', 'route' => 'admin.behavior.rules.index', 'permission' => Permission::PointRulesManage->value, 'feature' => Feature::Behavior],
                    ['label' => 'Reward & voucher', 'route' => 'admin.behavior.rewards.index', 'permission' => Permission::RewardsApprove->value, 'feature' => Feature::Rewards],
                    [
                        'label' => 'Nilai sikap', 'soon' => 'nilai-sikap', 'permission' => Permission::AttitudeView->value,
                        'description' => 'Predikat sikap spiritual dan sosial per semester dari rekap pembiasaan dan poin perilaku, dikunci wali kelas.',
                        'plan' => 'Fase 12, Paket P-18 Nilai sikap',
                    ],
                    [
                        'label' => 'Asrama', 'soon' => 'asrama', 'permission' => Permission::StudentsManage->value,
                        'description' => 'Kamar asrama (kapasitas, jenis kelamin) dan penempatan siswa.',
                        'plan' => 'T-1606, Paket P-21 Kerangka paritas',
                    ],
                ],
            ],
            [
                'key' => 'ibadah',
                'label' => 'Ibadah',
                'icon' => 'moon-star',
                'items' => [
                    ['label' => 'Verifikasi pembiasaan', 'route' => 'admin.habits.verify.index', 'permission' => Permission::HabitsVerify->value, 'feature' => Feature::Habits],
                    ['label' => 'Rekap pembiasaan', 'route' => 'admin.habits.recap.index', 'permission' => Permission::HabitsVerify->value, 'feature' => Feature::Habits],
                    ['label' => 'Item pembiasaan', 'route' => 'admin.habits.items.index', 'permission' => Permission::HabitsManage->value, 'feature' => Feature::Habits],
                    ['label' => 'Materi hadits & doa', 'route' => 'admin.quran.materials.index', 'permission' => Permission::QuranManage->value, 'feature' => Feature::Quran],
                    ['label' => 'Target hafalan', 'route' => 'admin.quran.targets.index', 'permission' => Permission::QuranManage->value, 'feature' => Feature::Quran],
                    [
                        'label' => 'Setoran hafalan', 'route' => 'admin.quran.index', 'soon' => 'setoran-hafalan',
                        'permission' => Permission::QuranView->value, 'feature' => Feature::Quran,
                        'description' => "Guru mencatat setoran ziyadah dan murāja'ah per siswa dengan rubrik penilaian; progres tampil ke wali.",
                        'plan' => 'T-1104–T-1105, Paket P-14 Hafalan & PIB',
                    ],
                    [
                        'label' => 'Target hafalan', 'soon' => 'target-hafalan', 'permission' => Permission::QuranManage->value,
                        'description' => 'Target hafalan Al-Qur\'an per unit, tingkat, dan tahun ajaran.',
                        'plan' => 'T-1102, Paket P-14 Hafalan & PIB',
                    ],
                    [
                        'label' => 'Materi hadits & doa', 'soon' => 'materi-hafalan', 'permission' => Permission::QuranManage->value,
                        'description' => 'Daftar hadits dan doa hafalan beserta teks Arab, terjemahan, dan sumber.',
                        'plan' => 'T-1107, Paket P-14 Hafalan & PIB',
                    ],
                    [
                        'label' => 'Praktik ibadah (PIB)', 'soon' => 'praktik-ibadah', 'permission' => Permission::QuranRecord->value,
                        'description' => 'Penilaian praktik ibadah per bab dan materi untuk setiap rombel.',
                        'plan' => 'T-1106, Paket P-14 Hafalan & PIB',
                    ],
                ],
            ],
            [
                'key' => 'ujian',
                'label' => 'Ujian',
                'icon' => 'clipboard-check',
                'items' => [
                    ['label' => 'Daftar ujian', 'route' => 'admin.cbt.exams.index', 'permission' => Permission::CbtView->value, 'feature' => Feature::Cbt],
                    ['label' => 'PIN ujian siswa', 'route' => 'admin.cbt.pins.index', 'permission' => Permission::CbtView->value, 'feature' => Feature::Cbt],
                ],
            ],
            [
                'key' => 'keuangan',
                'label' => 'Keuangan',
                'icon' => 'wallet',
                'items' => [
                    [
                        'label' => 'Tagihan', 'route' => 'admin.invoices.index', 'soon' => 'tagihan',
                        'permission' => Permission::InvoicesView->value, 'feature' => Feature::Billing,
                        'description' => 'Penerbitan tagihan per rombel, cicilan, dan status lunas per siswa.',
                        'plan' => 'Paket P-05 Tagihan & kasir, rilis peralihan uang (ADR-021)',
                    ],
                    [
                        'label' => 'Jenis tagihan & tarif', 'soon' => 'jenis-tagihan', 'permission' => Permission::FeeTypesManage->value, 'feature' => Feature::Billing,
                        'description' => 'Master SPP dan biaya lain beserta tarif per unit dan tingkat.',
                        'plan' => 'Paket P-05 Tagihan & kasir',
                    ],
                    [
                        'label' => 'Diskon & beasiswa', 'soon' => 'diskon', 'permission' => Permission::FeeTypesManage->value, 'feature' => Feature::Billing,
                        'description' => 'Potongan berulang per siswa (persen atau nominal) yang diterapkan otomatis saat tagihan terbit.',
                        'plan' => 'Fase 6, U-12 (b)',
                    ],
                    [
                        'label' => 'Kasir pembayaran', 'soon' => 'kasir', 'permission' => Permission::PaymentsReceive->value, 'feature' => Feature::Billing,
                        'description' => 'Penerimaan pembayaran tunai di loket beserta kuitansi.',
                        'plan' => 'Paket P-05 Tagihan & kasir',
                    ],
                    [
                        'label' => 'Bukti transfer', 'soon' => 'bukti-transfer', 'permission' => Permission::PaymentsReconcile->value, 'feature' => Feature::Billing,
                        'description' => 'Wali mengunggah bukti transfer; bendahara memverifikasi dan melunasi tagihan.',
                        'plan' => 'Fase 6, U-12 (c)',
                    ],
                    [
                        'label' => 'Pembayaran online', 'soon' => 'pembayaran-online', 'permission' => Permission::PaymentsReconcile->value, 'feature' => Feature::Midtrans,
                        'description' => 'Pembayaran Midtrans dari wali dan antrean rekonsiliasi.',
                        'plan' => 'Paket P-08 Midtrans wali',
                    ],
                    [
                        'label' => 'Kelayakan ujian', 'soon' => 'kelayakan-ujian', 'permission' => Permission::InvoicesView->value, 'feature' => Feature::ExamEligibility,
                        'description' => 'Syarat lunas per periode ujian, dispensasi, dan daftar siswa yang belum layak.',
                        'plan' => 'Paket P-15 Kelayakan ujian',
                    ],
                ],
            ],
            [
                'key' => 'kantin',
                'label' => 'Kantin',
                'icon' => 'store',
                'items' => [
                    [
                        'label' => 'Saldo & isi saldo', 'soon' => 'saldo-kantin', 'permission' => Permission::WalletManage->value, 'feature' => Feature::Wallet,
                        'description' => 'Saldo dompet kantin siswa, isi saldo di loket, dan batas jajan harian.',
                        'plan' => 'Paket P-10 Dompet kantin',
                    ],
                    [
                        'label' => 'Menu & produk', 'soon' => 'menu-kantin', 'permission' => Permission::WalletManage->value, 'feature' => Feature::Wallet,
                        'description' => 'Kategori dan daftar menu kantin bila kasir memilih produk, bukan mengetik nominal.',
                        'plan' => 'Celah dari aplikasi lama, menunggu keputusan klien (U-13)',
                    ],
                ],
            ],
            [
                'key' => 'sarana',
                'label' => 'Sarana',
                'icon' => 'package',
                'items' => [
                    [
                        'label' => 'Inventaris aset', 'soon' => 'inventaris', 'permission' => Permission::SettingsManage->value,
                        'description' => 'Daftar aset dan sarana per unit beserta kondisi dan lokasinya.',
                        'plan' => 'Celah dari aplikasi lama, menunggu keputusan klien (U-13)',
                    ],
                    [
                        'label' => 'Ruangan & peminjaman', 'soon' => 'ruangan', 'permission' => Permission::SettingsManage->value,
                        'description' => 'Gedung, ruang kelas, dan laboratorium beserta jadwal peminjamannya.',
                        'plan' => 'Celah dari aplikasi lama, menunggu keputusan klien (U-13)',
                    ],
                ],
            ],
            [
                'key' => 'administrasi',
                'label' => 'Administrasi',
                'icon' => 'inbox',
                'items' => [
                    [
                        'label' => 'Buku kas', 'soon' => 'buku-kas', 'permission' => Permission::ReportsView->value, 'feature' => Feature::CashBook,
                        'description' => 'Kas masuk dan keluar per unit dengan saldo berjalan.',
                        'plan' => 'T-1605, Paket P-21 Kerangka paritas',
                    ],
                    [
                        'label' => 'Undangan rapat', 'soon' => 'rapat', 'permission' => Permission::UsersManage->value,
                        'description' => 'Undangan rapat guru dan staf beserta notulen.',
                        'plan' => 'Celah dari aplikasi lama, menunggu keputusan klien (U-13)',
                    ],
                    [
                        'label' => 'Buku tamu', 'soon' => 'buku-tamu', 'permission' => Permission::AttendanceManage->value,
                        'description' => 'Pencatatan tamu sekolah: nama, keperluan, dan waktu kunjungan.',
                        'plan' => 'Celah dari aplikasi lama, menunggu keputusan klien (U-13)',
                    ],
                ],
            ],
            [
                'key' => 'laporan',
                'label' => 'Laporan',
                'icon' => 'chart-column',
                'items' => [
                    [
                        'label' => 'Laporan presensi', 'route' => 'admin.reports.index', 'soon' => 'laporan-presensi', 'permission' => Permission::ReportsView->value,
                        'description' => 'Rekap presensi harian dan bulanan per rombel dan per siswa, siap cetak dan ekspor.',
                        'plan' => 'T-1301, Paket P-19 Laporan non-keuangan',
                    ],
                    [
                        'label' => 'Laporan BK', 'soon' => 'laporan-bk', 'permission' => Permission::ReportsView->value,
                        'description' => 'Catatan perilaku per periode, siswa dengan poin tertinggi, tindak lanjut, dan reward.',
                        'plan' => 'T-1303, Paket P-19 Laporan non-keuangan',
                    ],
                    [
                        'label' => 'Laporan pembiasaan', 'soon' => 'laporan-pembiasaan', 'permission' => Permission::ReportsView->value,
                        'description' => 'Keterlaksanaan pembiasaan per item dan rombel.',
                        'plan' => 'T-1306, Paket P-19 Laporan non-keuangan',
                    ],
                    [
                        'label' => 'Laporan hafalan & PIB', 'soon' => 'laporan-hafalan', 'permission' => Permission::ReportsView->value,
                        'description' => 'Progres hafalan per kelompok dan rombel serta setoran per guru.',
                        'plan' => 'T-1305, Paket P-19 Laporan non-keuangan',
                    ],
                    [
                        'label' => 'Leger hasil belajar', 'soon' => 'leger', 'permission' => Permission::ReportsView->value,
                        'description' => 'Leger nilai per rombel dan semester.',
                        'plan' => 'Celah dari aplikasi lama, bergantung e-rapor (U-13)',
                    ],
                    [
                        'label' => 'Laporan keuangan', 'soon' => 'laporan-keuangan', 'permission' => Permission::InvoicesView->value, 'feature' => Feature::Billing,
                        'description' => 'Piutang per jenis dan rombel, penerimaan per metode dan periode, serta kartu tagihan siswa.',
                        'plan' => 'T-1302, rilis peralihan uang',
                    ],
                    [
                        'label' => 'Jejak audit', 'soon' => 'jejak-audit', 'permission' => Permission::AuditView->value,
                        'description' => 'Riwayat perubahan data penting: siapa, kapan, dan nilai sebelum/sesudah.',
                        'plan' => 'T-1307, Fase 13 Laporan',
                    ],
                ],
            ],
            [
                'key' => 'pengaturan',
                'label' => 'Pengaturan',
                'icon' => 'settings',
                'items' => [
                    ['label' => 'Identitas sekolah', 'route' => 'admin.settings.identity.edit', 'permission' => Permission::SettingsManage->value],
                    ['label' => 'Tampilan', 'route' => 'admin.settings.appearance.edit', 'permission' => Permission::SettingsManage->value],
                    ['label' => 'Unit & jam presensi', 'route' => 'admin.settings.units.index', 'permission' => Permission::UnitsManage->value],
                    ['label' => 'Kunci fitur', 'route' => 'admin.settings.features.edit', 'permission' => Permission::SettingsManage->value],
                    ['label' => 'Pengguna staf', 'route' => 'admin.users.index', 'permission' => Permission::UsersManage->value],
                    [
                        'label' => 'Impor guru & staf', 'soon' => 'impor-staf', 'permission' => Permission::UsersManage->value,
                        'description' => 'Membuat akun guru dan staf sekaligus dari berkas Excel.',
                        'plan' => 'Celah dari aplikasi lama, menunggu keputusan klien (U-13)',
                    ],
                    [
                        'label' => 'Cadangan data', 'soon' => 'cadangan', 'permission' => Permission::SettingsManage->value,
                        'description' => 'Cadangan basis data terjadwal dan status cadangan terakhir.',
                        'plan' => 'T-1402, Paket P-09a Staging',
                    ],
                ],
            ],
        ];
    }
}
