import { Head, router } from '@inertiajs/react';
import type { ReactNode } from 'react';
import HabitReportController from '@/actions/App/Http/Controllers/Admin/HabitReportController';
import { EmptyState } from '@/components/app/empty-state';
import { ExportButtons } from '@/components/app/export-buttons';
import { PageHeader } from '@/components/app/page-header';
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

type ReportRow = Record<string, string | number>;

type Props = {
    filters: {
        mode: string;
        unit_id: number | null;
        classroom_id: number | null;
        date_from: string;
        date_to: string;
    };
    summary: {
        total_logs: number;
        verified_logs: number;
        active_items: number;
        active_students: number;
    };
    rows: {
        data: ReportRow[];
        meta: {
            current_page: number;
            last_page: number;
            per_page: number;
            total: number;
            from: number | null;
            to: number | null;
        };
    };
    units: { id: number; code: string; name: string }[];
    classrooms: { id: number; label: string }[];
};

export default function HabitReportPage({
    filters,
    summary,
    rows,
    units,
    classrooms,
}: Props) {
    function visit(next: Partial<Props['filters']> & { page?: number }) {
        const merged = { ...filters, ...next };
        router.get(
            HabitReportController.index.url({
                query: {
                    mode: merged.mode,
                    unit_id: merged.unit_id ?? undefined,
                    classroom_id: merged.classroom_id ?? undefined,
                    date_from: merged.date_from,
                    date_to: merged.date_to,
                    page: next.page ?? 1,
                },
            }),
            {},
            { preserveState: false, replace: true },
        );
    }

    const exportQuery = {
        mode: filters.mode,
        unit_id: filters.unit_id ?? undefined,
        classroom_id: filters.classroom_id ?? undefined,
        date_from: filters.date_from,
        date_to: filters.date_to,
    };

    return (
        <>
            <Head title="Laporan pembiasaan" />
            <div className="space-y-6">
                <PageHeader
                    title="Laporan pembiasaan"
                    description="Keterlaksanaan pembiasaan siswa per item dan per rombel."
                    actions={
                        <ExportButtons
                            pdfHref={HabitReportController.exportPdf.url({
                                query: exportQuery,
                            })}
                            excelHref={HabitReportController.exportExcel.url({
                                query: exportQuery,
                            })}
                        />
                    }
                />

                <div className="flex flex-wrap items-center gap-3">
                    <Select
                        value={filters.mode}
                        onValueChange={(v) => visit({ mode: v })}
                    >
                        <SelectTrigger
                            className="w-[200px]"
                            aria-label="Mode laporan pembiasaan"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="item">Per item</SelectItem>
                            <SelectItem value="classroom">
                                Per rombel
                            </SelectItem>
                        </SelectContent>
                    </Select>

                    <Select
                        value={
                            filters.unit_id ? String(filters.unit_id) : 'semua'
                        }
                        onValueChange={(v) =>
                            visit({
                                unit_id: v === 'semua' ? null : Number(v),
                                classroom_id: null,
                            })
                        }
                    >
                        <SelectTrigger
                            className="w-[160px]"
                            aria-label="Filter unit"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="semua">Semua unit</SelectItem>
                            {units.map((u) => (
                                <SelectItem key={u.id} value={String(u.id)}>
                                    {u.code} — {u.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    <Select
                        value={
                            filters.classroom_id
                                ? String(filters.classroom_id)
                                : 'semua'
                        }
                        onValueChange={(v) =>
                            visit({
                                classroom_id: v === 'semua' ? null : Number(v),
                            })
                        }
                    >
                        <SelectTrigger
                            className="w-[180px]"
                            aria-label="Filter rombel"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="semua">Semua rombel</SelectItem>
                            {classrooms.map((c) => (
                                <SelectItem key={c.id} value={String(c.id)}>
                                    {c.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    <Input
                        type="date"
                        value={filters.date_from}
                        onChange={(e) => visit({ date_from: e.target.value })}
                        className="w-[160px]"
                        aria-label="Dari tanggal"
                    />
                    <Input
                        type="date"
                        value={filters.date_to}
                        onChange={(e) => visit({ date_to: e.target.value })}
                        className="w-[160px]"
                        aria-label="Sampai tanggal"
                    />
                </div>

                <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <div className="rounded-lg border border-border bg-card p-3 shadow-xs">
                        <div className="text-xs text-muted-foreground">
                            Total ceklis
                        </div>
                        <div className="text-xl font-semibold tabular-nums">
                            {summary.total_logs}
                        </div>
                    </div>
                    <div className="rounded-lg border border-border bg-card p-3 shadow-xs">
                        <div className="text-xs text-muted-foreground">
                            Terverifikasi
                        </div>
                        <div className="text-xl font-semibold tabular-nums">
                            {summary.verified_logs}
                        </div>
                    </div>
                    <div className="rounded-lg border border-border bg-card p-3 shadow-xs">
                        <div className="text-xs text-muted-foreground">
                            Item aktif
                        </div>
                        <div className="text-xl font-semibold tabular-nums">
                            {summary.active_items}
                        </div>
                    </div>
                    <div className="rounded-lg border border-border bg-card p-3 shadow-xs">
                        <div className="text-xs text-muted-foreground">
                            Siswa aktif
                        </div>
                        <div className="text-xl font-semibold tabular-nums">
                            {summary.active_students}
                        </div>
                    </div>
                </div>

                {rows.data.length === 0 ? (
                    <EmptyState
                        title="Belum ada data pembiasaan"
                        description="Tidak ditemukan item atau catatan pembiasaan pada filter yang dipilih."
                    />
                ) : (
                    <div className="space-y-4">
                        <div className="overflow-x-auto rounded-lg border border-border bg-card shadow-xs">
                            <table className="w-full text-left text-sm">
                                <thead className="border-b border-border bg-muted/40 text-xs font-medium text-muted-foreground">
                                    {filters.mode === 'classroom' ? (
                                        <tr>
                                            <th className="px-4 py-2.5">
                                                Rombel
                                            </th>
                                            <th className="px-4 py-2.5 text-right">
                                                Siswa
                                            </th>
                                            <th className="px-4 py-2.5 text-right">
                                                Total ceklis
                                            </th>
                                            <th className="px-4 py-2.5 text-right">
                                                Terverifikasi
                                            </th>
                                            <th className="px-4 py-2.5 text-right">
                                                Rata-rata / siswa
                                            </th>
                                        </tr>
                                    ) : (
                                        <tr>
                                            <th className="px-4 py-2.5">
                                                Item pembiasaan
                                            </th>
                                            <th className="px-4 py-2.5">
                                                Kategori
                                            </th>
                                            <th className="px-4 py-2.5 text-right">
                                                Total ceklis
                                            </th>
                                            <th className="px-4 py-2.5 text-right">
                                                Terverifikasi
                                            </th>
                                            <th className="px-4 py-2.5 text-right">
                                                Siswa
                                            </th>
                                            <th className="px-4 py-2.5 text-right">
                                                Cakupan
                                            </th>
                                        </tr>
                                    )}
                                </thead>
                                <tbody className="divide-y divide-border">
                                    {rows.data.map((r) =>
                                        filters.mode === 'classroom' ? (
                                            <tr key={r.id}>
                                                <td className="px-4 py-2.5 font-medium">
                                                    {r.unit_code} ·{' '}
                                                    {r.classroom_name}
                                                </td>
                                                <td className="px-4 py-2.5 text-right tabular-nums">
                                                    {r.student_count}
                                                </td>
                                                <td className="px-4 py-2.5 text-right tabular-nums">
                                                    {r.total_done}
                                                </td>
                                                <td className="px-4 py-2.5 text-right tabular-nums">
                                                    {r.verified}
                                                </td>
                                                <td className="px-4 py-2.5 text-right font-medium tabular-nums">
                                                    {r.avg_per_student}
                                                </td>
                                            </tr>
                                        ) : (
                                            <tr key={r.id}>
                                                <td className="px-4 py-2.5 font-medium">
                                                    {r.item_name}
                                                </td>
                                                <td className="px-4 py-2.5 text-muted-foreground">
                                                    {r.category}
                                                </td>
                                                <td className="px-4 py-2.5 text-right tabular-nums">
                                                    {r.total_done}
                                                </td>
                                                <td className="px-4 py-2.5 text-right tabular-nums">
                                                    {r.verified}
                                                </td>
                                                <td className="px-4 py-2.5 text-right tabular-nums">
                                                    {r.unique_students}
                                                </td>
                                                <td className="px-4 py-2.5 text-right font-medium tabular-nums">
                                                    {r.coverage_pct}
                                                </td>
                                            </tr>
                                        ),
                                    )}
                                </tbody>
                            </table>
                        </div>

                        {rows.meta.last_page > 1 ? (
                            <div className="flex items-center justify-between text-xs text-muted-foreground">
                                <span>
                                    Menampilkan {rows.meta.from ?? 0}–
                                    {rows.meta.to ?? 0} dari {rows.meta.total}
                                </span>
                                <div className="flex items-center gap-2">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        disabled={rows.meta.current_page <= 1}
                                        onClick={() =>
                                            visit({
                                                page:
                                                    rows.meta.current_page - 1,
                                            })
                                        }
                                    >
                                        Sebelumnya
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        disabled={
                                            rows.meta.current_page >=
                                            rows.meta.last_page
                                        }
                                        onClick={() =>
                                            visit({
                                                page:
                                                    rows.meta.current_page + 1,
                                            })
                                        }
                                    >
                                        Berikutnya
                                    </Button>
                                </div>
                            </div>
                        ) : null}
                    </div>
                )}
            </div>
        </>
    );
}

HabitReportPage.layout = (page: ReactNode) => (
    <StaffLayout
        breadcrumbs={[
            { title: 'Laporan', href: '#' },
            {
                title: 'Laporan pembiasaan',
                href: HabitReportController.index.url(),
            },
        ]}
    >
        {page}
    </StaffLayout>
);
