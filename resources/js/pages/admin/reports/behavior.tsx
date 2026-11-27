import { Head, router } from '@inertiajs/react';
import type { ReactNode } from 'react';
import BehaviorReportController from '@/actions/App/Http/Controllers/Admin/BehaviorReportController';
import { EmptyState } from '@/components/app/empty-state';
import { ExportButtons } from '@/components/app/export-buttons';
import { MoneyText } from '@/components/app/money-text';
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
        date_from: string;
        date_to: string;
    };
    summary: {
        records: number;
        positive_points: number;
        violation_points: number;
        open_followups: number;
        vouchers: number;
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

export default function BehaviorReportPage({
    filters,
    summary,
    rows,
    units,
    classrooms,
}: Props) {
    function visit(next: Partial<Props['filters']> & { page?: number }) {
        const merged = { ...filters, ...next };
        router.get(
            BehaviorReportController.index.url({
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
            <Head title="Laporan BK" />
            <div className="space-y-6">
                <PageHeader
                    title="Laporan BK"
                    description="Catatan perilaku per periode, siswa poin tertinggi, tindak lanjut terbuka, dan reward."
                    actions={
                        <ExportButtons
                            pdfHref={BehaviorReportController.exportPdf.url({
                                query: exportQuery,
                            })}
                            excelHref={BehaviorReportController.exportExcel.url(
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
                            className="w-[220px]"
                            aria-label="Mode laporan BK"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="records">
                                Catatan per periode
                            </SelectItem>
                            <SelectItem value="top_points">
                                Siswa poin tertinggi
                            </SelectItem>
                            <SelectItem value="followups">
                                Tindak lanjut terbuka
                            </SelectItem>
                            <SelectItem value="rewards">
                                Reward & voucher
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
                            Catatan aktif
                        </div>
                        <div className="text-xl font-semibold tabular-nums">
                            {summary.records}
                        </div>
                    </div>
                    <div className="rounded-lg border border-border bg-card p-3 shadow-xs">
                        <div className="text-xs text-muted-foreground">
                            Poin kebaikan
                        </div>
                        <div className="text-xl font-semibold tabular-nums">
                            {summary.positive_points}
                        </div>
                    </div>
                    <div className="rounded-lg border border-border bg-card p-3 shadow-xs">
                        <div className="text-xs text-muted-foreground">
                            Poin pelanggaran
                        </div>
                        <div className="text-xl font-semibold tabular-nums">
                            {summary.violation_points}
                        </div>
                    </div>
                    <div className="rounded-lg border border-border bg-card p-3 shadow-xs">
                        <div className="text-xs text-muted-foreground">
                            Tindak lanjut terbuka
                        </div>
                        <div className="text-xl font-semibold tabular-nums">
                            {summary.open_followups}
                        </div>
                    </div>
                    <div className="rounded-lg border border-border bg-card p-3 shadow-xs">
                        <div className="text-xs text-muted-foreground">
                            Penukaran reward
                        </div>
                        <div className="text-xl font-semibold tabular-nums">
                            {summary.vouchers}
                        </div>
                    </div>
                </div>

                {rows.data.length === 0 ? (
                    <EmptyState
                        title="Belum ada data BK"
                        description="Tidak ada catatan atau tindak lanjut untuk filter yang dipilih."
                    />
                ) : (
                    <div className="space-y-4">
                        <div className="overflow-x-auto rounded-lg border border-border bg-card shadow-xs">
                            <table className="w-full text-left text-sm">
                                <thead className="border-b border-border bg-muted/40 text-xs font-medium text-muted-foreground">
                                    {filters.mode === 'top_points' ? (
                                        <tr>
                                            <th className="px-4 py-2.5">
                                                Siswa
                                            </th>
                                            <th className="px-4 py-2.5">
                                                Rombel
                                            </th>
                                            <th className="px-4 py-2.5 text-right">
                                                Pelanggaran
                                            </th>
                                            <th className="px-4 py-2.5 text-right">
                                                Kebaikan
                                            </th>
                                            <th className="px-4 py-2.5 text-right">
                                                Catatan
                                            </th>
                                        </tr>
                                    ) : filters.mode === 'followups' ? (
                                        <tr>
                                            <th className="px-4 py-2.5">
                                                Dipicu
                                            </th>
                                            <th className="px-4 py-2.5">
                                                Siswa
                                            </th>
                                            <th className="px-4 py-2.5">
                                                Rombel
                                            </th>
                                            <th className="px-4 py-2.5">
                                                Tindakan
                                            </th>
                                            <th className="px-4 py-2.5 text-right">
                                                Ambang
                                            </th>
                                        </tr>
                                    ) : filters.mode === 'rewards' ? (
                                        <tr>
                                            <th className="px-4 py-2.5">
                                                Siswa
                                            </th>
                                            <th className="px-4 py-2.5">
                                                Reward
                                            </th>
                                            <th className="px-4 py-2.5 text-right">
                                                Poin
                                            </th>
                                            <th className="px-4 py-2.5 text-right">
                                                Voucher
                                            </th>
                                            <th className="px-4 py-2.5">
                                                Status
                                            </th>
                                            <th className="px-4 py-2.5">
                                                Kode
                                            </th>
                                        </tr>
                                    ) : (
                                        <tr>
                                            <th className="px-4 py-2.5">
                                                Tanggal
                                            </th>
                                            <th className="px-4 py-2.5">
                                                Siswa
                                            </th>
                                            <th className="px-4 py-2.5">
                                                Kategori
                                            </th>
                                            <th className="px-4 py-2.5">
                                                Aturan
                                            </th>
                                            <th className="px-4 py-2.5 text-right">
                                                Poin
                                            </th>
                                            <th className="px-4 py-2.5">
                                                Pencatat
                                            </th>
                                        </tr>
                                    )}
                                </thead>
                                <tbody className="divide-y divide-border">
                                    {rows.data.map((r) =>
                                        filters.mode === 'top_points' ? (
                                            <tr key={r.id}>
                                                <td className="px-4 py-2.5">
                                                    <div className="font-medium">
                                                        {r.student_name}
                                                    </div>
                                                    <div className="text-xs text-muted-foreground">
                                                        NIS {r.nis} ·{' '}
                                                        {r.unit_code}
                                                    </div>
                                                </td>
                                                <td className="px-4 py-2.5 text-muted-foreground">
                                                    {r.classroom_name}
                                                </td>
                                                <td className="px-4 py-2.5 text-right font-medium tabular-nums">
                                                    {r.violation}
                                                </td>
                                                <td className="px-4 py-2.5 text-right tabular-nums">
                                                    {r.positive}
                                                </td>
                                                <td className="px-4 py-2.5 text-right tabular-nums">
                                                    {r.record_count}
                                                </td>
                                            </tr>
                                        ) : filters.mode === 'followups' ? (
                                            <tr key={r.id}>
                                                <td className="px-4 py-2.5 tabular-nums">
                                                    {r.triggered_at}
                                                </td>
                                                <td className="px-4 py-2.5">
                                                    <div className="font-medium">
                                                        {r.student_name}
                                                    </div>
                                                    <div className="text-xs text-muted-foreground">
                                                        NIS {r.nis} ·{' '}
                                                        {r.unit_code}
                                                    </div>
                                                </td>
                                                <td className="px-4 py-2.5 text-muted-foreground">
                                                    {r.classroom_name}
                                                </td>
                                                <td className="px-4 py-2.5">
                                                    {r.action_title}
                                                </td>
                                                <td className="px-4 py-2.5 text-right tabular-nums">
                                                    {r.threshold_points}
                                                </td>
                                            </tr>
                                        ) : filters.mode === 'rewards' ? (
                                            <tr key={r.id}>
                                                <td className="px-4 py-2.5">
                                                    <div className="font-medium">
                                                        {r.student_name}
                                                    </div>
                                                    <div className="text-xs text-muted-foreground">
                                                        NIS {r.nis} ·{' '}
                                                        {r.unit_code}
                                                    </div>
                                                </td>
                                                <td className="px-4 py-2.5">
                                                    {r.offer_name}
                                                </td>
                                                <td className="px-4 py-2.5 text-right tabular-nums">
                                                    {r.cost_points}
                                                </td>
                                                <td className="px-4 py-2.5 text-right">
                                                    <MoneyText
                                                        amount={Number(
                                                            r.voucher_amount,
                                                        )}
                                                    />
                                                </td>
                                                <td className="px-4 py-2.5">
                                                    <StatusBadge variant="muted">
                                                        {r.status}
                                                    </StatusBadge>
                                                </td>
                                                <td className="px-4 py-2.5 font-mono text-xs">
                                                    {r.voucher_code}
                                                </td>
                                            </tr>
                                        ) : (
                                            <tr key={r.id}>
                                                <td className="px-4 py-2.5 tabular-nums">
                                                    {r.date}
                                                </td>
                                                <td className="px-4 py-2.5">
                                                    <div className="font-medium">
                                                        {r.student_name}
                                                    </div>
                                                    <div className="text-xs text-muted-foreground">
                                                        NIS {r.nis} ·{' '}
                                                        {r.unit_code}
                                                    </div>
                                                </td>
                                                <td className="px-4 py-2.5">
                                                    <StatusBadge
                                                        variant={
                                                            r.category_code !==
                                                            'violation'
                                                                ? 'success'
                                                                : 'warning'
                                                        }
                                                    >
                                                        {r.category_label}
                                                    </StatusBadge>
                                                </td>
                                                <td className="px-4 py-2.5">
                                                    {r.rule_name}
                                                </td>
                                                <td className="px-4 py-2.5 text-right tabular-nums">
                                                    {r.points}
                                                </td>
                                                <td className="px-4 py-2.5 text-muted-foreground">
                                                    {r.recorder}
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

BehaviorReportPage.layout = (page: ReactNode) => (
    <StaffLayout
        breadcrumbs={[
            { title: 'Laporan', href: '#' },
            {
                title: 'Laporan BK',
                href: BehaviorReportController.index.url(),
            },
        ]}
    >
        {page}
    </StaffLayout>
);
