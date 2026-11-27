<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\Permission;
use App\Models\User;

final class Navigation
{
    /**
     * Bangun grup menu navigasi staf dari izin pengguna.
     * Menu tidak pernah menampilkan rute yang akan ditolak.
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
                        'label' => 'Pengaturan',
                        'route' => 'admin.settings.index',
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

        $result = [];

        foreach ($groups as $group) {
            $visibleItems = [];

            foreach ($group['items'] as $item) {
                if (! $user->can($item['permission'])) {
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
