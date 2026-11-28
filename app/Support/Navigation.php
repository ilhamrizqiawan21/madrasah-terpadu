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
 *     query?: array<string, string>,
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
                        ? route($item['route'], $item['query'] ?? [], absolute: false)
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
     * Susunan dan urutan grup mengikuti sidebar admin aplikasi lama (docs/legacy/admin-sidebar.md,
     * arahan pemilik 2026-11-28). Nama menu disederhanakan ke bahasa sehari-hari (arahan pemilik); halaman yang sudah dibangun dipasang di menu padanannya; halaman
     * kita yang tidak ada padanannya diletakkan di akhir grup terdekat agar tetap bisa dibuka.
     *
     * @return list<CatalogGroup>
     */
    private static function catalog(): array
    {
        $staff = Permission::StudentsView->value;
        $settings = Permission::SettingsManage->value;
        $finance = Permission::InvoicesView->value;
        $reports = Permission::ReportsView->value;

        return [
            [
                'key' => 'ppdb',
                'label' => 'Penerimaan Siswa',
                'icon' => 'user-plus',
                'items' => [
                    self::soon('Pengaturan PPDB', 'ppdb-pengaturan', Permission::StudentsManage->value, 'Gelombang, kuota, biaya formulir, dan persyaratan pendaftaran siswa baru.'),
                    self::soon('Data Pendaftar', 'ppdb-pendaftar', Permission::StudentsManage->value, 'Daftar calon siswa beserta berkas dan status pendaftarannya.'),
                    self::soon('Hasil Seleksi', 'ppdb-seleksi', Permission::StudentsManage->value, 'Nilai seleksi calon siswa dan penetapan diterima atau tidak.'),
                    self::soon('Pembayaran PPDB', 'ppdb-kasir', Permission::StudentsManage->value, 'Penerimaan biaya formulir dan daftar ulang calon siswa.'),
                ],
            ],
            [
                'key' => 'pengguna',
                'label' => 'Pengguna',
                'icon' => 'users',
                'items' => [
                    ['label' => 'Peran & Hak Akses', 'route' => 'admin.roles.index', 'permission' => Permission::UsersManage->value],
                    ['label' => 'Guru & Staf', 'route' => 'admin.users.index', 'permission' => Permission::UsersManage->value],
                    self::soon('Undangan Rapat', 'rapat', Permission::UsersManage->value, 'Undangan rapat guru dan staf beserta notulen.'),
                    ['label' => 'Siswa', 'route' => 'admin.students.index', 'permission' => $staff],
                    ['label' => 'Orang Tua / Wali', 'route' => 'admin.guardians.index', 'permission' => Permission::GuardiansManage->value],
                    ['label' => 'Mutasi Siswa', 'route' => 'admin.students.mutations.index', 'permission' => Permission::StudentsManage->value],
                    self::soon('Data Alumni', 'alumni', Permission::StudentsManage->value, 'Rekap jenjang lanjutan atau karier lulusan dari survei alumni.'),
                    self::soon('Survei Alumni', 'survei-alumni', Permission::StudentsManage->value, 'Formulir survei yang diisi alumni.'),
                ],
            ],
            [
                'key' => 'akademik',
                'label' => 'Akademik',
                'icon' => 'graduation-cap',
                'items' => [
                    ['label' => 'Tahun Ajaran', 'route' => 'admin.academic-years.index', 'permission' => Permission::AcademicYearsManage->value],
                    self::soon('Gedung', 'gedung', $settings, 'Gedung sekolah per unit.'),
                    self::soon('Ruangan & Lab', 'ruangan', $settings, 'Ruang kelas dan laboratorium per gedung.'),
                    self::soon('Tingkat Kelas', 'tingkat-kelas', Permission::ClassroomsManage->value, 'Tingkat kelas per unit (mis. Kelas 1–6).'),
                    ['label' => 'Rombel', 'route' => 'admin.classrooms.index', 'permission' => Permission::ClassroomsManage->value],
                    self::soon('Jurusan', 'jurusan', Permission::ClassroomsManage->value, 'Jurusan atau peminatan.'),
                    self::soon('Jam Pelajaran', 'jam-pelajaran', $staff, 'Jam pelajaran per unit.'),
                    self::soon('Wali Kelas', 'wali-kelas', Permission::ClassroomsManage->value, 'Penetapan wali kelas per rombel per tahun ajaran. Saat ini diatur di Rombel.'),
                    self::soon('Ekstrakurikuler', 'ekstrakurikuler', $staff, 'Daftar kegiatan ekstrakurikuler.'),
                    ['label' => 'Mata Pelajaran', 'route' => 'admin.cbt.subjects.index', 'permission' => Permission::CbtView->value, 'feature' => Feature::Cbt],
                    self::soon('Tujuan Pembelajaran', 'capaian-belajar', $staff, 'Tujuan pembelajaran per mapel dan tingkat.'),
                    self::soon('Jadwal Pelajaran', 'jadwal-pelajaran', $staff, 'Jadwal mingguan per rombel (mapel + guru).', 'T-1602, Paket P-21 Kerangka paritas'),
                    ['label' => 'Kenaikan Kelas', 'route' => 'admin.students.promotions.index', 'permission' => Permission::StudentsManage->value],
                    self::soon('Kelulusan & SKL', 'kelulusan', Permission::StudentsManage->value, 'Kelulusan siswa tingkat akhir dan cetak Surat Keterangan Lulus.'),
                    self::soon('Supervisi Modul Ajar', 'supervisi', $staff, 'Unggah modul ajar dan catatan supervisi kepala unit.'),
                    self::soon('Anggota & Nilai Ekskul', 'nilai-ekskul', $staff, 'Anggota per kegiatan ekstrakurikuler dan nilainya untuk rapor.'),
                    self::soon('Peminjaman Ruangan', 'pinjam-ruangan', $staff, 'Reservasi ruangan dan laboratorium.'),
                ],
            ],
            [
                'key' => 'e-learning',
                'label' => 'E-Learning',
                'icon' => 'monitor-play',
                'items' => [
                    self::soon('Tugas & PR', 'tugas', $staff, 'Tugas per mapel dan rombel dengan tenggat; wali melihat daftar tugas anak.', 'T-1603, Paket P-21 Kerangka paritas'),
                    self::soon('Bank Soal', 'bank-soal', Permission::CbtView->value, 'Kumpulan soal per mapel yang dapat dipakai ulang. Saat ini soal dibuat per ujian di Jadwal Ujian.', null, Feature::Cbt),
                    ['label' => 'Jadwal Ujian', 'route' => 'admin.cbt.exams.index', 'permission' => Permission::CbtView->value, 'feature' => Feature::Cbt],
                    self::soon('Pantau Ujian', 'pantau-ujian', Permission::CbtView->value, 'Pemantauan peserta ujian yang sedang berlangsung. Saat ini dibuka dari detail ujian.', null, Feature::Cbt),
                    self::soon('Hasil Ujian', 'hasil-ujian', Permission::CbtView->value, 'Rekap nilai ujian lintas ujian. Saat ini dibuka dari detail ujian.', null, Feature::Cbt),
                    self::soon('E-Rapor', 'e-rapor', $staff, 'Unggah rapor PDF per siswa per semester.', 'T-1604, Paket P-21 Kerangka paritas'),
                    ['label' => 'PIN Ujian Siswa', 'route' => 'admin.cbt.pins.index', 'permission' => Permission::CbtView->value, 'feature' => Feature::Cbt],
                ],
            ],
            [
                'key' => 'absensi',
                'label' => 'Absensi',
                'icon' => 'calendar-check',
                'items' => [
                    self::soon('Jam Absensi', 'pengaturan-absen', Permission::UnitsManage->value, 'Jam masuk, terlambat, alpa, dan pulang. Saat ini diatur per unit di Pengaturan > Unit Sekolah.'),
                    self::soon('Denda & Potongan', 'denda-potongan', Permission::UsersManage->value, 'Aturan potongan karena keterlambatan atau ketidakhadiran pegawai.'),
                    ['label' => 'Izin Siswa', 'route' => 'admin.attendance.leaves.index', 'permission' => Permission::AttendanceView->value, 'feature' => Feature::Attendance],
                    self::soon('Izin & Cuti Pegawai', 'izin-pegawai', Permission::UsersManage->value, 'Pengajuan izin dan cuti pegawai.', 'T-1601, Paket P-21 Kerangka paritas'),
                    ['label' => 'Kehadiran Siswa', 'route' => 'admin.attendance.index', 'permission' => Permission::AttendanceView->value, 'feature' => Feature::Attendance],
                    self::soon('Mesin Sidik Jari', 'log-biometrik', Permission::AttendanceManage->value, 'Log mesin absensi biometrik.'),
                    ['label' => 'Scan Kartu', 'route' => 'gate.index', 'permission' => Permission::AttendanceScan->value, 'feature' => Feature::Attendance],
                    self::soon('Absensi per Mapel', 'presensi-mapel', Permission::AttendanceView->value, 'Kehadiran siswa per jam pelajaran.'),
                    self::soon('Absensi Pegawai', 'presensi-pegawai', Permission::UsersManage->value, 'Rekap kehadiran guru dan staf.', 'T-1601, Paket P-21 Kerangka paritas'),
                    self::soon('Buku Tamu', 'buku-tamu', Permission::AttendanceManage->value, 'Pencatatan tamu sekolah: nama, keperluan, dan waktu kunjungan.'),
                    self::soon('Kartu RFID', 'aktivasi-rfid', Permission::StudentsManage->value, 'Pendaftaran UID kartu RFID siswa. Saat ini dari profil siswa.'),
                    self::soon('Cetak Kartu Siswa', 'cetak-kartu', Permission::StudentsManage->value, 'Cetak kartu siswa per rombel. Saat ini dari Rombel dan profil siswa.'),
                    ['label' => 'Kalender Sekolah', 'route' => 'admin.attendance.calendar.index', 'permission' => Permission::AttendanceView->value, 'feature' => Feature::Attendance],
                ],
            ],
            [
                'key' => 'kesiswaan',
                'label' => 'Kesiswaan & Asrama',
                'icon' => 'heart-handshake',
                'items' => [
                    ['label' => 'Tata Tertib & Poin', 'route' => 'admin.behavior.rules.index', 'permission' => Permission::PointRulesManage->value, 'feature' => Feature::Behavior],
                    ['label' => 'Buku Saku BK', 'route' => 'admin.behavior.index', 'permission' => Permission::BehaviorView->value, 'feature' => Feature::Behavior],
                    ['label' => 'Item Mutabaah', 'route' => 'admin.habits.items.index', 'permission' => Permission::HabitsManage->value, 'feature' => Feature::Habits],
                    self::soon('Daftar Surat', 'master-surat', Permission::QuranManage->value, 'Daftar surat dan ayat untuk setoran tahfidz.', null, Feature::Quran),
                    ['label' => 'Daftar Hadits', 'route' => 'admin.quran.materials.index', 'query' => ['kind' => 'hadith'], 'permission' => Permission::QuranManage->value, 'feature' => Feature::Quran],
                    ['label' => 'Daftar Doa', 'route' => 'admin.quran.materials.index', 'query' => ['kind' => 'prayer'], 'permission' => Permission::QuranManage->value, 'feature' => Feature::Quran],
                    ['label' => 'Setoran Tahfidz', 'route' => 'admin.quran.index', 'permission' => Permission::QuranView->value, 'feature' => Feature::Quran],
                    self::soon('Hafalan Hadits', 'hafalan-hadits', Permission::QuranView->value, 'Setoran hafalan hadits. Saat ini dicatat di Setoran Tahfidz.', null, Feature::Quran),
                    self::soon('Hafalan Doa', 'hafalan-doa', Permission::QuranView->value, 'Setoran hafalan doa. Saat ini dicatat di Setoran Tahfidz.', null, Feature::Quran),
                    ['label' => 'Mutabaah Harian', 'route' => 'admin.habits.verify.index', 'permission' => Permission::HabitsVerify->value, 'feature' => Feature::Habits],
                    self::soon('Kamar Asrama', 'asrama', Permission::StudentsManage->value, 'Kamar asrama (kapasitas, jenis kelamin) dan penempatan siswa.', 'T-1606, Paket P-21 Kerangka paritas'),
                    self::soon('Izin Pulang Asrama', 'boarding-pass', Permission::StudentsManage->value, 'Perizinan pulang santri asrama.'),
                    ['label' => 'Keterlambatan', 'route' => 'admin.behavior.late.index', 'permission' => Permission::BehaviorRecord->value, 'feature' => Feature::Behavior],
                    ['label' => 'Tindak Lanjut BK', 'route' => 'admin.behavior.discipline.index', 'permission' => Permission::ThresholdsManage->value, 'feature' => Feature::Behavior],
                    ['label' => 'Reward & Voucher', 'route' => 'admin.behavior.rewards.index', 'permission' => Permission::RewardsApprove->value, 'feature' => Feature::Rewards],
                    ['label' => 'Penilaian Karakter', 'route' => 'admin.character.index', 'permission' => Permission::AttitudeView->value, 'feature' => Feature::Attitude],
                    ['label' => 'Rekap Mutabaah', 'route' => 'admin.habits.recap.index', 'permission' => Permission::HabitsVerify->value, 'feature' => Feature::Habits],
                    ['label' => 'Target Hafalan', 'route' => 'admin.quran.targets.index', 'permission' => Permission::QuranManage->value, 'feature' => Feature::Quran],
                    ['label' => 'Rubrik Hafalan', 'route' => 'admin.quran.rubrics.index', 'permission' => Permission::QuranManage->value, 'feature' => Feature::Quran],
                    ['label' => 'Praktik Ibadah (PIB)', 'route' => 'admin.quran.pib.index', 'permission' => Permission::QuranView->value, 'feature' => Feature::Quran],
                ],
            ],
            [
                'key' => 'keuangan',
                'label' => 'Keuangan Siswa',
                'icon' => 'wallet',
                'items' => [
                    ['label' => 'Jenis & Tarif Biaya', 'route' => 'admin.fee-types.index', 'permission' => Permission::FeeTypesManage->value, 'feature' => Feature::Billing],
                    ['label' => 'Diskon & Beasiswa', 'route' => 'admin.discounts.index', 'permission' => Permission::FeeTypesManage->value, 'feature' => Feature::Billing],
                    self::soon('Kartu SPP', 'kartu-spp', $finance, 'Kartu tagihan dan riwayat pembayaran per siswa. Saat ini dibuka dari Tagihan.', null, Feature::Billing),
                    ['label' => 'Tagihan', 'route' => 'admin.invoices.index', 'permission' => $finance, 'feature' => Feature::Billing],
                    ['label' => 'Kasir Sekolah', 'route' => 'admin.cashier.index', 'permission' => Permission::PaymentsReceive->value, 'feature' => Feature::Billing],
                    ['label' => 'Bukti Transfer', 'route' => 'admin.transfer-proofs.index', 'permission' => Permission::PaymentsReconcile->value, 'feature' => Feature::Billing],
                    ['label' => 'Pembayaran Online', 'route' => 'admin.online-payments.index', 'permission' => Permission::PaymentsReconcile->value, 'feature' => Feature::Midtrans],
                    ['label' => 'Terbitkan Tagihan', 'route' => 'admin.billing-runs.create', 'permission' => Permission::InvoicesIssue->value, 'feature' => Feature::Billing],
                    ['label' => 'Saldo Awal', 'route' => 'admin.opening-balances.index', 'permission' => Permission::InvoicesIssue->value, 'feature' => Feature::Billing],
                    ['label' => 'Syarat Ujian', 'route' => 'admin.exam-periods.index', 'permission' => $finance, 'feature' => Feature::ExamEligibility],
                ],
            ],
            [
                'key' => 'akuntansi',
                'label' => 'Akuntansi & Kas',
                'icon' => 'landmark',
                'items' => [
                    self::soon('Kategori Akun', 'coa', $finance, 'Daftar akun pemasukan dan pengeluaran.'),
                    self::soon('Pemasukan Lain', 'pemasukan-eksternal', $finance, 'Pemasukan di luar pembayaran siswa (sewa, bantuan, dan lainnya).'),
                    self::soon('Penggajian', 'payroll', $finance, 'Penggajian guru dan staf.'),
                    self::soon('Zakat & Infak (ZIS)', 'zis', $finance, 'Penerimaan dan penyaluran zakat, infak, dan sedekah.'),
                    self::soon('Buku Kas Umum', 'buku-kas', $finance, 'Kas masuk dan keluar per unit dengan saldo berjalan.', 'T-1605, Paket P-21 Kerangka paritas', Feature::CashBook),
                    self::soon('Dana BOS Masuk', 'bos-termin', $finance, 'Penerimaan dana BOS per termin.'),
                    self::soon('RKAS BOS', 'bos-rkas', $finance, 'Rencana kegiatan dan anggaran dana BOS.'),
                    self::soon('BKU BOS', 'bos-bku', $finance, 'Buku kas umum dana BOS.'),
                    self::soon('Alokasi Dana', 'alokasi-kas', $finance, 'Pemetaan sumber dana ke pos pengeluaran.'),
                ],
            ],
            [
                'key' => 'kantin',
                'label' => 'Kantin',
                'icon' => 'store',
                'items' => [
                    ['label' => 'Saldo Siswa', 'route' => 'admin.wallets.index', 'permission' => Permission::WalletManage->value, 'feature' => Feature::Wallet],
                    self::soon('Riwayat Isi Saldo', 'riwayat-isi-saldo', Permission::WalletManage->value, 'Riwayat isi saldo tunai dan online seluruh siswa.', null, Feature::Wallet),
                    self::soon('Kategori Menu', 'kategori-produk', Permission::WalletManage->value, 'Kategori menu kantin.', null, Feature::Wallet),
                    self::soon('Daftar Menu', 'menu-kantin', Permission::WalletManage->value, 'Daftar menu dan harga kantin.', null, Feature::Wallet),
                    ['label' => 'Kasir Kantin', 'route' => 'canteen.index', 'permission' => Permission::CanteenPos->value, 'feature' => Feature::Wallet],
                ],
            ],
            [
                'key' => 'fasilitas',
                'label' => 'Fasilitas',
                'icon' => 'package',
                'items' => [
                    self::soon('Produk Koperasi', 'koperasi-produk', $settings, 'Produk koperasi dan seragam.'),
                    self::soon('Penjualan Koperasi', 'koperasi-penjualan', $settings, 'Pencatatan penjualan koperasi sekolah.'),
                    self::soon('Inventaris', 'inventaris', $settings, 'Daftar aset dan sarana per unit beserta kondisi dan lokasinya.'),
                    self::soon('Kategori Buku', 'kategori-buku', $settings, 'Kategori koleksi perpustakaan.'),
                    self::soon('Katalog Buku', 'katalog-buku', $settings, 'Katalog koleksi perpustakaan.'),
                    self::soon('Peminjaman Buku', 'peminjaman-buku', $settings, 'Sirkulasi peminjaman dan pengembalian buku.'),
                    self::soon('Kendaraan Antar-Jemput', 'armada', $settings, 'Kendaraan antar-jemput siswa.'),
                    self::soon('Rute Antar-Jemput', 'rute-bus', $settings, 'Rute antar-jemput dan siswa peserta.'),
                ],
            ],
            [
                'key' => 'helpdesk',
                'label' => 'Pengaduan',
                'icon' => 'life-buoy',
                'items' => [
                    self::soon('Buat Pengaduan', 'pengaduan-baru', $staff, 'Membuat tiket pengaduan atau permintaan bantuan.'),
                    self::soon('Tanggapi Pengaduan', 'respon-pengaduan', $settings, 'Menanggapi dan menutup tiket pengaduan.'),
                ],
            ],
            [
                'key' => 'website',
                'label' => 'Website Sekolah',
                'icon' => 'globe',
                'items' => [
                    self::soon('Menu Website', 'cms-menu', $settings, 'Menu navigasi situs publik sekolah.'),
                    self::soon('Halaman', 'cms-halaman', $settings, 'Halaman statis situs publik.'),
                    self::soon('Agenda', 'cms-agenda', $settings, 'Agenda kegiatan sekolah di situs publik.'),
                    self::soon('Berita & Artikel', 'cms-berita', $settings, 'Berita dan artikel situs publik.'),
                    self::soon('Galeri', 'cms-galeri', $settings, 'Galeri foto dan video kegiatan.'),
                    self::soon('Unduhan', 'cms-unduhan', $settings, 'Berkas yang dapat diunduh pengunjung situs.'),
                    self::soon('SEO', 'cms-seo', $settings, 'Judul, deskripsi, dan metadata situs publik.'),
                ],
            ],
            [
                'key' => 'laporan',
                'label' => 'Laporan',
                'icon' => 'chart-column',
                'items' => [
                    self::soon('Proyeksi Pemasukan', 'potensi-pemasukan', $finance, 'Proyeksi pemasukan dari tagihan yang terbit.', null, Feature::Billing),
                    self::soon('Tunggakan', 'laporan-keuangan', $finance, 'Piutang per jenis tagihan dan rombel beserta tunggakan teratas.', 'T-1302, rilis peralihan uang', Feature::Billing),
                    self::soon('Kas BOS', 'laporan-bos', $finance, 'Laporan buku kas dana BOS.'),
                    self::soon('Arus Kas', 'neraca-cashflow', $finance, 'Arus kas masuk dan keluar.'),
                    self::soon('Kas ZIS', 'laporan-zis', $finance, 'Mutasi kas zakat, infak, dan sedekah.'),
                    self::soon('Rekap Penggajian', 'laporan-payroll', $finance, 'Rekap penggajian dan kinerja pegawai.'),
                    ['label' => 'Transaksi Kantin', 'route' => 'admin.reports.canteen.index', 'permission' => $reports, 'feature' => Feature::Wallet],
                    self::soon('Penjualan Koperasi & Seragam', 'laporan-koperasi', $reports, 'Rekap penjualan koperasi dan seragam.'),
                    self::soon('Leger Nilai', 'leger', $reports, 'Leger nilai per rombel dan semester.'),
                    ['label' => 'Absensi Siswa', 'route' => 'admin.reports.index', 'permission' => $reports, 'feature' => Feature::Attendance],
                    self::soon('Rekap Absensi Pegawai', 'laporan-presensi-pegawai', $reports, 'Rekap kehadiran guru dan staf.'),
                    self::soon('Izin & Sakit Siswa', 'laporan-izin', $reports, 'Rekap surat izin dan sakit siswa.', null, Feature::Attendance),
                    ['label' => 'Mutabaah Ibadah', 'route' => 'admin.reports.habits.index', 'permission' => $reports, 'feature' => Feature::Habits],
                    ['label' => 'Poin BK', 'route' => 'admin.reports.behavior.index', 'permission' => $reports, 'feature' => Feature::Behavior],
                    self::soon('Hunian Asrama', 'laporan-asrama', $reports, 'Okupansi kamar asrama.'),
                    self::soon('Perpustakaan', 'laporan-perpustakaan', $reports, 'Rekap peminjaman dan denda perpustakaan.'),
                    self::soon('Pendaftar PPDB', 'laporan-ppdb', $reports, 'Rekap pendaftar PPDB dan konversinya menjadi siswa.'),
                    ['label' => 'Hafalan & PIB', 'route' => 'admin.reports.quran.index', 'permission' => $reports, 'feature' => Feature::Quran],
                ],
            ],
            [
                'key' => 'pengaturan',
                'label' => 'Pengaturan',
                'icon' => 'settings',
                'items' => [
                    ['label' => 'Unit Sekolah', 'route' => 'admin.settings.units.index', 'permission' => Permission::UnitsManage->value],
                    ['label' => 'Identitas Sekolah', 'route' => 'admin.settings.identity.edit', 'permission' => $settings],
                    self::soon('Midtrans', 'payment-gateway', $settings, 'Status konfigurasi Midtrans (mode sandbox/produksi). Kunci tetap diatur di server.'),
                    self::soon('WhatsApp', 'whatsapp-api', $settings, 'Koneksi gateway WhatsApp untuk notifikasi cadangan.'),
                    ['label' => 'Kunci Fitur', 'route' => 'admin.settings.features.edit', 'permission' => $settings],
                    ['label' => 'Log Aktivitas', 'route' => 'admin.reports.audit.index', 'permission' => Permission::AuditView->value],
                    self::soon('Cadangan Data', 'cadangan', $settings, 'Cadangan basis data terjadwal dan status cadangan terakhir.', 'T-1402, Paket P-09a Staging'),
                    self::soon('Impor Data', 'impor-data', $settings, 'Impor data induk dari berkas Excel. Impor siswa saat ini dari halaman Siswa.'),
                    ['label' => 'Tampilan', 'route' => 'admin.settings.appearance.edit', 'permission' => $settings],
                    ['label' => 'Penilaian Karakter', 'route' => 'admin.settings.character.edit', 'permission' => $settings, 'feature' => Feature::Attitude],
                ],
            ],
        ];
    }

    /**
     * Item menu yang modulnya belum dibangun (label "Segera").
     *
     * @return CatalogItem
     */
    private static function soon(
        string $label,
        string $slug,
        string $permission,
        string $description,
        ?string $plan = null,
        ?Feature $feature = null,
    ): array {
        $item = [
            'label' => $label,
            'soon' => $slug,
            'permission' => $permission,
            'description' => $description,
            'plan' => $plan ?? 'Duplikasi alur aplikasi lama, menunggu arahan pemilik',
        ];

        if ($feature !== null) {
            $item['feature'] = $feature;
        }

        return $item;
    }
}
