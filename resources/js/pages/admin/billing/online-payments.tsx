import { Head, router } from '@inertiajs/react';
import { Eye, RefreshCw, Search } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { useState } from 'react';
import OnlinePaymentController from '@/actions/App/Http/Controllers/Admin/OnlinePaymentController';
import type { DataTableColumn } from '@/components/app/data-table';
import { DataTable } from '@/components/app/data-table';
import { DateInput } from '@/components/app/date-input';
import { DateText } from '@/components/app/date-text';
import { MoneyText } from '@/components/app/money-text';
import { PageHeader } from '@/components/app/page-header';
import type { StatusBadgeVariant } from '@/components/app/status-badge';
import { StatusBadge } from '@/components/app/status-badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Spinner } from '@/components/ui/spinner';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import StaffLayout from '@/layouts/staff-layout';
import { cn } from '@/lib/utils';
import type { PaginatedResource } from '@/types';

type Tab = 'review' | 'pending' | 'paid' | 'closed';

type OrderRow = {
    order_id: string;
    purpose_label: string;
    student_name: string;
    student_nis: string;
    summary: string;
    gross_amount: number;
    status: 'pending' | 'paid' | 'expired' | 'failed' | 'cancelled';
    status_label: string;
    review_reason: string | null;
    review_label: string | null;
    created_at: string;
};

type OrderDetail = OrderRow & {
    review_reason_raw: string | null;
    review_reason_label: string | null;
    reviewed_at: string | null;
    reviewer_name: string | null;
    payment_type: string | null;
    paid_at: string | null;
    expires_at: string;
    receipt_number: string | null;
    items: { id: number; description: string; amount: number }[];
    midtrans: Record<string, string | number | null>;
    notifications: {
        id: number;
        transaction_status: string | null;
        signature_valid: boolean;
        process_result: string | null;
        received_at: string;
    }[];
};

type Filters = {
    tab: Tab;
    q: string;
    from: string | null;
    to: string | null;
};

type Props = {
    orders: PaginatedResource<OrderRow>;
    counts: Record<Tab, number>;
    filters: Filters;
    selected: OrderDetail | null;
};

type BusyKind = 'check' | 'review' | 'reconcile' | null;

const TABS: { key: Tab; label: string }[] = [
    { key: 'review', label: 'Perlu ditinjau' },
    { key: 'pending', label: 'Menunggu bayar' },
    { key: 'paid', label: 'Berhasil' },
    { key: 'closed', label: 'Kedaluwarsa/Gagal' },
];

const MIDTRANS_LABELS: Record<string, string> = {
    transaction_status: 'Status Midtrans',
    fraud_status: 'Fraud status',
    payment_type: 'Metode',
    gross_amount: 'Nominal dari Midtrans',
    transaction_time: 'Waktu transaksi',
    settlement_time: 'Waktu settlement',
    expiry_time: 'Batas bayar',
    transaction_id: 'ID transaksi',
    status_message: 'Pesan',
};

function statusVariant(row: OrderRow): StatusBadgeVariant {
    if (row.review_reason === 'amount_mismatch') {
        return 'destructive';
    }

    if (row.review_reason !== null) {
        return 'attention';
    }

    switch (row.status) {
        case 'paid':
            return 'success';
        case 'pending':
            return 'warning';
        default:
            return 'muted';
    }
}

export default function OnlinePaymentsIndex({
    orders,
    counts,
    filters,
    selected,
}: Props) {
    const [loading, setLoading] = useState(false);
    const [busy, setBusy] = useState<BusyKind>(null);
    const [search, setSearch] = useState(filters.q);
    const [from, setFrom] = useState(filters.from ?? '');
    const [to, setTo] = useState(filters.to ?? '');

    function visit(next: Partial<Filters> & { page?: number; order?: string }) {
        const merged = { ...filters, ...next };
        router.get(
            OnlinePaymentController.index.url({
                query: {
                    tab: merged.tab,
                    q: merged.q || undefined,
                    from: merged.from || undefined,
                    to: merged.to || undefined,
                    page: next.page && next.page > 1 ? next.page : undefined,
                    order: next.order,
                },
            }),
            {},
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                onStart: () => setLoading(true),
                onFinish: () => setLoading(false),
            },
        );
    }

    function applyFilters(e: FormEvent<HTMLFormElement>) {
        e.preventDefault();
        visit({ q: search.trim(), from: from || null, to: to || null });
    }

    function post(url: string, kind: Exclude<BusyKind, null>) {
        router.post(
            url,
            {},
            {
                preserveScroll: true,
                preserveState: true,
                onStart: () => setBusy(kind),
                onFinish: () => setBusy(null),
            },
        );
    }

    const columns: DataTableColumn<OrderRow>[] = [
        {
            key: 'order',
            label: 'Order',
            render: (row) => (
                <div>
                    <div className="font-mono text-xs break-all">
                        {row.order_id}
                    </div>
                    <div className="text-xs text-muted-foreground">
                        {row.purpose_label}
                    </div>
                </div>
            ),
        },
        {
            key: 'student',
            label: 'Siswa',
            render: (row) => (
                <div>
                    <div className="font-medium">{row.student_name}</div>
                    <div className="line-clamp-1 text-xs text-muted-foreground">
                        {row.summary}
                    </div>
                </div>
            ),
        },
        {
            key: 'amount',
            label: 'Nominal',
            align: 'right',
            render: (row) => (
                <MoneyText amount={row.gross_amount} className="font-medium" />
            ),
        },
        {
            key: 'created_at',
            label: 'Waktu',
            render: (row) => (
                <DateText value={row.created_at} mode="datetime" />
            ),
        },
        {
            key: 'status',
            label: 'Masalah/Status',
            align: 'center',
            render: (row) => (
                <StatusBadge variant={statusVariant(row)}>
                    {row.review_label ?? row.status_label}
                </StatusBadge>
            ),
        },
        {
            key: 'actions',
            label: 'Aksi',
            align: 'center',
            render: (row) => (
                <Tooltip>
                    <TooltipTrigger asChild>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            aria-label={`Lihat detail ${row.order_id}`}
                            onClick={() => visit({ order: row.order_id })}
                        >
                            <Eye className="size-4" strokeWidth={1.75} />
                        </Button>
                    </TooltipTrigger>
                    <TooltipContent>Lihat detail</TooltipContent>
                </Tooltip>
            ),
        },
    ];

    return (
        <StaffLayout
            breadcrumbs={[
                {
                    title: 'Keuangan Siswa',
                    href: OnlinePaymentController.index.url(),
                },
                {
                    title: 'Pembayaran online',
                    href: OnlinePaymentController.index.url(),
                },
            ]}
        >
            <Head title="Pembayaran online" />

            <div className="space-y-6">
                <PageHeader
                    title="Pembayaran online"
                    description="Status hanya berubah dari webhook Midtrans yang lolos verifikasi."
                    actions={
                        <Button
                            type="button"
                            onClick={() =>
                                post(
                                    OnlinePaymentController.reconcile.url(),
                                    'reconcile',
                                )
                            }
                            disabled={busy !== null}
                        >
                            {busy === 'reconcile' ? (
                                <Spinner />
                            ) : (
                                <RefreshCw
                                    className="size-4"
                                    strokeWidth={1.75}
                                />
                            )}
                            Cocokkan ulang hari ini
                        </Button>
                    }
                />

                <nav
                    aria-label="Kelompok pembayaran online"
                    className="flex gap-1 overflow-x-auto overflow-y-hidden border-b border-border"
                >
                    {TABS.map((tab) => {
                        const active = filters.tab === tab.key;

                        return (
                            <button
                                key={tab.key}
                                type="button"
                                aria-current={active ? 'page' : undefined}
                                onClick={() => visit({ tab: tab.key })}
                                className={cn(
                                    '-mb-px min-h-11 shrink-0 border-b-2 px-3 text-sm whitespace-nowrap transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                                    active
                                        ? 'border-primary font-semibold text-foreground'
                                        : 'border-transparent text-muted-foreground hover:text-foreground',
                                )}
                            >
                                {tab.label}{' '}
                                <span className="tabular-nums">
                                    ({counts[tab.key]})
                                </span>
                            </button>
                        );
                    })}
                </nav>

                <form
                    onSubmit={applyFilters}
                    className="grid gap-3 sm:grid-cols-[1fr_auto_auto_auto] sm:items-end"
                >
                    <div className="space-y-1.5">
                        <Label htmlFor="op-search">Cari</Label>
                        <Input
                            id="op-search"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Order atau nama siswa"
                        />
                    </div>
                    <div className="space-y-1.5">
                        <Label htmlFor="op-from">Dari tanggal</Label>
                        <DateInput
                            id="op-from"
                            value={from}
                            onChange={(e) => setFrom(e.target.value)}
                        />
                    </div>
                    <div className="space-y-1.5">
                        <Label htmlFor="op-to">Sampai tanggal</Label>
                        <DateInput
                            id="op-to"
                            value={to}
                            onChange={(e) => setTo(e.target.value)}
                        />
                    </div>
                    <Button type="submit" variant="outline">
                        <Search className="size-4" strokeWidth={1.75} />
                        Terapkan
                    </Button>
                </form>

                <DataTable
                    columns={columns}
                    data={orders.data}
                    rowKey={(row) => row.order_id}
                    loading={loading}
                    pagination={{
                        currentPage: orders.meta.current_page,
                        lastPage: orders.meta.last_page,
                        perPage: orders.meta.per_page,
                        total: orders.meta.total,
                        from: orders.meta.from,
                        to: orders.meta.to,
                    }}
                    onPageChange={(page) => visit({ page })}
                    empty={{
                        title:
                            filters.tab === 'review'
                                ? 'Tidak ada pembayaran yang perlu ditinjau'
                                : 'Belum ada pembayaran online',
                        description:
                            filters.tab === 'review'
                                ? 'Nominal tidak cocok, refund, dan kelebihan bayar akan muncul di sini.'
                                : 'Order yang dibuat wali lewat Midtrans akan tampil di daftar ini.',
                    }}
                />
            </div>

            <Sheet
                open={selected !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        visit({});
                    }
                }}
            >
                <SheetContent className="w-full overflow-y-auto sm:max-w-md">
                    {selected && (
                        <OrderSheet
                            order={selected}
                            busy={busy}
                            onCheck={() =>
                                post(
                                    OnlinePaymentController.check.url(
                                        selected.order_id,
                                    ),
                                    'check',
                                )
                            }
                            onReview={() =>
                                post(
                                    OnlinePaymentController.review.url(
                                        selected.order_id,
                                    ),
                                    'review',
                                )
                            }
                        />
                    )}
                </SheetContent>
            </Sheet>
        </StaffLayout>
    );
}

OnlinePaymentsIndex.layout = (page: ReactNode) => page;

function OrderSheet({
    order,
    busy,
    onCheck,
    onReview,
}: {
    order: OrderDetail;
    busy: BusyKind;
    onCheck: () => void;
    onReview: () => void;
}) {
    const awaitingReview =
        order.review_reason_raw !== null && order.reviewed_at === null;
    const midtransEntries = Object.entries(order.midtrans).filter(
        ([key]) => key in MIDTRANS_LABELS,
    );

    return (
        <>
            <SheetHeader>
                <SheetTitle className="font-mono text-base break-all">
                    {order.order_id}
                </SheetTitle>
                <SheetDescription asChild>
                    <div className="flex flex-wrap gap-2">
                        <StatusBadge variant={statusVariant(order)}>
                            {order.review_label ?? order.status_label}
                        </StatusBadge>
                        {order.review_label && (
                            <StatusBadge variant="muted">
                                {order.status_label}
                            </StatusBadge>
                        )}
                    </div>
                </SheetDescription>
            </SheetHeader>

            <div className="space-y-5 px-4 text-sm">
                <dl className="space-y-3">
                    <DetailRow label="Siswa">
                        {order.student_name} · {order.student_nis}
                    </DetailRow>
                    <DetailRow label={order.purpose_label}>
                        <ul className="space-y-1">
                            {order.items.map((item) => (
                                <li
                                    key={item.id}
                                    className="flex justify-between gap-3"
                                >
                                    <span>{item.description}</span>
                                    <MoneyText amount={item.amount} />
                                </li>
                            ))}
                        </ul>
                    </DetailRow>
                    <DetailRow label="Nominal order">
                        <MoneyText
                            amount={order.gross_amount}
                            className="font-semibold"
                        />
                    </DetailRow>
                    {order.receipt_number && (
                        <DetailRow label="Kuitansi">
                            {order.receipt_number}
                        </DetailRow>
                    )}
                    <DetailRow label="Batas bayar">
                        <DateText value={order.expires_at} mode="datetime" />
                    </DetailRow>
                    {order.reviewed_at && (
                        <DetailRow label="Ditinjau">
                            <DateText
                                value={order.reviewed_at}
                                mode="datetime"
                            />{' '}
                            oleh {order.reviewer_name ?? '—'}
                        </DetailRow>
                    )}
                </dl>

                <section className="space-y-2">
                    <h3 className="font-semibold">Status terakhir Midtrans</h3>
                    {midtransEntries.length === 0 ? (
                        <p className="text-muted-foreground">
                            Belum pernah dicek ke Midtrans.
                        </p>
                    ) : (
                        <dl className="space-y-2">
                            {midtransEntries.map(([key, value]) => (
                                <DetailRow
                                    key={key}
                                    label={MIDTRANS_LABELS[key]}
                                >
                                    <span className="break-all">
                                        {String(value ?? '—')}
                                    </span>
                                </DetailRow>
                            ))}
                        </dl>
                    )}
                </section>

                <section className="space-y-2">
                    <h3 className="font-semibold">Riwayat webhook</h3>
                    {order.notifications.length === 0 ? (
                        <p className="text-muted-foreground">
                            Belum ada notifikasi dari Midtrans.
                        </p>
                    ) : (
                        <ul className="space-y-1 text-muted-foreground">
                            {order.notifications.map((n) => (
                                <li key={n.id}>
                                    <DateText
                                        value={n.received_at}
                                        mode="datetime"
                                    />{' '}
                                    · {n.transaction_status ?? '—'} ·{' '}
                                    {n.signature_valid
                                        ? 'signature valid'
                                        : 'signature ditolak'}
                                    {n.process_result
                                        ? ` · ${n.process_result}`
                                        : ''}
                                </li>
                            ))}
                        </ul>
                    )}
                </section>

                {order.review_reason_raw === 'amount_mismatch' && (
                    <div className="rounded-md border border-border bg-muted p-3">
                        <p className="font-medium">
                            Tagihan tidak ditandai lunas.
                        </p>
                        <p className="text-muted-foreground">
                            Hubungi wali atau cek dasbor Midtrans sebelum
                            memutuskan.
                        </p>
                    </div>
                )}
            </div>

            <SheetFooter className="flex-row gap-2">
                {awaitingReview && (
                    <Button
                        type="button"
                        variant="outline"
                        className="flex-1"
                        onClick={onReview}
                        disabled={busy !== null}
                    >
                        {busy === 'review' && <Spinner />}
                        Tandai sudah ditinjau
                    </Button>
                )}
                <Button
                    type="button"
                    className="flex-1"
                    onClick={onCheck}
                    disabled={busy !== null}
                >
                    {busy === 'check' && <Spinner />}
                    Cek status
                </Button>
            </SheetFooter>
        </>
    );
}

function DetailRow({
    label,
    children,
}: {
    label: string;
    children: ReactNode;
}) {
    return (
        <div className="space-y-0.5">
            <dt className="text-xs text-muted-foreground">{label}</dt>
            <dd>{children}</dd>
        </div>
    );
}
