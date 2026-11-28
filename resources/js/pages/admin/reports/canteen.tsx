import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import CanteenReportController from '@/actions/App/Http/Controllers/Admin/CanteenReportController';
import type { DataTableColumn } from '@/components/app/data-table';
import { DataTable } from '@/components/app/data-table';
import { DateInput } from '@/components/app/date-input';
import { ExportButtons } from '@/components/app/export-buttons';
import { MoneyText } from '@/components/app/money-text';
import { PageHeader } from '@/components/app/page-header';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import StaffLayout from '@/layouts/staff-layout';
import { dashboard } from '@/routes/admin';

type CashierRow = {
    cashier_id: number;
    cashier_name: string;
    tx_count: number;
    total: number;
    paid_from_wallet: number;
    paid_from_vouchers: number;
};

type Props = {
    summary: {
        sales_total: number;
        sales_wallet: number;
        sales_voucher: number;
        tx_count: number;
        topup_cash: number;
        topup_online: number;
        floating_balance: number;
    };
    byCashier: CashierRow[];
    units: { code: string; name: string }[];
    filters: { unit: string; from: string; to: string };
};

export default function CanteenReportIndex({
    summary,
    byCashier,
    units,
    filters,
}: Props) {
    const [fromDate, setFromDate] = useState(filters.from);
    const [toDate, setToDate] = useState(filters.to);
    const [loading, setLoading] = useState(false);

    function buildQuery(next: Partial<Props['filters']>) {
        return {
            unit: next.unit ?? filters.unit,
            from: next.from ?? fromDate,
            to: next.to ?? toDate,
        };
    }

    function applyFilters(next: Partial<Props['filters']>) {
        router.get(CanteenReportController.index(), buildQuery(next), {
            preserveScroll: true,
            preserveState: true,
            onStart: () => setLoading(true),
            onFinish: () => setLoading(false),
        });
    }

    const queryParams = buildQuery({});
    const pdfHref = CanteenReportController.exportPdf.url({
        query: queryParams,
    });
    const excelHref = CanteenReportController.exportExcel.url({
        query: queryParams,
    });

    const columns: DataTableColumn<CashierRow>[] = [
        {
            key: 'cashier_name',
            label: 'Kasir',
            render: (row) => (
                <span className="font-medium">{row.cashier_name}</span>
            ),
        },
        {
            key: 'tx_count',
            label: 'Transaksi',
            align: 'right',
            render: (row) => row.tx_count,
        },
        {
            key: 'total',
            label: 'Total belanja',
            align: 'right',
            render: (row) => (
                <span className="font-semibold">
                    <MoneyText amount={row.total} />
                </span>
            ),
        },
        {
            key: 'paid_from_wallet',
            label: 'Dari dompet',
            align: 'right',
            render: (row) => <MoneyText amount={row.paid_from_wallet} />,
        },
        {
            key: 'paid_from_vouchers',
            label: 'Subsidi voucher',
            align: 'right',
            render: (row) => (
                <span className="text-primary">
                    <MoneyText amount={row.paid_from_vouchers} />
                </span>
            ),
        },
    ];

    return (
        <StaffLayout
            breadcrumbs={[
                { title: 'Beranda', href: dashboard() },
                {
                    title: 'Laporan kantin',
                    href: CanteenReportController.index(),
                },
            ]}
        >
            <Head title="Laporan kantin" />

            <div className="space-y-6">
                <PageHeader
                    title="Laporan kantin & dompet siswa"
                    description="Penjualan harian per kasir, top-up per metode, saldo mengendap, dan pemakaian voucher."
                    actions={
                        <ExportButtons
                            pdfHref={pdfHref}
                            excelHref={excelHref}
                        />
                    }
                />

                <div className="flex flex-wrap items-center gap-3">
                    <Select
                        value={filters.unit}
                        onValueChange={(value) => applyFilters({ unit: value })}
                    >
                        <SelectTrigger className="w-48">
                            <SelectValue placeholder="Pilih unit" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Semua unit</SelectItem>
                            {units.map((u) => (
                                <SelectItem key={u.code} value={u.code}>
                                    {u.code} — {u.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    <div className="flex items-center gap-2">
                        <DateInput
                            value={fromDate}
                            onChange={(e) => setFromDate(e.target.value)}
                            className="w-40"
                        />
                        <span className="text-xs text-muted-foreground">
                            s.d.
                        </span>
                        <DateInput
                            value={toDate}
                            onChange={(e) => setToDate(e.target.value)}
                            className="w-40"
                        />
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            onClick={() => applyFilters({})}
                        >
                            Terapkan
                        </Button>
                    </div>
                </div>

                <dl className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div className="rounded-xl border border-border bg-card p-4">
                        <dt className="text-xs text-muted-foreground">
                            Total penjualan ({summary.tx_count} transaksi)
                        </dt>
                        <dd className="mt-1 text-xl font-semibold">
                            <MoneyText amount={summary.sales_total} />
                        </dd>
                        <p className="mt-1 text-xs text-muted-foreground">
                            Dompet: <MoneyText amount={summary.sales_wallet} />
                        </p>
                    </div>

                    <div className="rounded-xl border border-border bg-card p-4">
                        <dt className="text-xs text-muted-foreground">
                            Subsidi voucher sekolah
                        </dt>
                        <dd className="mt-1 text-xl font-semibold text-primary">
                            <MoneyText amount={summary.sales_voucher} />
                        </dd>
                        <p className="mt-1 text-xs text-muted-foreground">
                            Terpisah dari uang wali
                        </p>
                    </div>

                    <div className="rounded-xl border border-border bg-card p-4">
                        <dt className="text-xs text-muted-foreground">
                            Top-up masuk
                        </dt>
                        <dd className="mt-1 text-xl font-semibold">
                            <MoneyText
                                amount={
                                    summary.topup_cash + summary.topup_online
                                }
                            />
                        </dd>
                        <p className="mt-1 text-xs text-muted-foreground">
                            Tunai: <MoneyText amount={summary.topup_cash} /> ·
                            Online: <MoneyText amount={summary.topup_online} />
                        </p>
                    </div>

                    <div className="rounded-xl border border-border bg-card p-4">
                        <dt className="text-xs text-muted-foreground">
                            Saldo mengendap siswa
                        </dt>
                        <dd className="mt-1 text-xl font-semibold">
                            <MoneyText amount={summary.floating_balance} />
                        </dd>
                        <p className="mt-1 text-xs text-muted-foreground">
                            Akumulasi dompet aktif
                        </p>
                    </div>
                </dl>

                <DataTable
                    columns={columns}
                    data={byCashier}
                    rowKey={(row) => row.cashier_id}
                    loading={loading}
                    empty={{
                        title: 'Belum ada penjualan kantin',
                        description:
                            'Tidak ada transaksi selesai pada rentang tanggal dan unit ini.',
                    }}
                />
            </div>
        </StaffLayout>
    );
}
