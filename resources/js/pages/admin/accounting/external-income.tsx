import { Head, router, useForm } from '@inertiajs/react';
import { PlusCircle, RotateCcw, Search } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { useEffect, useRef, useState } from 'react';
import CashBookController from '@/actions/App/Http/Controllers/Admin/Accounting/CashBookController';
import ExternalIncomeController from '@/actions/App/Http/Controllers/Admin/Accounting/ExternalIncomeController';
import type { DataTableColumn } from '@/components/app/data-table';
import { DataTable } from '@/components/app/data-table';
import { DateInput } from '@/components/app/date-input';
import { DateText } from '@/components/app/date-text';
import { ExportButtons } from '@/components/app/export-buttons';
import { FormDialog } from '@/components/app/form-dialog';
import { MoneyText } from '@/components/app/money-text';
import { PageHeader } from '@/components/app/page-header';
import { PeriodFilter } from '@/components/app/period-filter';
import { StatusBadge } from '@/components/app/status-badge';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import StaffLayout from '@/layouts/staff-layout';
import { cn } from '@/lib/utils';
import type { PaginatedResource } from '@/types';
import type { CashRow } from './cash-book';

type Option = { value: string; label: string };
type Filters = {
    period: string;
    from: string | null;
    to: string | null;
    unit: number | null;
    account: number | null;
    search: string;
};

type Props = {
    rows: PaginatedResource<CashRow>;
    summary: { total: number; count: number; cancelled: number };
    filters: Filters;
    periodLabel: string;
    periodOptions: Option[];
    unitOptions: Option[];
    incomeAccounts: { id: number; unit_id: number; name: string }[];
    boxOptions: Option[];
    today: string;
};

const ALL = 'semua';

export default function ExternalIncome(props: Props) {
    const {
        rows,
        summary,
        filters,
        periodLabel,
        periodOptions,
        unitOptions,
        incomeAccounts,
        boxOptions,
        today,
    } = props;
    const [search, setSearch] = useState(filters.search);
    const [loading, setLoading] = useState(false);
    const timer = useRef<number | undefined>(undefined);
    const [open, setOpen] = useState(false);
    const [reversing, setReversing] = useState<CashRow | null>(null);
    const blank = {
        cash_account_id: '',
        counterparty: '',
        cash_box: 'bank',
        reference_no: '',
        amount: '',
        entry_date: today,
        description: '',
    };
    const form = useForm(blank);
    const reverseForm = useForm({ reason: '' });

    useEffect(() => () => window.clearTimeout(timer.current), []);

    const query = (
        changes: Partial<
            Record<keyof Filters | 'page', string | number | null>
        >,
        q = search,
    ) =>
        Object.fromEntries(
            Object.entries({ ...filters, search: q, ...changes }).filter(
                ([, v]) => v !== null && v !== undefined && String(v) !== '',
            ),
        ) as Record<string, string>;
    const visit = (
        changes: Partial<
            Record<keyof Filters | 'page', string | number | null>
        >,
        q = search,
    ) =>
        router.get(ExternalIncomeController.index.url(), query(changes, q), {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onStart: () => setLoading(true),
            onFinish: () => setLoading(false),
        });
    const exportQuery = new URLSearchParams(query({ page: null })).toString();

    const submit = (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        form.post(ExternalIncomeController.store.url(), {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    };
    const submitReverse = (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        if (reversing) {
            reverseForm.post(CashBookController.reverse.url(reversing.id), {
                preserveScroll: true,
                onSuccess: () => setReversing(null),
            });
        }
    };

    const columns: DataTableColumn<CashRow>[] = [
        {
            key: 'date',
            label: 'Tanggal',
            className: 'whitespace-nowrap',
            render: (r) => <DateText value={r.entry_date} />,
        },
        {
            key: 'account',
            label: 'Pos pendapatan',
            render: (r) => (
                <div>
                    <div className="font-medium">{r.account}</div>
                    {r.reference_no && (
                        <div className="text-xs text-muted-foreground tabular-nums">
                            Bukti {r.reference_no}
                        </div>
                    )}
                </div>
            ),
        },
        {
            key: 'desc',
            label: 'Rincian & pengirim',
            render: (r) => (
                <div className="max-w-80 space-y-0.5">
                    <div
                        className={cn(
                            r.reversed_reason &&
                                'text-muted-foreground line-through',
                        )}
                    >
                        {r.description}
                    </div>
                    <div className="text-xs text-muted-foreground">
                        {r.counterparty}
                    </div>
                    {r.reversed_reason && (
                        <StatusBadge variant="destructive">
                            Dikoreksi: {r.reversed_reason}
                        </StatusBadge>
                    )}
                </div>
            ),
        },
        {
            key: 'box',
            label: 'Rekening',
            className: 'hidden @3xl:table-cell',
            render: (r) => (r.cash_box === 'bank' ? 'Bank unit' : 'Kas tunai'),
        },
        {
            key: 'amount',
            label: 'Nominal masuk',
            align: 'right',
            render: (r) => (
                <MoneyText
                    amount={r.amount}
                    className={cn(
                        'font-semibold',
                        r.reversed_reason &&
                            'text-muted-foreground line-through',
                    )}
                />
            ),
        },
        {
            key: 'actions',
            label: 'Aksi',
            align: 'center',
            render: (r) =>
                r.can_reverse ? (
                    <Button
                        size="sm"
                        variant="outline"
                        onClick={() => {
                            reverseForm.reset();
                            reverseForm.clearErrors();
                            setReversing(r);
                        }}
                    >
                        <RotateCcw className="size-3.5" />
                        Koreksi
                    </Button>
                ) : null,
        },
    ];

    const addButton = (
        <Button
            type="button"
            onClick={() => {
                form.clearErrors();
                form.setData({ ...blank });
                setOpen(true);
            }}
        >
            <PlusCircle className="size-4" strokeWidth={1.75} />
            Catat pemasukan
        </Button>
    );

    return (
        <StaffLayout
            breadcrumbs={[
                { title: 'Akuntansi & Kas', href: '#' },
                {
                    title: 'Pemasukan Lain',
                    href: ExternalIncomeController.index.url(),
                },
            ]}
        >
            <Head title="Pemasukan Lain" />

            <div className="@container space-y-4">
                <PageHeader
                    title="Pemasukan Lain"
                    description="Pendapatan di luar pembayaran siswa: hibah, sewa, CSR, bantuan. Setiap pemasukan langsung tercatat di Buku Kas Umum dan menjadi sumber dana."
                    actions={
                        <>
                            <ExportButtons
                                pdfHref={`${ExternalIncomeController.exportPdf.url()}?${exportQuery}`}
                                excelHref={`${ExternalIncomeController.exportExcel.url()}?${exportQuery}`}
                            />
                            {addButton}
                        </>
                    }
                />

                <section
                    aria-label={`Ringkasan ${periodLabel}`}
                    className="grid grid-cols-2 gap-3 @3xl:grid-cols-3"
                >
                    <div className="rounded-xl border border-border bg-card p-4 shadow-xs">
                        <p className="text-xs text-muted-foreground">
                            Total pemasukan
                        </p>
                        <MoneyText
                            amount={summary.total}
                            className="mt-1 block text-xl font-semibold text-success"
                        />
                        <p className="mt-0.5 text-xs text-muted-foreground">
                            {periodLabel}
                        </p>
                    </div>
                    <div className="rounded-xl border border-border bg-card p-4 shadow-xs">
                        <p className="text-xs text-muted-foreground">
                            Jumlah transaksi
                        </p>
                        <p className="mt-1 text-xl font-semibold tabular-nums">
                            {summary.count}
                        </p>
                    </div>
                    <div className="rounded-xl border border-border bg-card p-4 shadow-xs">
                        <p className="text-xs text-muted-foreground">
                            Dikoreksi
                        </p>
                        <MoneyText
                            amount={summary.cancelled}
                            className={cn(
                                'mt-1 block text-xl font-semibold',
                                summary.cancelled > 0 && 'text-destructive',
                            )}
                        />
                    </div>
                </section>

                <div className="flex flex-wrap gap-2">
                    <div className="relative w-full @3xl:w-60">
                        <Search
                            className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                            strokeWidth={1.75}
                        />
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
                            placeholder="Cari uraian atau pengirim"
                            aria-label="Cari uraian atau pengirim"
                            className="pl-9"
                        />
                    </div>
                    <PeriodFilter
                        value={filters}
                        options={periodOptions}
                        onChange={(c) => visit({ ...c, page: 1 })}
                    />
                    <Select
                        value={
                            filters.unit === null ? ALL : String(filters.unit)
                        }
                        onValueChange={(v) =>
                            visit({
                                unit: v === ALL ? null : v,
                                account: null,
                                page: 1,
                            })
                        }
                    >
                        <SelectTrigger
                            className="w-full @lg:w-52"
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
                            filters.account === null
                                ? ALL
                                : String(filters.account)
                        }
                        onValueChange={(v) =>
                            visit({ account: v === ALL ? null : v, page: 1 })
                        }
                    >
                        <SelectTrigger
                            className="w-full @lg:w-56"
                            aria-label="Saring pos pendapatan"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL}>
                                Semua pos pendapatan
                            </SelectItem>
                            {incomeAccounts.map((a) => (
                                <SelectItem key={a.id} value={String(a.id)}>
                                    {a.name}
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
                        title: 'Belum ada pemasukan',
                        description:
                            incomeAccounts.length === 0
                                ? 'Buat dulu pos pendapatan di Kategori Akun, mis. "Hibah" atau "Sewa gedung".'
                                : 'Tidak ada pemasukan pada periode dan saringan ini.',
                        action:
                            incomeAccounts.length === 0 ? undefined : addButton,
                    }}
                />
            </div>

            <FormDialog
                open={open}
                onOpenChange={setOpen}
                title="Catat pemasukan lain"
                onSubmit={submit}
                processing={form.processing}
                submitLabel="Simpan pemasukan"
            >
                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="inc-account">Pos pendapatan</Label>
                        <Select
                            value={form.data.cash_account_id || undefined}
                            onValueChange={(v) =>
                                form.setData('cash_account_id', v)
                            }
                        >
                            <SelectTrigger id="inc-account">
                                <SelectValue placeholder="Pilih pos pendapatan" />
                            </SelectTrigger>
                            <SelectContent>
                                {incomeAccounts.map((a) => (
                                    <SelectItem key={a.id} value={String(a.id)}>
                                        {a.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={form.errors.cash_account_id} />
                    </div>
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="inc-party">Pengirim dana / mitra</Label>
                        <Input
                            id="inc-party"
                            value={form.data.counterparty}
                            onChange={(e) =>
                                form.setData('counterparty', e.target.value)
                            }
                            maxLength={120}
                            required
                        />
                        <InputError message={form.errors.counterparty} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="inc-amount">Nominal masuk (Rp)</Label>
                        <Input
                            id="inc-amount"
                            inputMode="numeric"
                            value={form.data.amount}
                            onChange={(e) =>
                                form.setData(
                                    'amount',
                                    e.target.value
                                        .replace(/\D/g, '')
                                        .slice(0, 11),
                                )
                            }
                            className="tabular-nums"
                            required
                        />
                        <InputError message={form.errors.amount} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="inc-date">Tanggal terima</Label>
                        <DateInput
                            id="inc-date"
                            value={form.data.entry_date}
                            onChange={(e) =>
                                form.setData('entry_date', e.target.value)
                            }
                            required
                        />
                        <InputError message={form.errors.entry_date} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="inc-box">Rekening penampung</Label>
                        <Select
                            value={form.data.cash_box}
                            onValueChange={(v) => form.setData('cash_box', v)}
                        >
                            <SelectTrigger id="inc-box">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {boxOptions.map((o) => (
                                    <SelectItem key={o.value} value={o.value}>
                                        {o.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="inc-ref">No. ref / bukti</Label>
                        <Input
                            id="inc-ref"
                            value={form.data.reference_no}
                            onChange={(e) =>
                                form.setData('reference_no', e.target.value)
                            }
                            maxLength={50}
                        />
                    </div>
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="inc-desc">
                            Uraian / keterangan sumber pendapatan
                        </Label>
                        <Input
                            id="inc-desc"
                            value={form.data.description}
                            onChange={(e) =>
                                form.setData('description', e.target.value)
                            }
                            maxLength={255}
                            required
                        />
                        <InputError message={form.errors.description} />
                    </div>
                </div>
            </FormDialog>

            <FormDialog
                open={reversing !== null}
                onOpenChange={(o) => !o && setReversing(null)}
                title="Koreksi pemasukan"
                description="Dibuat entri pembalik bertanggal hari ini. Koreksi ditolak bila dana dari pemasukan ini sudah dibelanjakan."
                onSubmit={submitReverse}
                processing={reverseForm.processing}
                submitLabel="Buat entri koreksi"
            >
                <div className="grid gap-2">
                    <Label htmlFor="inc-reason">Alasan koreksi</Label>
                    <Input
                        id="inc-reason"
                        value={reverseForm.data.reason}
                        onChange={(e) =>
                            reverseForm.setData('reason', e.target.value)
                        }
                        required
                    />
                    <InputError message={reverseForm.errors.reason} />
                </div>
            </FormDialog>
        </StaffLayout>
    );
}

ExternalIncome.layout = (page: ReactNode) => page;
