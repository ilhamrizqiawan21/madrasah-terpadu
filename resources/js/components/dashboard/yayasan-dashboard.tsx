import { Link, router } from '@inertiajs/react';
import { ArrowRight, MessageCircle } from 'lucide-react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import DashboardController from '@/actions/App/Http/Controllers/Admin/DashboardController';
import InvoiceController from '@/actions/App/Http/Controllers/Admin/InvoiceController';
import WalletController from '@/actions/App/Http/Controllers/Admin/WalletController';
import { DateInput } from '@/components/app/date-input';
import { DateText } from '@/components/app/date-text';
import { MoneyText } from '@/components/app/money-text';
import type { StatusBadgeVariant } from '@/components/app/status-badge';
import { StatusBadge } from '@/components/app/status-badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { formatRupiah } from '@/lib/format';
import { cn } from '@/lib/utils';

export type YayasanDashboardData = {
    filters: {
        unit: string;
        period: string;
        from: string | null;
        to: string;
        units: { code: string; name: string }[];
        periods: { value: string; label: string }[];
    };
    modules: { billing: boolean; wallet: boolean };
    services: { key: string; label: string; status: string; tone: string }[];
    cash_in: { total: number; invoices: number; topups: number; count: number };
    receivables: {
        total: number;
        overdue: number;
        due_in_period: number;
        paid_in_period: number;
        collection_rate: number | null;
    } | null;
    trend: { month: number; invoices: number; topups: number }[];
    composition: { label: string; amount: number }[];
    wallet_flow: { period: number; all_time: number; balance: number } | null;
    units: { code: string; name: string; students: number; cash_in: number }[];
    attendance:
        | {
              code: string;
              present: number;
              late: number;
              excused: number;
              absent: number;
          }[]
        | null;
    top_arrears: {
        invoice_id: number;
        description: string;
        student_id: number;
        student_name: string;
        unit_code: string;
        remaining: number;
        due_date: string;
        whatsapp: string | null;
    }[];
    recent_cash: {
        key: string;
        kind: string;
        title: string;
        detail: string;
        amount: number;
        at: string;
    }[];
    pending_modules: {
        key: string;
        label: string;
        detail: string;
        href: string | null;
    }[];
};

const MONTHS = [
    'Jan',
    'Feb',
    'Mar',
    'Apr',
    'Mei',
    'Jun',
    'Jul',
    'Agu',
    'Sep',
    'Okt',
    'Nov',
    'Des',
];

const SEGMENT_COLORS = [
    'bg-chart-1',
    'bg-chart-3',
    'bg-chart-2',
    'bg-chart-4',
    'bg-chart-5',
];

const numberFormatter = new Intl.NumberFormat('id-ID');

function toneVariant(tone: string): StatusBadgeVariant {
    if (tone === 'success' || tone === 'warning') {
        return tone;
    }

    return 'muted';
}

export function YayasanDashboard({ data }: { data: YayasanDashboardData }) {
    const { filters } = data;
    const pending = (key: string) =>
        data.pending_modules.find((m) => m.key === key);
    const assets = pending('assets');

    return (
        <section aria-labelledby="yayasan-title" className="space-y-4">
            <FilterBar data={data} />

            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <KpiCard
                    label="Kas masuk"
                    value={<MoneyText amount={data.cash_in.total} />}
                    rows={[
                        ['Tagihan siswa', data.cash_in.invoices],
                        ['Isi saldo kantin', data.cash_in.topups],
                    ]}
                />
                <PendingKpi
                    label="Kas keluar"
                    module={pending('cash_out')}
                    note="Pengeluaran operasional dan gaji belum dicatat di aplikasi ini."
                />
                <PendingKpi
                    label="Selisih kas periode"
                    module={pending('cash_out')}
                    note="Dihitung setelah kas keluar tercatat."
                />
                {data.receivables ? (
                    <KpiCard
                        label="Total piutang siswa"
                        value={<MoneyText amount={data.receivables.total} />}
                        rows={[
                            ['Menunggak', data.receivables.overdue],
                            [
                                'Jatuh tempo periode ini',
                                data.receivables.due_in_period,
                            ],
                        ]}
                        footer={
                            <div className="flex items-center justify-between gap-2 text-xs">
                                <span className="text-muted-foreground">
                                    Capaian tagihan periode:{' '}
                                    <span className="font-semibold text-foreground tabular-nums">
                                        {data.receivables.collection_rate ===
                                        null
                                            ? '—'
                                            : `${numberFormatter.format(data.receivables.collection_rate)}%`}
                                    </span>
                                </span>
                                <Link
                                    href={InvoiceController.index.url()}
                                    className="inline-flex items-center gap-1 font-medium text-primary hover:underline"
                                >
                                    Tunggakan
                                    <ArrowRight className="size-3" />
                                </Link>
                            </div>
                        }
                    />
                ) : (
                    <PendingKpi
                        label="Total piutang siswa"
                        module={null}
                        note="Modul tagihan belum dinyalakan di Kunci Fitur."
                    />
                )}
            </div>

            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                {['bos', 'zis', 'ppdb', 'dormitory'].map((key) => {
                    const module = pending(key);

                    return module ? (
                        <PendingModuleCard key={key} module={module} />
                    ) : null;
                })}
            </div>

            <div className="grid gap-4 lg:grid-cols-3">
                <Panel
                    title="Arus kas masuk 12 bulan"
                    subtitle={`Tahun ${filters.to.slice(0, 4)}`}
                    className="lg:col-span-2"
                >
                    <TrendChart trend={data.trend} />
                </Panel>
                <Panel title="Komposisi kas masuk" subtitle="Periode terpilih">
                    <Composition items={data.composition} />
                </Panel>
            </div>

            <div className="grid gap-4 lg:grid-cols-3">
                <Panel
                    title="Dompet siswa & kas lain"
                    subtitle="Aliran dana lainnya"
                >
                    <ul className="space-y-2 text-sm">
                        {data.wallet_flow ? (
                            <>
                                <FlowRow
                                    label="Isi saldo kantin (periode)"
                                    hint={`Sejak awal: ${formatRupiah(data.wallet_flow.all_time)}`}
                                    amount={data.wallet_flow.period}
                                    href={WalletController.index.url()}
                                />
                                <FlowRow
                                    label="Saldo dompet mengendap"
                                    hint="Total saldo semua siswa saat ini"
                                    amount={data.wallet_flow.balance}
                                />
                            </>
                        ) : (
                            <FlowRow
                                label="Isi saldo kantin"
                                hint="Modul kantin belum dinyalakan"
                                amount={null}
                            />
                        )}
                        <FlowRow
                            label="Penerimaan eksternal & sewa"
                            hint="Belum dicatat"
                            amount={null}
                        />
                        <FlowRow
                            label="Rata-rata pengeluaran bulanan"
                            hint="Butuh pencatatan kas keluar"
                            amount={null}
                        />
                    </ul>
                </Panel>
                <Panel
                    title="Perbandingan unit"
                    subtitle="Siswa aktif & penerimaan tagihan periode"
                    className="lg:col-span-2"
                >
                    <UnitComparison units={data.units} />
                </Panel>
            </div>

            <div className="grid gap-4 lg:grid-cols-3">
                <Panel
                    title="Presensi gerbang hari ini"
                    subtitle="Per unit"
                    className="lg:col-span-2"
                >
                    {data.attendance ? (
                        <AttendanceByUnit rows={data.attendance} />
                    ) : (
                        <p className="text-sm text-muted-foreground">
                            Modul presensi belum dinyalakan.
                        </p>
                    )}
                </Panel>
                {assets && <PendingModuleCard module={assets} />}
            </div>

            <div className="grid gap-4 lg:grid-cols-2">
                <Panel
                    title="5 piutang tertinggi"
                    action={
                        <Link
                            href={InvoiceController.index.url()}
                            className="text-xs font-medium text-primary hover:underline"
                        >
                            Semua tagihan
                        </Link>
                    }
                >
                    <TopArrears
                        rows={data.top_arrears}
                        billingOn={data.modules.billing}
                    />
                </Panel>
                <Panel title="Transaksi kas masuk terbaru">
                    <RecentCash rows={data.recent_cash} />
                </Panel>
            </div>
        </section>
    );
}

function FilterBar({ data }: { data: YayasanDashboardData }) {
    const { filters } = data;
    const [from, setFrom] = useState(filters.from ?? '');
    const [to, setTo] = useState(filters.to);

    const visit = (next: Record<string, string | undefined>) => {
        const query = {
            unit: filters.unit === 'all' ? undefined : filters.unit,
            periode:
                filters.period === 'bulan_ini' ? undefined : filters.period,
            ...next,
        };

        router.get(
            DashboardController.url({ query }),
            {},
            { preserveScroll: true, preserveState: true, replace: true },
        );
    };

    return (
        <div className="rounded-xl border border-border bg-card p-4 shadow-xs">
            <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div className="space-y-1">
                    <h2 id="yayasan-title" className="text-base font-semibold">
                        Dasbor Yayasan
                    </h2>
                    <div className="flex flex-wrap gap-1.5">
                        {data.services.map((service) => (
                            <StatusBadge
                                key={service.key}
                                variant={toneVariant(service.tone)}
                            >
                                {service.label}: {service.status}
                            </StatusBadge>
                        ))}
                    </div>
                </div>

                <div className="flex flex-wrap items-end gap-3">
                    <div className="space-y-1.5">
                        <Label htmlFor="yayasan-unit">Unit</Label>
                        <Select
                            value={filters.unit}
                            onValueChange={(value) =>
                                visit({
                                    unit: value === 'all' ? undefined : value,
                                })
                            }
                        >
                            <SelectTrigger id="yayasan-unit" className="w-44">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">Semua unit</SelectItem>
                                {filters.units.map((unit) => (
                                    <SelectItem
                                        key={unit.code}
                                        value={unit.code}
                                    >
                                        {unit.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="space-y-1.5">
                        <Label htmlFor="yayasan-period">Periode</Label>
                        <Select
                            value={filters.period}
                            onValueChange={(value) =>
                                value === 'rentang'
                                    ? visit({
                                          periode: value,
                                          dari: from || undefined,
                                          sampai: to,
                                      })
                                    : visit({ periode: value })
                            }
                        >
                            <SelectTrigger id="yayasan-period" className="w-44">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {filters.periods.map((period) => (
                                    <SelectItem
                                        key={period.value}
                                        value={period.value}
                                    >
                                        {period.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    {filters.period === 'rentang' && (
                        <form
                            className="flex flex-wrap items-end gap-2"
                            onSubmit={(e) => {
                                e.preventDefault();
                                visit({
                                    periode: 'rentang',
                                    dari: from,
                                    sampai: to,
                                });
                            }}
                        >
                            <div className="space-y-1.5">
                                <Label htmlFor="yayasan-from">Dari</Label>
                                <DateInput
                                    id="yayasan-from"
                                    value={from}
                                    onChange={(e) => setFrom(e.target.value)}
                                    className="w-36"
                                />
                            </div>
                            <div className="space-y-1.5">
                                <Label htmlFor="yayasan-to">Sampai</Label>
                                <DateInput
                                    id="yayasan-to"
                                    value={to}
                                    onChange={(e) => setTo(e.target.value)}
                                    className="w-36"
                                />
                            </div>
                            <Button type="submit" variant="outline">
                                Terapkan
                            </Button>
                        </form>
                    )}
                </div>
            </div>
        </div>
    );
}

function Panel({
    title,
    subtitle,
    action,
    className,
    children,
}: {
    title: string;
    subtitle?: string;
    action?: ReactNode;
    className?: string;
    children: ReactNode;
}) {
    return (
        <div
            className={cn(
                'rounded-xl border border-border bg-card p-4 shadow-xs',
                className,
            )}
        >
            <div className="mb-3 flex items-start justify-between gap-2">
                <div>
                    <h3 className="text-sm font-semibold">{title}</h3>
                    {subtitle && (
                        <p className="text-xs text-muted-foreground">
                            {subtitle}
                        </p>
                    )}
                </div>
                {action}
            </div>
            {children}
        </div>
    );
}

function KpiCard({
    label,
    value,
    rows,
    footer,
}: {
    label: string;
    value: ReactNode;
    rows: [string, number][];
    footer?: ReactNode;
}) {
    return (
        <div className="flex flex-col gap-3 rounded-xl border border-border bg-card p-4 shadow-xs">
            <div>
                <p className="text-xs text-muted-foreground">{label}</p>
                <p className="mt-1 text-2xl font-semibold">{value}</p>
            </div>
            <dl className="space-y-1 text-xs">
                {rows.map(([name, amount]) => (
                    <div key={name} className="flex justify-between gap-2">
                        <dt className="text-muted-foreground">{name}</dt>
                        <dd>
                            <MoneyText amount={amount} />
                        </dd>
                    </div>
                ))}
            </dl>
            {footer && (
                <div className="mt-auto border-t border-border pt-2">
                    {footer}
                </div>
            )}
        </div>
    );
}

function PendingKpi({
    label,
    module,
    note,
}: {
    label: string;
    module: YayasanDashboardData['pending_modules'][number] | null | undefined;
    note: string;
}) {
    return (
        <div className="flex flex-col gap-2 rounded-xl border border-dashed border-border bg-card p-4">
            <p className="text-xs text-muted-foreground">{label}</p>
            <p className="text-2xl font-semibold text-muted-foreground">—</p>
            <p className="text-xs text-muted-foreground">{note}</p>
            {module?.href && (
                <Link
                    href={module.href}
                    className="mt-auto text-xs font-medium text-primary hover:underline"
                >
                    Lihat rencana modul
                </Link>
            )}
        </div>
    );
}

function PendingModuleCard({
    module,
}: {
    module: YayasanDashboardData['pending_modules'][number];
}) {
    const body = (
        <>
            <div className="flex items-start justify-between gap-2">
                <p className="text-sm font-semibold">{module.label}</p>
                <StatusBadge variant="muted">Belum dicatat</StatusBadge>
            </div>
            <p className="text-xs text-muted-foreground">{module.detail}</p>
        </>
    );

    return module.href ? (
        <Link
            href={module.href}
            className="flex flex-col gap-2 rounded-xl border border-dashed border-border bg-card p-4 transition-colors hover:bg-muted/50 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
        >
            {body}
        </Link>
    ) : (
        <div className="flex flex-col gap-2 rounded-xl border border-dashed border-border bg-card p-4">
            {body}
        </div>
    );
}

function TrendChart({ trend }: { trend: YayasanDashboardData['trend'] }) {
    const max = Math.max(1, ...trend.map((m) => m.invoices + m.topups));
    const hasData = trend.some((m) => m.invoices + m.topups > 0);

    return (
        <div className="space-y-3">
            <div
                role="img"
                aria-label="Grafik kas masuk per bulan"
                className="flex h-44 items-end gap-1.5"
            >
                {trend.map((m) => {
                    const total = m.invoices + m.topups;

                    return (
                        <div
                            key={m.month}
                            className="flex h-full flex-1 flex-col items-center justify-end gap-1"
                            title={`${MONTHS[m.month - 1]}: ${formatRupiah(total)}`}
                        >
                            <div
                                className="flex w-full flex-col justify-end overflow-hidden rounded-t-sm"
                                style={{ height: `${(total / max) * 100}%` }}
                            >
                                <div
                                    className="bg-chart-3"
                                    style={{
                                        height:
                                            total > 0
                                                ? `${(m.topups / total) * 100}%`
                                                : 0,
                                    }}
                                />
                                <div
                                    className="bg-chart-1"
                                    style={{
                                        height:
                                            total > 0
                                                ? `${(m.invoices / total) * 100}%`
                                                : 0,
                                    }}
                                />
                            </div>
                            <span className="text-[10px] text-muted-foreground">
                                {MONTHS[m.month - 1]}
                            </span>
                        </div>
                    );
                })}
            </div>
            <div className="flex flex-wrap items-center gap-4 text-xs text-muted-foreground">
                <Legend color="bg-chart-1" label="Tagihan siswa" />
                <Legend color="bg-chart-3" label="Isi saldo kantin" />
                {!hasData && <span>Belum ada kas masuk tahun ini.</span>}
            </div>
        </div>
    );
}

function Legend({ color, label }: { color: string; label: string }) {
    return (
        <span className="inline-flex items-center gap-1.5">
            <span aria-hidden className={cn('size-2.5 rounded-sm', color)} />
            {label}
        </span>
    );
}

function Composition({
    items,
}: {
    items: YayasanDashboardData['composition'];
}) {
    const total = items.reduce((sum, i) => sum + i.amount, 0);

    if (total === 0) {
        return (
            <p className="text-sm text-muted-foreground">
                Belum ada kas masuk pada periode ini.
            </p>
        );
    }

    return (
        <div className="space-y-3">
            <div className="flex h-3 overflow-hidden rounded-full bg-muted">
                {items.map((item, index) => (
                    <div
                        key={item.label}
                        className={
                            SEGMENT_COLORS[index % SEGMENT_COLORS.length]
                        }
                        style={{ width: `${(item.amount / total) * 100}%` }}
                    />
                ))}
            </div>
            <ul className="space-y-1.5 text-sm">
                {items.map((item, index) => (
                    <li
                        key={item.label}
                        className="flex items-center justify-between gap-2"
                    >
                        <Legend
                            color={
                                SEGMENT_COLORS[index % SEGMENT_COLORS.length]
                            }
                            label={item.label}
                        />
                        <span className="text-right tabular-nums">
                            <MoneyText amount={item.amount} />{' '}
                            <span className="text-xs text-muted-foreground">
                                {Math.round((item.amount / total) * 100)}%
                            </span>
                        </span>
                    </li>
                ))}
            </ul>
        </div>
    );
}

function FlowRow({
    label,
    hint,
    amount,
    href,
}: {
    label: string;
    hint: string;
    amount: number | null;
    href?: string;
}) {
    const content = (
        <>
            <div>
                <p className="font-medium">{label}</p>
                <p className="text-xs text-muted-foreground">{hint}</p>
            </div>
            {amount === null ? (
                <span className="text-muted-foreground">—</span>
            ) : (
                <MoneyText amount={amount} className="font-semibold" />
            )}
        </>
    );

    return (
        <li>
            {href ? (
                <Link
                    href={href}
                    className="flex items-center justify-between gap-3 rounded-lg border border-border p-3 transition-colors hover:bg-muted/50"
                >
                    {content}
                </Link>
            ) : (
                <div className="flex items-center justify-between gap-3 rounded-lg border border-border p-3">
                    {content}
                </div>
            )}
        </li>
    );
}

function UnitComparison({ units }: { units: YayasanDashboardData['units'] }) {
    if (units.length === 0) {
        return (
            <p className="text-sm text-muted-foreground">
                Belum ada unit aktif.
            </p>
        );
    }

    const maxStudents = Math.max(1, ...units.map((u) => u.students));
    const maxCash = Math.max(1, ...units.map((u) => u.cash_in));

    return (
        <ul className="space-y-3">
            {units.map((unit) => (
                <li key={unit.code} className="space-y-1.5">
                    <div className="flex justify-between gap-2 text-sm">
                        <span className="font-medium">{unit.name}</span>
                        <span className="text-xs text-muted-foreground tabular-nums">
                            {numberFormatter.format(unit.students)} siswa ·{' '}
                            {formatRupiah(unit.cash_in)}
                        </span>
                    </div>
                    <Bar
                        value={unit.students}
                        max={maxStudents}
                        color="bg-chart-1"
                        label="Siswa aktif"
                    />
                    <Bar
                        value={unit.cash_in}
                        max={maxCash}
                        color="bg-chart-2"
                        label="Penerimaan tagihan"
                    />
                </li>
            ))}
            <li className="flex gap-4 pt-1 text-xs text-muted-foreground">
                <Legend color="bg-chart-1" label="Siswa aktif" />
                <Legend color="bg-chart-2" label="Penerimaan tagihan" />
            </li>
        </ul>
    );
}

function Bar({
    value,
    max,
    color,
    label,
}: {
    value: number;
    max: number;
    color: string;
    label: string;
}) {
    return (
        <div
            className="h-2 overflow-hidden rounded-full bg-muted"
            role="meter"
            aria-label={label}
            aria-valuenow={value}
            aria-valuemin={0}
            aria-valuemax={max}
        >
            <div
                className={cn('h-full rounded-full', color)}
                style={{ width: `${(value / max) * 100}%` }}
            />
        </div>
    );
}

function AttendanceByUnit({
    rows,
}: {
    rows: NonNullable<YayasanDashboardData['attendance']>;
}) {
    const segments = [
        { key: 'present', label: 'Hadir tepat', color: 'bg-chart-2' },
        { key: 'late', label: 'Terlambat', color: 'bg-chart-4' },
        { key: 'excused', label: 'Izin/sakit', color: 'bg-chart-1' },
        { key: 'absent', label: 'Alpa', color: 'bg-chart-5' },
    ] as const;
    const anyData = rows.some(
        (r) => r.present + r.late + r.excused + r.absent > 0,
    );

    return (
        <div className="space-y-3">
            {rows.map((row) => {
                const total = row.present + row.late + row.excused + row.absent;

                return (
                    <div
                        key={row.code}
                        className="grid grid-cols-[3rem_1fr_auto] items-center gap-3 text-sm"
                    >
                        <span className="font-medium">{row.code}</span>
                        <div className="flex h-3 overflow-hidden rounded-full bg-muted">
                            {segments.map((s) => (
                                <div
                                    key={s.key}
                                    className={s.color}
                                    style={{
                                        width:
                                            total > 0
                                                ? `${(row[s.key] / total) * 100}%`
                                                : 0,
                                    }}
                                />
                            ))}
                        </div>
                        <span className="text-xs text-muted-foreground tabular-nums">
                            {numberFormatter.format(total)} tercatat
                        </span>
                    </div>
                );
            })}
            <div className="flex flex-wrap gap-4 text-xs text-muted-foreground">
                {segments.map((s) => (
                    <Legend key={s.key} color={s.color} label={s.label} />
                ))}
                {!anyData && <span>Belum ada scan hari ini.</span>}
            </div>
        </div>
    );
}

function TopArrears({
    rows,
    billingOn,
}: {
    rows: YayasanDashboardData['top_arrears'];
    billingOn: boolean;
}) {
    if (!billingOn) {
        return (
            <p className="text-sm text-muted-foreground">
                Modul tagihan belum dinyalakan.
            </p>
        );
    }

    if (rows.length === 0) {
        return (
            <p className="text-sm text-muted-foreground">
                Tidak ada tagihan terbuka.
            </p>
        );
    }

    return (
        <ul className="divide-y divide-border">
            {rows.map((row) => (
                <li
                    key={row.invoice_id}
                    className="flex items-center justify-between gap-3 py-2.5"
                >
                    <div className="min-w-0">
                        <p className="truncate text-sm font-medium">
                            {row.description}
                        </p>
                        <p className="truncate text-xs text-muted-foreground">
                            {row.student_name} · {row.unit_code} · jatuh tempo{' '}
                            <DateText value={row.due_date} />
                        </p>
                    </div>
                    <div className="flex shrink-0 items-center gap-2">
                        <MoneyText
                            amount={row.remaining}
                            className="text-sm font-semibold"
                        />
                        {row.whatsapp && (
                            <Button
                                asChild
                                variant="outline"
                                size="icon"
                                aria-label={`Hubungi wali ${row.student_name} lewat WhatsApp`}
                            >
                                <a
                                    href={`https://wa.me/${row.whatsapp}`}
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    <MessageCircle
                                        className="size-4"
                                        strokeWidth={1.75}
                                    />
                                </a>
                            </Button>
                        )}
                    </div>
                </li>
            ))}
        </ul>
    );
}

function RecentCash({ rows }: { rows: YayasanDashboardData['recent_cash'] }) {
    if (rows.length === 0) {
        return (
            <p className="text-sm text-muted-foreground">
                Belum ada transaksi kas masuk.
            </p>
        );
    }

    return (
        <ul className="divide-y divide-border">
            {rows.map((row) => (
                <li
                    key={row.key}
                    className="flex items-center justify-between gap-3 py-2.5"
                >
                    <div className="min-w-0">
                        <p className="truncate text-sm font-medium">
                            {row.title}
                        </p>
                        <p className="truncate text-xs text-muted-foreground">
                            {row.kind} · {row.detail} ·{' '}
                            <DateText value={row.at} mode="datetime" />
                        </p>
                    </div>
                    <span className="shrink-0 text-sm font-semibold text-success tabular-nums">
                        +{formatRupiah(row.amount)}
                    </span>
                </li>
            ))}
        </ul>
    );
}
