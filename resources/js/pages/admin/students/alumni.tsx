import { Head, Link, router } from '@inertiajs/react';
import { ClipboardPlus, Printer } from 'lucide-react';
import type { ReactNode } from 'react';
import { useRef, useState } from 'react';
import AlumniTracerController from '@/actions/App/Http/Controllers/Admin/AlumniTracerController';
import type { DataTableColumn } from '@/components/app/data-table';
import { DataTable } from '@/components/app/data-table';
import { PageHeader } from '@/components/app/page-header';
import { RowActions } from '@/components/app/row-actions';
import { StatusBadge } from '@/components/app/status-badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import StaffLayout from '@/layouts/staff-layout';
import type { PaginatedResource } from '@/types';

type Row = {
    id: number;
    student_name: string;
    student_nis: string;
    unit_code: string;
    graduation_year: number;
    activity: string;
    activity_label: string;
    institution: string | null;
    program: string | null;
    income_label: string | null;
    relevance_label: string;
    feedback: string | null;
};

type Props = {
    responses: PaginatedResource<Row>;
    summary: { value: string; label: string; total: number }[];
    filters: { activity: string | null; search: string };
};

const ALL = 'semua';

export default function AlumniIndex({ responses, summary, filters }: Props) {
    const [search, setSearch] = useState(filters.search);
    const [loading, setLoading] = useState(false);
    const [deletingId, setDeletingId] = useState<number | null>(null);
    const timer = useRef<number | undefined>(undefined);

    const visit = (
        changes: Record<string, string | number | null>,
        q = search,
    ) => {
        const params = Object.fromEntries(
            Object.entries({ ...filters, search: q, ...changes }).filter(
                ([, v]) => v !== '' && v !== null && v !== undefined,
            ),
        );
        router.get(AlumniTracerController.index.url(), params, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onStart: () => setLoading(true),
            onFinish: () => setLoading(false),
        });
    };

    const columns: DataTableColumn<Row>[] = [
        {
            key: 'name',
            label: 'Nama alumni & NIS',
            render: (r) => (
                <div className="min-w-40">
                    <div className="font-medium">{r.student_name}</div>
                    <div className="text-xs text-muted-foreground tabular-nums">
                        {r.student_nis} · {r.unit_code} · angkatan{' '}
                        {r.graduation_year}
                    </div>
                </div>
            ),
        },
        {
            key: 'activity',
            label: 'Status karier',
            render: (r) => (
                <StatusBadge variant="default">{r.activity_label}</StatusBadge>
            ),
        },
        {
            key: 'institution',
            label: 'Kampus / tempat kerja',
            className: 'hidden @3xl:table-cell',
            render: (r) => (
                <div className="text-sm">
                    <div>{r.institution ?? '—'}</div>
                    <div className="text-xs text-muted-foreground">
                        {r.program ?? ''}
                    </div>
                </div>
            ),
        },
        {
            key: 'relevance',
            label: 'Kesesuaian kurikulum',
            className: 'hidden @4xl:table-cell',
            render: (r) => r.relevance_label,
        },
        {
            key: 'feedback',
            label: 'Masukan & saran',
            className: 'hidden @5xl:table-cell',
            render: (r) => (
                <span className="line-clamp-2 max-w-64 text-sm text-muted-foreground">
                    {r.feedback ?? '—'}
                </span>
            ),
        },
        {
            key: 'actions',
            label: 'Aksi',
            align: 'center',
            render: (r) => (
                <RowActions
                    onDelete={() =>
                        router.delete(
                            AlumniTracerController.destroy.url(r.id),
                            {
                                preserveScroll: true,
                                onStart: () => setDeletingId(r.id),
                                onFinish: () => setDeletingId(null),
                            },
                        )
                    }
                    deleting={deletingId === r.id}
                    itemName={r.student_name}
                    deleteTitle="Hapus jawaban alumni?"
                    deleteDescription={`Jawaban kuesioner ${r.student_name} dihapus.`}
                />
            ),
        },
    ];

    return (
        <StaffLayout
            breadcrumbs={[
                { title: 'Pengguna', href: '#' },
                {
                    title: 'Data Alumni',
                    href: AlumniTracerController.index.url(),
                },
            ]}
        >
            <Head title="Data Alumni" />

            <div className="@container space-y-4">
                <PageHeader
                    title="Data Alumni"
                    description="Rekap penelusuran karier dan studi lanjut lulusan (tracer study)."
                    actions={
                        <>
                            <Button
                                type="button"
                                variant="soft"
                                onClick={() => window.print()}
                            >
                                <Printer
                                    className="size-4"
                                    strokeWidth={1.75}
                                />
                                Cetak laporan
                            </Button>
                            <Button asChild>
                                <Link
                                    href={AlumniTracerController.create.url()}
                                >
                                    <ClipboardPlus
                                        className="size-4"
                                        strokeWidth={1.75}
                                    />
                                    Isi kuesioner
                                </Link>
                            </Button>
                        </>
                    }
                />

                <dl className="grid grid-cols-2 gap-3 @3xl:grid-cols-4">
                    {summary.map((s) => (
                        <div
                            key={s.value}
                            className="rounded-lg border border-border bg-card p-3 shadow-xs"
                        >
                            <dt className="text-xs text-muted-foreground">
                                {s.label}
                            </dt>
                            <dd className="text-xl font-semibold tabular-nums">
                                {s.total}
                            </dd>
                        </div>
                    ))}
                </dl>

                <div className="flex flex-wrap gap-2 print:hidden">
                    <Input
                        type="search"
                        value={search}
                        onChange={(e) => {
                            const v = e.target.value;
                            setSearch(v);
                            window.clearTimeout(timer.current);
                            timer.current = window.setTimeout(
                                () => visit({ page: 1 }, v),
                                300,
                            );
                        }}
                        placeholder="Cari nama, NIS, atau institusi"
                        aria-label="Cari alumni"
                        className="w-full @3xl:w-72"
                    />
                    <Select
                        value={filters.activity ?? ALL}
                        onValueChange={(v) =>
                            visit({ activity: v === ALL ? null : v, page: 1 })
                        }
                    >
                        <SelectTrigger
                            className="w-full @lg:w-52"
                            aria-label="Saring status karier"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL}>
                                Semua status karier
                            </SelectItem>
                            {summary.map((s) => (
                                <SelectItem key={s.value} value={s.value}>
                                    {s.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                <DataTable
                    columns={columns}
                    data={responses.data}
                    rowKey={(r) => r.id}
                    loading={loading}
                    pagination={{
                        currentPage: responses.meta.current_page,
                        lastPage: responses.meta.last_page,
                        perPage: responses.meta.per_page,
                        total: responses.meta.total,
                        from: responses.meta.from,
                        to: responses.meta.to,
                    }}
                    onPageChange={(page) => visit({ page })}
                    empty={{
                        title: 'Belum ada data alumni',
                        description:
                            'Isi kuesioner untuk alumni yang sudah lulus agar rekap kariernya tercatat.',
                    }}
                />
            </div>
        </StaffLayout>
    );
}

AlumniIndex.layout = (page: ReactNode) => page;
