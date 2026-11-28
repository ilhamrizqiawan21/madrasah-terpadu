import { Head, Link, router } from '@inertiajs/react';
import { Printer } from 'lucide-react';
import type { ReactNode } from 'react';
import { useEffect, useRef, useState } from 'react';
import GraduationController from '@/actions/App/Http/Controllers/Admin/GraduationController';
import { ConfirmDialog } from '@/components/app/confirm-dialog';
import type { DataTableColumn } from '@/components/app/data-table';
import { DataTable } from '@/components/app/data-table';
import { PageHeader } from '@/components/app/page-header';
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

type Option = { value: string; label: string };
type Row = {
    id: number;
    nis: string;
    name: string;
    classroom: string;
    average: number | null;
    status: 'passed' | 'failed' | null;
    letter_number: string | null;
};

type Props = {
    yearLabel: string | null;
    rows: PaginatedResource<Row>;
    filters: { search: string; unit: number | null; classroom: number | null };
    unitOptions: Option[];
    classroomOptions: Option[];
};

const ALL = 'semua';

export default function Graduations({
    yearLabel,
    rows,
    filters,
    unitOptions,
    classroomOptions,
}: Props) {
    const [search, setSearch] = useState(filters.search);
    const [loading, setLoading] = useState(false);
    const [target, setTarget] = useState<{
        row: Row;
        status: 'passed' | 'failed';
    } | null>(null);
    const [saving, setSaving] = useState(false);
    const timer = useRef<number | undefined>(undefined);

    useEffect(() => () => window.clearTimeout(timer.current), []);

    const visit = (
        changes: Record<string, string | number | null>,
        q = search,
    ) =>
        router.get(
            GraduationController.index.url(),
            Object.fromEntries(
                Object.entries({ ...filters, search: q, ...changes }).filter(
                    ([, v]) => v !== null && String(v) !== '',
                ),
            ),
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                onStart: () => setLoading(true),
                onFinish: () => setLoading(false),
            },
        );

    const confirm = () => {
        if (!target) {
            return;
        }
        router.put(
            GraduationController.decide.url(target.row.id),
            { status: target.status },
            {
                preserveScroll: true,
                onStart: () => setSaving(true),
                onFinish: () => setSaving(false),
                onSuccess: () => setTarget(null),
            },
        );
    };

    const columns: DataTableColumn<Row>[] = [
        {
            key: 'nis',
            label: 'NIS',
            className: 'tabular-nums whitespace-nowrap',
            render: (r) => r.nis,
        },
        {
            key: 'name',
            label: 'Nama siswa',
            render: (r) => <span className="font-medium">{r.name}</span>,
        },
        { key: 'classroom', label: 'Rombel', render: (r) => r.classroom },
        {
            key: 'average',
            label: 'Rata-rata',
            align: 'center',
            className: 'hidden tabular-nums @3xl:table-cell',
            render: (r) =>
                r.average === null
                    ? '—'
                    : r.average.toFixed(2).replace('.', ','),
        },
        {
            key: 'status',
            label: 'Status',
            render: (r) =>
                r.status === 'passed' ? (
                    <StatusBadge variant="success">Lulus</StatusBadge>
                ) : r.status === 'failed' ? (
                    <StatusBadge variant="destructive">Tidak Lulus</StatusBadge>
                ) : (
                    <StatusBadge variant="muted">Belum ditetapkan</StatusBadge>
                ),
        },
        {
            key: 'actions',
            label: 'Aksi',
            align: 'center',
            render: (r) => (
                <div className="flex flex-wrap justify-center gap-2">
                    {r.status === 'passed' && (
                        <Button asChild size="sm" variant="soft">
                            <Link href={GraduationController.letter.url(r.id)}>
                                <Printer
                                    className="size-4"
                                    strokeWidth={1.75}
                                />
                                Cetak SKL
                            </Link>
                        </Button>
                    )}
                    {r.status !== 'passed' && (
                        <Button
                            size="sm"
                            variant="outline"
                            onClick={() =>
                                setTarget({ row: r, status: 'passed' })
                            }
                        >
                            Tetapkan lulus
                        </Button>
                    )}
                    {r.status !== 'failed' && (
                        <Button
                            size="sm"
                            variant="outline"
                            className="text-destructive"
                            onClick={() =>
                                setTarget({ row: r, status: 'failed' })
                            }
                        >
                            Tidak lulus
                        </Button>
                    )}
                </div>
            ),
        },
    ];

    return (
        <StaffLayout
            breadcrumbs={[
                { title: 'Akademik', href: '#' },
                {
                    title: 'Kelulusan & SKL',
                    href: GraduationController.index.url(),
                },
            ]}
        >
            <Head title="Kelulusan & SKL" />

            <div className="@container space-y-4">
                <PageHeader
                    title="Kelulusan & SKL"
                    description={`Status kelulusan siswa tahun ajaran ${yearLabel ?? 'aktif'} dan cetak Surat Keterangan Lulus. Rata-rata diambil dari nilai ujian CBT tahun ini.`}
                />

                <div className="flex flex-wrap gap-2">
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
                        placeholder="Cari nama siswa atau NIS"
                        aria-label="Cari nama siswa atau NIS"
                        className="w-full @3xl:w-72"
                    />
                    <Select
                        value={
                            filters.unit === null ? ALL : String(filters.unit)
                        }
                        onValueChange={(v) =>
                            visit({
                                unit: v === ALL ? null : v,
                                classroom: null,
                                page: 1,
                            })
                        }
                    >
                        <SelectTrigger
                            className="w-full @lg:w-56"
                            aria-label="Saring unit"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL}>Semua unit</SelectItem>
                            {unitOptions.map((o) => (
                                <SelectItem key={o.value} value={o.value}>
                                    {o.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <Select
                        value={
                            filters.classroom === null
                                ? ALL
                                : String(filters.classroom)
                        }
                        onValueChange={(v) =>
                            visit({ classroom: v === ALL ? null : v, page: 1 })
                        }
                    >
                        <SelectTrigger
                            className="w-full @lg:w-48"
                            aria-label="Saring rombel"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL}>Semua rombel</SelectItem>
                            {classroomOptions.map((o) => (
                                <SelectItem key={o.value} value={o.value}>
                                    {o.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                <DataTable
                    columns={columns}
                    data={rows.data}
                    rowKey={(r) => r.id}
                    loading={loading}
                    pagination={{
                        currentPage: rows.meta.current_page,
                        lastPage: rows.meta.last_page,
                        perPage: rows.meta.per_page,
                        total: rows.meta.total,
                        from: rows.meta.from,
                        to: rows.meta.to,
                    }}
                    onPageChange={(page) => visit({ page })}
                    empty={{
                        title: 'Tidak ada siswa',
                        description:
                            'Belum ada siswa terdaftar di rombel tahun ajaran aktif untuk saringan ini.',
                    }}
                />
            </div>

            <ConfirmDialog
                open={target !== null}
                onOpenChange={(o) => !o && setTarget(null)}
                title={
                    target?.status === 'passed'
                        ? 'Tetapkan lulus?'
                        : 'Tetapkan tidak lulus?'
                }
                description={
                    target
                        ? `${target.row.name} (${target.row.classroom}) akan ditetapkan ${target.status === 'passed' ? 'LULUS dan mendapat nomor SKL' : 'TIDAK LULUS'}. Perubahan tercatat di log audit.`
                        : ''
                }
                confirmLabel={
                    target?.status === 'passed'
                        ? 'Ya, lulus'
                        : 'Ya, tidak lulus'
                }
                variant={
                    target?.status === 'failed' ? 'destructive' : 'default'
                }
                loading={saving}
                onConfirm={confirm}
            />
        </StaffLayout>
    );
}

Graduations.layout = (page: ReactNode) => page;
