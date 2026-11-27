import { Head, router } from '@inertiajs/react';
import type { ReactNode } from 'react';
import QuranReportController from '@/actions/App/Http/Controllers/Admin/QuranReportController';
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
        deposits: number;
        passed: number;
        avg_score: number;
        pib_scores: number;
        avg_pib: number;
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

export default function QuranReportPage({
    filters,
    summary,
    rows,
    units,
    classrooms,
}: Props) {
    function visit(next: Partial<Props['filters']> & { page?: number }) {
        const merged = { ...filters, ...next };
        router.get(
            QuranReportController.index.url({
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
            <Head title="Laporan hafalan & PIB" />
            <div className="space-y-6">
                <PageHeader
                    title="Laporan hafalan & PIB"
                    description="Progres hafalan per rombel, rekap setoran per guru, dan rekap materi PIB."
                    actions={
                        <ExportButtons
                            pdfHref={QuranReportController.exportPdf.url({
                                query: exportQuery,
                            })}
                            excelHref={QuranReportController.exportExcel.url({
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
                            className="w-[210px]"
                            aria-label="Mode laporan hafalan"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="classroom">
                                Progres per rombel
                            </SelectItem>
                            <SelectItem value="teacher">
                                Setoran per guru
                            </SelectItem>
                            <SelectItem value="pib">
                                Rekap materi PIB
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

                <div className="grid grid-cols-2 gap-3 sm:grid-cols-5">
                    <div className="rounded-lg border border-border bg-card p-3 shadow-xs">
                        <div className="text-xs text-muted-foreground">
                            Total setoran
                        </div>
                        <div className="text-xl font-semibold tabular-nums">
                            {summary.deposits}
                        </div>
                    </div>
                    <div className="rounded-lg border border-border bg-card p-3 shadow-xs">
                        <div className="text-xs text-muted-foreground">
                            Setoran lulus
                        </div>
                        <div className="text-xl font-semibold tabular-nums">
                            {summary.passed}
                        </div>
                    </div>
                    <div className="rounded-lg border border-border bg-card p-3 shadow-xs">
                        <div className="text-xs text-muted-foreground">
                            Rata-rata hafalan
                        </div>
                        <div className="text-xl font-semibold tabular-nums">
                            {summary.avg_score}
                        </div>
                    </div>
                    <div className="rounded-lg border border-border bg-card p-3 shadow-xs">
                        <div className="text-xs text-muted-foreground">
                            Penilaian PIB
                        </div>
                        <div className="text-xl font-semibold tabular-nums">
                            {summary.pib_scores}
                        </div>
                    </div>
                    <div className="rounded-lg border border-border bg-card p-3 shadow-xs">
                        <div className="text-xs text-muted-foreground">
                            Rata-rata PIB
                        </div>
                        <div className="text-xl font-semibold tabular-nums">
                            {summary.avg_pib}
                        </div>
                    </div>
                </div>

                {rows.data.length === 0 ? (
                    <EmptyState
                        title="Belum ada data hafalan atau PIB"
                        description="Tidak ditemukan setoran hafalan atau nilai PIB pada filter yang dipilih."
                    />
                ) : (
                    <div className="space-y-4">
                        <div className="overflow-x-auto rounded-lg border border-border bg-card shadow-xs">
                            <table className="w-full text-left text-sm">
                                <thead className="border-b border-border bg-muted/40 text-xs font-medium text-muted-foreground">
                                    {filters.mode === 'teacher' ? (
                                        <tr>
                                            <th className="px-4 py-2.5">
                                                Guru
                                            </th>
                                            <th className="px-4 py-2.5 text-right">
                                                Siswa unik
                                            </th>
                                            <th className="px-4 py-2.5 text-right">
                                                Setoran
                                            </th>
                                            <th className="px-4 py-2.5 text-right">
                                                Lulus
                                            </th>
                                            <th className="px-4 py-2.5 text-right">
                                                Rata-rata
                                            </th>
                                        </tr>
                                    ) : filters.mode === 'pib' ? (
                                        <tr>
                                            <th className="px-4 py-2.5">Bab</th>
                                            <th className="px-4 py-2.5">
                                                Subbab
                                            </th>
                                            <th className="px-4 py-2.5">
                                                Materi
                                            </th>
                                            <th className="px-4 py-2.5 text-right">
                                                Siswa
                                            </th>
                                            <th className="px-4 py-2.5 text-right">
                                                Rata-rata salah
                                            </th>
                                            <th className="px-4 py-2.5 text-right">
                                                Rata-rata nilai
                                            </th>
                                        </tr>
                                    ) : (
                                        <tr>
                                            <th className="px-4 py-2.5">
                                                Rombel
                                            </th>
                                            <th className="px-4 py-2.5 text-right">
                                                Siswa
                                            </th>
                                            <th className="px-4 py-2.5 text-right">
                                                Setoran
                                            </th>
                                            <th className="px-4 py-2.5 text-right">
                                                Lulus
                                            </th>
                                            <th className="px-4 py-2.5 text-right">
                                                Rata-rata hafalan
                                            </th>
                                            <th className="px-4 py-2.5 text-right">
                                                Nilai PIB
                                            </th>
                                            <th className="px-4 py-2.5 text-right">
                                                Rata-rata PIB
                                            </th>
                                        </tr>
                                    )}
                                </thead>
                                <tbody className="divide-y divide-border">
                                    {rows.data.map((r) =>
                                        filters.mode === 'teacher' ? (
                                            <tr key={r.id}>
                                                <td className="px-4 py-2.5 font-medium">
                                                    {r.teacher_name}
                                                </td>
                                                <td className="px-4 py-2.5 text-right tabular-nums">
                                                    {r.student_count}
                                                </td>
                                                <td className="px-4 py-2.5 text-right tabular-nums">
                                                    {r.deposits}
                                                </td>
                                                <td className="px-4 py-2.5 text-right tabular-nums">
                                                    {r.passed}
                                                </td>
                                                <td className="px-4 py-2.5 text-right font-medium tabular-nums">
                                                    {r.avg_score}
                                                </td>
                                            </tr>
                                        ) : filters.mode === 'pib' ? (
                                            <tr key={r.id}>
                                                <td className="px-4 py-2.5">
                                                    {r.chapter}
                                                </td>
                                                <td className="px-4 py-2.5 text-muted-foreground">
                                                    {r.subchapter}
                                                </td>
                                                <td className="px-4 py-2.5 font-medium">
                                                    {r.material}
                                                </td>
                                                <td className="px-4 py-2.5 text-right tabular-nums">
                                                    {r.student_count}
                                                </td>
                                                <td className="px-4 py-2.5 text-right tabular-nums">
                                                    {r.avg_mistakes}
                                                </td>
                                                <td className="px-4 py-2.5 text-right font-medium tabular-nums">
                                                    {r.avg_score}
                                                </td>
                                            </tr>
                                        ) : (
                                            <tr key={r.id}>
                                                <td className="px-4 py-2.5 font-medium">
                                                    {r.unit_code} ·{' '}
                                                    {r.classroom_name}
                                                </td>
                                                <td className="px-4 py-2.5 text-right tabular-nums">
                                                    {r.student_count}
                                                </td>
                                                <td className="px-4 py-2.5 text-right tabular-nums">
                                                    {r.deposits}
                                                </td>
                                                <td className="px-4 py-2.5 text-right tabular-nums">
                                                    {r.passed}
                                                </td>
                                                <td className="px-4 py-2.5 text-right tabular-nums">
                                                    {r.avg_score}
                                                </td>
                                                <td className="px-4 py-2.5 text-right tabular-nums">
                                                    {r.pib_count}
                                                </td>
                                                <td className="px-4 py-2.5 text-right font-medium tabular-nums">
                                                    {r.avg_pib}
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

QuranReportPage.layout = (page: ReactNode) => (
    <StaffLayout
        breadcrumbs={[
            { title: 'Laporan', href: '#' },
            {
                title: 'Laporan hafalan & PIB',
                href: QuranReportController.index.url(),
            },
        ]}
    >
        {page}
    </StaffLayout>
);
