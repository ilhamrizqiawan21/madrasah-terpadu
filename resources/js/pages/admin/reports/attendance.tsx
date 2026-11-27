import { Head, router } from '@inertiajs/react';
import type { ReactNode } from 'react';
import AttendanceReportController from '@/actions/App/Http/Controllers/Admin/AttendanceReportController';
import { EmptyState } from '@/components/app/empty-state';
import { ExportButtons } from '@/components/app/export-buttons';
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

type ReportRow = Record<string, string | number>;

type Props = {
    filters: {
        mode: string;
        unit_id: number | null;
        classroom_id: number | null;
        date: string;
        month: string;
    };
    summary: {
        present: number;
        late: number;
        sick: number;
        excused: number;
        absent: number;
        total: number;
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

function statusVariant(code: string) {
    switch (code) {
        case 'present':
            return 'success' as const;
        case 'late':
            return 'warning' as const;
        case 'absent':
            return 'destructive' as const;
        case 'sick':
        case 'excused':
            return 'attention' as const;
        default:
            return 'muted' as const;
    }
}

export default function AttendanceReportPage({
    filters,
    summary,
    rows,
    units,
    classrooms,
}: Props) {
    function visit(next: Partial<Props['filters']> & { page?: number }) {
        const merged = { ...filters, ...next };
        router.get(
            AttendanceReportController.index.url({
                query: {
                    mode: merged.mode,
                    unit_id: merged.unit_id ?? undefined,
                    classroom_id: merged.classroom_id ?? undefined,
                    date: merged.date,
                    month: merged.month,
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
        date: filters.date,
        month: filters.month,
    };

    return (
        <>
            <Head title="Laporan presensi" />
            <div className="space-y-6">
                <PageHeader
                    title="Laporan presensi"
                    description="Rekap presensi harian, bulanan per rombel, dan per siswa."
                    actions={
                        <ExportButtons
                            pdfHref={AttendanceReportController.exportPdf.url({
                                query: exportQuery,
                            })}
                            excelHref={AttendanceReportController.exportExcel.url(
                                { query: exportQuery },
                            )}
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
                            aria-label="Mode laporan"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="daily">Harian</SelectItem>
                            <SelectItem value="monthly">
                                Bulanan per rombel
                            </SelectItem>
                            <SelectItem value="student">Per siswa</SelectItem>
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

                    {filters.mode === 'daily' ? (
                        <Input
                            type="date"
                            value={filters.date}
                            onChange={(e) => visit({ date: e.target.value })}
                            className="w-[165px]"
                            aria-label="Tanggal"
                        />
                    ) : (
                        <Input
                            type="month"
                            value={filters.month}
                            onChange={(e) => visit({ month: e.target.value })}
                            className="w-[165px]"
                            aria-label="Bulan"
                        />
                    )}
                </div>

                <div className="grid grid-cols-2 gap-3 sm:grid-cols-5">
                    <div className="rounded-lg border border-border bg-card p-3 shadow-xs">
                        <div className="text-xs text-muted-foreground">
                            Hadir
                        </div>
                        <div className="text-xl font-semibold tabular-nums">
                            {summary.present}
                        </div>
                    </div>
                    <div className="rounded-lg border border-border bg-card p-3 shadow-xs">
                        <div className="text-xs text-muted-foreground">
                            Terlambat
                        </div>
                        <div className="text-xl font-semibold tabular-nums">
                            {summary.late}
                        </div>
                    </div>
                    <div className="rounded-lg border border-border bg-card p-3 shadow-xs">
                        <div className="text-xs text-muted-foreground">
                            Sakit
                        </div>
                        <div className="text-xl font-semibold tabular-nums">
                            {summary.sick}
                        </div>
                    </div>
                    <div className="rounded-lg border border-border bg-card p-3 shadow-xs">
                        <div className="text-xs text-muted-foreground">
                            Izin
                        </div>
                        <div className="text-xl font-semibold tabular-nums">
                            {summary.excused}
                        </div>
                    </div>
                    <div className="rounded-lg border border-border bg-card p-3 shadow-xs">
                        <div className="text-xs text-muted-foreground">
                            Alpa
                        </div>
                        <div className="text-xl font-semibold tabular-nums">
                            {summary.absent}
                        </div>
                    </div>
                </div>

                {rows.data.length === 0 ? (
                    <EmptyState
                        title="Belum ada data presensi"
                        description="Tidak ditemukan siswa atau catatan presensi untuk filter yang dipilih."
                    />
                ) : (
                    <div className="space-y-4">
                        <div className="overflow-x-auto rounded-lg border border-border bg-card shadow-xs">
                            <table className="w-full text-left text-sm">
                                <thead className="border-b border-border bg-muted/40 text-xs font-medium text-muted-foreground">
                                    {filters.mode === 'daily' ? (
                                        <tr>
                                            <th className="px-4 py-2.5">
                                                Siswa
                                            </th>
                                            <th className="px-4 py-2.5">
                                                Rombel
                                            </th>
                                            <th className="px-4 py-2.5">
                                                Status
                                            </th>
                                            <th className="px-4 py-2.5">
                                                Masuk
                                            </th>
                                            <th className="px-4 py-2.5">
                                                Pulang
                                            </th>
                                        </tr>
                                    ) : filters.mode === 'monthly' ? (
                                        <tr>
                                            <th className="px-4 py-2.5">
                                                Rombel
                                            </th>
                                            <th className="px-4 py-2.5 text-right">
                                                Siswa
                                            </th>
                                            <th className="px-4 py-2.5 text-right">
                                                Hadir
                                            </th>
                                            <th className="px-4 py-2.5 text-right">
                                                Terlambat
                                            </th>
                                            <th className="px-4 py-2.5 text-right">
                                                Sakit
                                            </th>
                                            <th className="px-4 py-2.5 text-right">
                                                Izin
                                            </th>
                                            <th className="px-4 py-2.5 text-right">
                                                Alpa
                                            </th>
                                            <th className="px-4 py-2.5 text-right">
                                                % Hadir
                                            </th>
                                        </tr>
                                    ) : (
                                        <tr>
                                            <th className="px-4 py-2.5">
                                                Siswa
                                            </th>
                                            <th className="px-4 py-2.5">
                                                Rombel
                                            </th>
                                            <th className="px-4 py-2.5 text-right">
                                                Hadir
                                            </th>
                                            <th className="px-4 py-2.5 text-right">
                                                Terlambat
                                            </th>
                                            <th className="px-4 py-2.5 text-right">
                                                Sakit
                                            </th>
                                            <th className="px-4 py-2.5 text-right">
                                                Izin
                                            </th>
                                            <th className="px-4 py-2.5 text-right">
                                                Alpa
                                            </th>
                                            <th className="px-4 py-2.5 text-right">
                                                % Hadir
                                            </th>
                                        </tr>
                                    )}
                                </thead>
                                <tbody className="divide-y divide-border">
                                    {rows.data.map((r) =>
                                        filters.mode === 'daily' ? (
                                            <tr key={r.id}>
                                                <td className="px-4 py-2.5">
                                                    <div className="font-medium text-foreground">
                                                        {r.student_name}
                                                    </div>
                                                    <div className="text-xs text-muted-foreground">
                                                        NIS {r.nis}
                                                    </div>
                                                </td>
                                                <td className="px-4 py-2.5 text-muted-foreground">
                                                    {r.unit_code} ·{' '}
                                                    {r.classroom_name}
                                                </td>
                                                <td className="px-4 py-2.5">
                                                    <StatusBadge
                                                        variant={statusVariant(
                                                            String(
                                                                r.status_code,
                                                            ),
                                                        )}
                                                    >
                                                        {r.status_label}
                                                    </StatusBadge>
                                                </td>
                                                <td className="px-4 py-2.5 tabular-nums">
                                                    {r.checked_in}
                                                </td>
                                                <td className="px-4 py-2.5 tabular-nums">
                                                    {r.checked_out}
                                                </td>
                                            </tr>
                                        ) : filters.mode === 'monthly' ? (
                                            <tr key={r.id}>
                                                <td className="px-4 py-2.5 font-medium">
                                                    {r.unit_code} ·{' '}
                                                    {r.classroom_name}
                                                </td>
                                                <td className="px-4 py-2.5 text-right tabular-nums">
                                                    {r.student_count}
                                                </td>
                                                <td className="px-4 py-2.5 text-right tabular-nums">
                                                    {r.present}
                                                </td>
                                                <td className="px-4 py-2.5 text-right tabular-nums">
                                                    {r.late}
                                                </td>
                                                <td className="px-4 py-2.5 text-right tabular-nums">
                                                    {r.sick}
                                                </td>
                                                <td className="px-4 py-2.5 text-right tabular-nums">
                                                    {r.excused}
                                                </td>
                                                <td className="px-4 py-2.5 text-right tabular-nums">
                                                    {r.absent}
                                                </td>
                                                <td className="px-4 py-2.5 text-right font-medium tabular-nums">
                                                    {r.attendance_pct}
                                                </td>
                                            </tr>
                                        ) : (
                                            <tr key={r.id}>
                                                <td className="px-4 py-2.5">
                                                    <div className="font-medium text-foreground">
                                                        {r.student_name}
                                                    </div>
                                                    <div className="text-xs text-muted-foreground">
                                                        NIS {r.nis}
                                                    </div>
                                                </td>
                                                <td className="px-4 py-2.5 text-muted-foreground">
                                                    {r.unit_code} ·{' '}
                                                    {r.classroom_name}
                                                </td>
                                                <td className="px-4 py-2.5 text-right tabular-nums">
                                                    {r.present}
                                                </td>
                                                <td className="px-4 py-2.5 text-right tabular-nums">
                                                    {r.late}
                                                </td>
                                                <td className="px-4 py-2.5 text-right tabular-nums">
                                                    {r.sick}
                                                </td>
                                                <td className="px-4 py-2.5 text-right tabular-nums">
                                                    {r.excused}
                                                </td>
                                                <td className="px-4 py-2.5 text-right tabular-nums">
                                                    {r.absent}
                                                </td>
                                                <td className="px-4 py-2.5 text-right font-medium tabular-nums">
                                                    {r.attendance_pct}
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

AttendanceReportPage.layout = (page: ReactNode) => (
    <StaffLayout
        breadcrumbs={[
            { title: 'Laporan', href: '#' },
            {
                title: 'Laporan presensi',
                href: AttendanceReportController.index.url(),
            },
        ]}
    >
        {page}
    </StaffLayout>
);
