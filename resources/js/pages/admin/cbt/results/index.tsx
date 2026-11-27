import { Head, router } from '@inertiajs/react';
import { FileSpreadsheet } from 'lucide-react';
import CbtExamController from '@/actions/App/Http/Controllers/Admin/CbtExamController';
import CbtResultController from '@/actions/App/Http/Controllers/Admin/CbtResultController';
import AdminDashboardController from '@/actions/App/Http/Controllers/Admin/DashboardController';
import type { DataTableColumn } from '@/components/app/data-table';
import { DataTable } from '@/components/app/data-table';
import { PageHeader } from '@/components/app/page-header';
import { StatusBadge } from '@/components/app/status-badge';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import StaffLayout from '@/layouts/staff-layout';

type ResultRow = {
    session_id: number;
    nis: string;
    name: string;
    classroom: string;
    status_label: string;
    auto_points: number;
    essay_points: number | null;
    max_points: number;
    score: number | null;
};

type ClassroomOption = { id: number; name: string };

type Props = {
    exam: { id: number; title: string; subject: string };
    filters: { classroom_id: number | null };
    classroomOptions: ClassroomOption[];
    results: ResultRow[];
};

export default function CbtResultsIndex({
    exam,
    filters,
    classroomOptions,
    results,
}: Props) {
    const changeClassroom = (value: string): void => {
        const params = value === 'all' ? {} : { classroom_id: Number(value) };

        router.get(CbtResultController.index.url(exam.id), params, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const exportHref = CbtResultController.export.url(exam.id, {
        query:
            filters.classroom_id !== null
                ? { classroom_id: filters.classroom_id }
                : undefined,
    });

    const columns: DataTableColumn<ResultRow>[] = [
        {
            key: 'nis',
            label: 'NIS',
            render: (row) => (
                <span className="font-mono text-xs tabular-nums">
                    {row.nis}
                </span>
            ),
        },
        {
            key: 'name',
            label: 'Nama siswa',
            render: (row) => <span className="font-medium">{row.name}</span>,
        },
        {
            key: 'classroom',
            label: 'Rombel',
            render: (row) => row.classroom,
        },
        {
            key: 'status',
            label: 'Status',
            align: 'center',
            render: (row) => (
                <StatusBadge
                    variant={row.score !== null ? 'success' : 'warning'}
                >
                    {row.status_label}
                </StatusBadge>
            ),
        },
        {
            key: 'auto_points',
            label: 'Poin otomatis',
            align: 'right',
            render: (row) => (
                <span className="tabular-nums">{row.auto_points}</span>
            ),
        },
        {
            key: 'essay_points',
            label: 'Poin essay',
            align: 'right',
            render: (row) => (
                <span className="tabular-nums">
                    {row.essay_points !== null ? row.essay_points : '—'}
                </span>
            ),
        },
        {
            key: 'score',
            label: 'Nilai akhir',
            align: 'right',
            render: (row) => (
                <span className="font-semibold tabular-nums">
                    {row.score !== null ? row.score : '—'}
                </span>
            ),
        },
    ];

    return (
        <StaffLayout
            breadcrumbs={[
                { title: 'Beranda', href: AdminDashboardController.url() },
                { title: 'Daftar ujian', href: CbtExamController.index.url() },
                {
                    title: 'Hasil ujian',
                    href: CbtResultController.index.url(exam.id),
                },
            ]}
        >
            <Head title={`Hasil — ${exam.title}`} />

            <div className="space-y-6">
                <PageHeader
                    title={`Hasil ujian — ${exam.title}`}
                    description={`${exam.subject} · ${results.length} peserta`}
                    actions={
                        <Tooltip>
                            <TooltipTrigger asChild>
                                <Button
                                    variant="outline"
                                    size="icon"
                                    asChild
                                    aria-label="Ekspor Excel hasil ujian"
                                >
                                    <a href={exportHref}>
                                        <FileSpreadsheet
                                            className="size-4 text-success"
                                            strokeWidth={1.75}
                                        />
                                    </a>
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent>Ekspor Excel</TooltipContent>
                        </Tooltip>
                    }
                />

                <div className="flex items-center gap-3">
                    <Select
                        value={
                            filters.classroom_id !== null
                                ? String(filters.classroom_id)
                                : 'all'
                        }
                        onValueChange={changeClassroom}
                    >
                        <SelectTrigger
                            className="w-full sm:w-52"
                            aria-label="Filter rombel"
                        >
                            <SelectValue placeholder="Semua rombel" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Semua rombel</SelectItem>
                            {classroomOptions.map((opt) => (
                                <SelectItem key={opt.id} value={String(opt.id)}>
                                    {opt.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                <DataTable
                    columns={columns}
                    data={results}
                    rowKey={(row) => row.session_id}
                    empty={{
                        title: 'Belum ada hasil ujian',
                        description:
                            'Hasil akan muncul setelah siswa menyelesaikan ujian atau sesi ditutup.',
                    }}
                />
            </div>
        </StaffLayout>
    );
}
