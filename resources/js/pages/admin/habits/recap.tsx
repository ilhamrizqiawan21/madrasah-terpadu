import { Head, router } from '@inertiajs/react';
import type { ReactNode } from 'react';
import HabitRecapController from '@/actions/App/Http/Controllers/Admin/HabitRecapController';
import type { DataTableColumn } from '@/components/app/data-table';
import { DataTable } from '@/components/app/data-table';
import { PageHeader } from '@/components/app/page-header';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import StaffLayout from '@/layouts/staff-layout';

type RecapRow = {
    student_id: number;
    student_name: string;
    student_nis: string;
    spiritual_verified: number;
    spiritual_expected: number;
    spiritual_pct: number;
    social_verified: number;
    social_expected: number;
    social_pct: number;
};

type ClassroomOption = { id: number; label: string };

type Props = {
    classrooms: ClassroomOption[];
    selectedClassroomId: number | null;
    period: 'month' | 'semester';
    month: string;
    meta: {
        effective_days: number;
        spiritual_items: number;
        social_items: number;
    };
    rows: RecapRow[];
};

export default function HabitRecapIndex({
    classrooms,
    selectedClassroomId,
    period,
    month,
    meta,
    rows,
}: Props) {
    function visitFilters(next: {
        classroom_id?: number;
        period?: 'month' | 'semester';
        month?: string;
    }) {
        router.get(
            HabitRecapController.index.url({
                query: {
                    classroom_id:
                        next.classroom_id ?? selectedClassroomId ?? undefined,
                    period: next.period ?? period,
                    month: next.month ?? month,
                },
            }),
            {},
            { preserveState: false, replace: true },
        );
    }

    const columns: DataTableColumn<RecapRow>[] = [
        {
            key: 'student',
            label: 'Siswa',
            render: (row) => (
                <div>
                    <div className="font-medium text-foreground">
                        {row.student_name}
                    </div>
                    <div className="text-xs text-muted-foreground">
                        NIS {row.student_nis}
                    </div>
                </div>
            ),
        },
        {
            key: 'spiritual_count',
            label: 'Spiritual (terverifikasi)',
            align: 'right',
            render: (row) => (
                <span className="text-muted-foreground tabular-nums">
                    {row.spiritual_verified} / {row.spiritual_expected}
                </span>
            ),
        },
        {
            key: 'spiritual_pct',
            label: 'Spiritual %',
            align: 'right',
            render: (row) => (
                <span className="font-medium text-foreground tabular-nums">
                    {row.spiritual_pct.toFixed(1)}%
                </span>
            ),
        },
        {
            key: 'social_count',
            label: 'Sosial (terverifikasi)',
            align: 'right',
            render: (row) => (
                <span className="text-muted-foreground tabular-nums">
                    {row.social_verified} / {row.social_expected}
                </span>
            ),
        },
        {
            key: 'social_pct',
            label: 'Sosial %',
            align: 'right',
            render: (row) => (
                <span className="font-medium text-foreground tabular-nums">
                    {row.social_pct.toFixed(1)}%
                </span>
            ),
        },
    ];

    return (
        <>
            <Head title="Rekap pembiasaan" />
            <div className="space-y-6">
                <PageHeader
                    title="Rekap pembiasaan"
                    description="Persentase mutabaah terverifikasi per siswa dan aspek."
                />

                <div className="flex flex-wrap items-center gap-3">
                    <Select
                        value={
                            selectedClassroomId
                                ? String(selectedClassroomId)
                                : ''
                        }
                        onValueChange={(v) =>
                            visitFilters({ classroom_id: Number(v) })
                        }
                    >
                        <SelectTrigger
                            className="w-[220px]"
                            aria-label="Pilih rombel"
                        >
                            <SelectValue placeholder="Pilih rombel" />
                        </SelectTrigger>
                        <SelectContent>
                            {classrooms.map((c) => (
                                <SelectItem key={c.id} value={String(c.id)}>
                                    {c.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    <Select
                        value={period}
                        onValueChange={(v) =>
                            visitFilters({ period: v as 'month' | 'semester' })
                        }
                    >
                        <SelectTrigger
                            className="w-[150px]"
                            aria-label="Periode rekap"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="month">Bulanan</SelectItem>
                            <SelectItem value="semester">
                                Semester / TA
                            </SelectItem>
                        </SelectContent>
                    </Select>

                    {period === 'month' ? (
                        <Input
                            type="month"
                            value={month}
                            onChange={(e) =>
                                visitFilters({ month: e.target.value })
                            }
                            className="w-[170px]"
                            aria-label="Bulan rekap"
                        />
                    ) : null}

                    <span className="text-xs text-muted-foreground">
                        Hari efektif: {meta.effective_days} hari · Item
                        spiritual: {meta.spiritual_items} · Item sosial:{' '}
                        {meta.social_items}
                    </span>
                </div>

                <DataTable
                    columns={columns}
                    data={rows}
                    rowKey={(row) => row.student_id}
                    empty={{
                        title: 'Belum ada data rekap',
                        description:
                            'Pilih rombel yang memiliki siswa aktif untuk melihat rekap pembiasaan.',
                    }}
                />
            </div>
        </>
    );
}

HabitRecapIndex.layout = (page: ReactNode) => (
    <StaffLayout
        breadcrumbs={[
            { title: 'Ibadah', href: '#' },
            {
                title: 'Rekap pembiasaan',
                href: HabitRecapController.index.url(),
            },
        ]}
    >
        {page}
    </StaffLayout>
);
