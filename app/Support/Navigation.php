<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\Feature;
use App\Enums\Permission;
use App\Models\User;
use App\Services\Features\FeatureFlags;

final class Navigation
{
    /**
     * Bangun grup menu navigasi staf dari izin pengguna.
     * Menu tidak pernah menampilkan rute yang akan ditolak, termasuk modul yang
     * dimatikan lewat kunci fitur (T-211) karena rutenya 404.
     *
     * @return list<array{
     *     key: string,
     *     label: string,
     *     items: list<array{label: string, route: string, href: string, permission: string}>
     * }>
     */
    public static function forUser(?User $user): array
    {
        if ($user === null) {
            return [];
        }

        $groups = [
            [
                'key' => 'data-induk',
                'label' => 'Data Induk',
                'items' => [
                    [
                        'label' => 'Siswa',
                        'route' => 'admin.students.index',
                        'permission' => Permission::StudentsView->value,
                    ],
                    [
                        'label' => 'Kenaikan kelas',
                        'route' => 'admin.students.promotions.index',
                        'permission' => Permission::StudentsManage->value,
                    ],
                    [
                        'label' => 'Mutasi siswa',
                        'route' => 'admin.students.mutations.index',
                        'permission' => Permission::StudentsManage->value,
                    ],
                    [
                        'label' => 'Wali',
                        'route' => 'admin.guardians.index',
                        'permission' => Permission::GuardiansManage->value,
                    ],
                    [
                        'label' => 'Tahun ajaran',
                        'route' => 'admin.academic-years.index',
                        'permission' => Permission::AcademicYearsManage->value,
                    ],
                    [
                        'label' => 'Rombel',
                        'route' => 'admin.classrooms.index',
                        'permission' => Permission::ClassroomsManage->value,
                    ],
                ],
            ],
            [
                'key' => 'presensi',
                'label' => 'Presensi',
                'items' => [
                    [
                        'label' => 'Rekap presensi',
                        'route' => 'admin.attendance.index',
                        'permission' => Permission::AttendanceView->value,
                        'feature' => Feature::Attendance,
                    ],
                    [
                        'label' => 'Pengajuan izin',
                        'route' => 'admin.attendance.leaves.index',
                        'permission' => Permission::AttendanceView->value,
                        'feature' => Feature::Attendance,
                    ],
                    [
                        'label' => 'Kalender sekolah',
                        'route' => 'admin.attendance.calendar.index',
                        'permission' => Permission::AttendanceView->value,
                        'feature' => Feature::Attendance,
                    ],
                ],
            ],
            [
                'key' => 'keuangan',
                'label' => 'Keuangan',
                'items' => [
                    [
                        'label' => 'Tagihan',
                        'route' => 'admin.invoices.index',
                        'permission' => Permission::InvoicesView->value,
                        'feature' => Feature::Billing,
                    ],
                ],
            ],
            [
                'key' => 'kesiswaan',
                'label' => 'Kesiswaan',
                'items' => [
                    [
                        'label' => 'Catatan perilaku',
                        'route' => 'admin.behavior.index',
                        'permission' => Permission::BehaviorView->value,
                        'feature' => Feature::Behavior,
                    ],
                    [
                        'label' => 'Dari keterlambatan',
                        'route' => 'admin.behavior.late.index',
                        'permission' => Permission::BehaviorRecord->value,
                        'feature' => Feature::Behavior,
                    ],
                    [
                        'label' => 'Aturan poin',
                        'route' => 'admin.behavior.rules.index',
                        'permission' => Permission::PointRulesManage->value,
                        'feature' => Feature::Behavior,
                    ],
                    [
                        'label' => 'Tindak lanjut & ambang',
                        'route' => 'admin.behavior.discipline.index',
                        'permission' => Permission::BehaviorView->value,
                        'feature' => Feature::Behavior,
                    ],
                    [
                        'label' => 'Reward & voucher',
                        'route' => 'admin.behavior.rewards.index',
                        'permission' => Permission::BehaviorView->value,
                        'feature' => Feature::Rewards,
                    ],
                ],
            ],
            [
                'key' => 'ibadah',
                'label' => 'Ibadah',
                'items' => [
                    [
                        'label' => 'Setoran hafalan',
                        'route' => 'admin.quran.index',
                        'permission' => Permission::QuranView->value,
                        'feature' => Feature::Quran,
                    ],
                ],
            ],
            [
                'key' => 'ujian',
                'label' => 'Ujian',
                'items' => [
                    [
                        'label' => 'Daftar ujian',
                        'route' => 'admin.cbt.exams.index',
                        'permission' => Permission::CbtView->value,
                        'feature' => Feature::Cbt,
                    ],
                    [
                        'label' => 'Mapel & penugasan',
                        'route' => 'admin.cbt.subjects.index',
                        'permission' => Permission::CbtView->value,
                        'feature' => Feature::Cbt,
                    ],
                    [
                        'label' => 'PIN ujian siswa',
                        'route' => 'admin.cbt.pins.index',
                        'permission' => Permission::CbtView->value,
                        'feature' => Feature::Cbt,
                    ],
                ],
            ],
            [
                'key' => 'laporan',
                'label' => 'Laporan',
                'items' => [
                    [
                        'label' => 'Laporan',
                        'route' => 'admin.reports.index',
                        'permission' => Permission::ReportsView->value,
                    ],
                ],
            ],
            [
                'key' => 'pengaturan',
                'label' => 'Pengaturan',
                'items' => [
                    [
                        'label' => 'Identitas sekolah',
                        'route' => 'admin.settings.identity.edit',
                        'permission' => Permission::SettingsManage->value,
                    ],
                    [
                        'label' => 'Tampilan',
                        'route' => 'admin.settings.appearance.edit',
                        'permission' => Permission::SettingsManage->value,
                    ],
                    [
                        'label' => 'Unit & jam presensi',
                        'route' => 'admin.settings.units.index',
                        'permission' => Permission::UnitsManage->value,
                    ],
                    [
                        'label' => 'Kunci fitur',
                        'route' => 'admin.settings.features.edit',
                        'permission' => Permission::SettingsManage->value,
                    ],
                    [
                        'label' => 'Pengguna staf',
                        'route' => 'admin.users.index',
                        'permission' => Permission::UsersManage->value,
                    ],
                ],
            ],
        ];

        $flags = app(FeatureFlags::class);
        $result = [];

        foreach ($groups as $group) {
            $visibleItems = [];

            foreach ($group['items'] as $item) {
                if (! $user->can($item['permission'])) {
                    continue;
                }

                if (isset($item['feature']) && ! $flags->enabled($item['feature'])) {
                    continue;
                }

                $visibleItems[] = [
                    'label' => $item['label'],
                    'route' => $item['route'],
                    'href' => route($item['route'], absolute: false),
                    'permission' => $item['permission'],
                ];
            }

            if ($visibleItems !== []) {
                $result[] = [
                    'key' => $group['key'],
                    'label' => $group['label'],
                    'items' => $visibleItems,
                ];
            }
        }

        return $result;
    }
}
