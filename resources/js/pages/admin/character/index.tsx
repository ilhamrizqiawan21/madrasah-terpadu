import { Head, Link } from '@inertiajs/react';
import CharacterAssessmentController from '@/actions/App/Http/Controllers/Admin/CharacterAssessmentController';
import type { DataTableColumn } from '@/components/app/data-table';
import { DataTable } from '@/components/app/data-table';
import { DateText } from '@/components/app/date-text';
import { EmptyState } from '@/components/app/empty-state';
import { PageHeader } from '@/components/app/page-header';
import type { StatusBadgeVariant } from '@/components/app/status-badge';
import { StatusBadge } from '@/components/app/status-badge';
import { Button } from '@/components/ui/button';
import StaffLayout from '@/layouts/staff-layout';
import { dashboard } from '@/routes/admin';

type ClassroomRow = {
    id: number;
    name: string;
    homeroom: string | null;
    students_count: number;
    status: 'draft' | 'submitted' | 'approved' | null;
    status_label: string;
    computed_at: string | null;
};

type Props = {
    academicYear: string | null;
    configured: boolean;
    classrooms: ClassroomRow[];
};

const statusVariant: Record<
    NonNullable<ClassroomRow['status']>,
    StatusBadgeVariant
> = {
    draft: 'muted',
    submitted: 'attention',
    approved: 'success',
};

export default function CharacterIndex({
    academicYear,
    configured,
    classrooms,
}: Props) {
    const columns: DataTableColumn<ClassroomRow>[] = [
        {
            key: 'name',
            label: 'Rombel',
            render: (row) => (
                <div>
                    <div className="font-medium">{row.name}</div>
                    <div className="text-xs text-muted-foreground">
                        {row.homeroom ?? 'Belum ada wali kelas'}
                    </div>
                </div>
            ),
        },
        {
            key: 'students',
            label: 'Siswa',
            align: 'right',
            className: 'tabular-nums',
            render: (row) => row.students_count,
        },
        {
            key: 'status',
            label: 'Status',
            align: 'center',
            render: (row) => (
                <StatusBadge
                    variant={row.status ? statusVariant[row.status] : 'muted'}
                >
                    {row.status_label}
                </StatusBadge>
            ),
        },
        {
            key: 'computed',
            label: 'Terakhir dihitung',
            align: 'center',
            render: (row) => (
                <DateText value={row.computed_at} mode="datetime" />
            ),
        },
        {
            key: 'actions',
            label: 'Aksi',
            align: 'center',
            render: (row) => (
                <Button size="sm" variant="outline" asChild>
                    <Link href={CharacterAssessmentController.show(row.id)}>
                        Buka
                    </Link>
                </Button>
            ),
        },
    ];

    return (
        <StaffLayout
            breadcrumbs={[
                { title: 'Kesiswaan', href: dashboard() },
                {
                    title: 'Penilaian karakter',
                    href: CharacterAssessmentController.index(),
                },
            ]}
        >
            <Head title="Penilaian karakter" />

            <div className="space-y-6">
                <PageHeader
                    title="Penilaian karakter"
                    description={`Capaian karakter per rombel${academicYear ? ` · ${academicYear}` : ''}. Wali kelas menghitung dan mengajukan, kepala unit menyetujui.`}
                />

                {!configured ? (
                    <EmptyState
                        title="Penilaian karakter unit ini belum diatur"
                        description="Admin perlu memilih kurikulum dan dimensi di Pengaturan › Penilaian karakter sebelum wali kelas bisa menghitung."
                    />
                ) : (
                    <DataTable
                        columns={columns}
                        data={classrooms}
                        rowKey={(row) => row.id}
                        empty={{
                            title: 'Belum ada rombel untuk dinilai',
                            description:
                                'Anda hanya melihat rombel yang Anda walikan pada unit dan tahun ajaran terpilih.',
                        }}
                    />
                )}
            </div>
        </StaffLayout>
    );
}
