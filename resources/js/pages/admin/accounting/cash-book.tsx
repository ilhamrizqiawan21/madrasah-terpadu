import { Head, router, useForm } from '@inertiajs/react';
import { MinusCircle, RotateCcw, Search } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { useEffect, useRef, useState } from 'react';
import CashBookController from '@/actions/App/Http/Controllers/Admin/Accounting/CashBookController';
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

type Option = { value: string; label: string };
export type CashRow = {
    id: number;
    entry_date: string;
    reference_no: string | null;
    account: string;
    funding: string | null;
    description: string;
    counterparty: string | null;
    cash_box: 'cash' | 'bank';
    direction: 'in' | 'out';
    amount: number;
    running: number | null;
    is_reversal: boolean;
    reversal_reason: string | null;
    reversed_reason: string | null;
    can_reverse: boolean;
};
type Filters = {
    period: string;
    from: string | null;
    to: string | null;
    unit: number | null;
    direction: 'in' | 'out' | null;
    box: 'cash' | 'bank' | null;
    search: string;
};

type Props = {
    rows: PaginatedResource<CashRow>;
    summary: { opening: number; in: number; out: number; closing: number };
    showRunning: boolean;
    filters: Filters;
    periodLabel: string;
    periodOptions: Option[];
    unitOptions: Option[];
    boxOptions: Option[];
    expenseAccounts: { id: number; name: string; allowed: number[] }[];
    fundingAccounts: { id: number; name: string; balance: number }[];
    today: string;
};

const ALL = 'semua';

export default function CashBook(props: Props) {
    const {
        rows,
        summary,
        showRunning,
        filters,
        periodLabel,
        periodOptions,
        unitOptions,
        boxOptions,
        expenseAccounts,
        fundingAccounts,
        today,
    } = props;
    const [search, setSearch] = useState(filters.search);
    const [loading, setLoading] = useState(false);
    const timer = useRef<number | undefined>(undefined);
    const [expenseOpen, setExpenseOpen] = useState(false);
    const [reversing, setReversing] = useState<CashRow | null>(null);

    const expense = useForm({
        unit_id: String(filters.unit ?? ''),
        cash_account_id: '',
        funding_account_id: '',
        cash_box: 'cash',
        reference_no: '',
        counterparty: '',
        amount: '',
        entry_date: today,
        description: '',
    });
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
        router.get(CashBookController.index.url(), query(changes, q), {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onStart: () => setLoading(true),
            onFinish: () => setLoading(false),
        });
    const exportQuery = new URLSearchParams(query({ page: null })).toString();

    const selectedExpense = expenseAccounts.find(
        (a) => String(a.id) === expense.data.cash_account_id,
    );
    const fundingChoices = fundingAccounts.filter(
        (f) =>
            !selectedExpense ||
            selectedExpense.allowed.length === 0 ||
            selectedExpense.allowed.includes(f.id),
    );
    const selectedFunding = fundingAccounts.find(
        (f) => String(f.id) === expense.data.funding_account_id,
    );
    const amount = Number.parseInt(expense.data.amount || '0', 10) || 0;
    const overBudget =
        selectedFunding !== undefined && amount > selectedFunding.balance;

    const openExpense = () => {
        expense.clearErrors();
        expense.setData({
            unit_id: String(filters.unit ?? ''),
            cash_account_id: '',
            funding_account_id: '',
            cash_box: 'cash',
            reference_no: '',
            counterparty: '',
            amount: '',
            entry_date: today,
            description: '',
        });
        setExpenseOpen(true);
    };

    const submitExpense = (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        expense.post(CashBookController.storeExpense.url(), {
            preserveScroll: true,
            onSuccess: () => setExpenseOpen(false),
        });
    };

    const submitReverse = (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        if (!reversing) {
            return;
        }
        reverseForm.post(CashBookController.reverse.url(reversing.id), {
            preserveScroll: true,
            onSuccess: () => setReversing(null),
        });
    };

    const columns: DataTableColumn<CashRow>[] = [
        {
            key: 'date',
            label: 'Tanggal',
            className: 'whitespace-nowrap',
            render: (r) => <DateText value={r.entry_date} />,
        },
        {
            key: 'ref',
            label: 'No. bukti',
            className: 'hidden whitespace-nowrap tabular-nums @3xl:table-cell',
            render: (r) => r.reference_no ?? '—',
        },
        {
            key: 'account',
            label: 'Pos akun',
            render: (r) => (
                <div>
                    <div className="font-medium">{r.account}</div>
                    {r.funding && (
                        <div className="text-xs text-muted-foreground">
                            Dana: {r.funding}
                        </div>
                    )}
                </div>
            ),
        },
        {
            key: 'desc',
            label: 'Uraian',
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
                        {[
                            r.counterparty,
                            r.cash_box === 'bank' ? 'Bank' : 'Tunai',
                        ]
                            .filter(Boolean)
                            .join(' · ')}
                    </div>
                    {r.is_reversal && (
                        <StatusBadge variant="warning">
                            Koreksi: {r.reversal_reason}
                        </StatusBadge>
                    )}
                    {r.reversed_reason && (
                        <StatusBadge variant="destructive">
                            Dikoreksi: {r.reversed_reason}
                        </StatusBadge>
                    )}
                </div>
            ),
        },
        {
            key: 'in',
            label: 'Masuk',
            align: 'right',
            render: (r) =>
                r.direction === 'in' ? (
                    <MoneyText amount={r.amount} className="text-success" />
                ) : (
                    ''
                ),
        },
        {
            key: 'out',
            label: 'Keluar',
            align: 'right',
            render: (r) =>
                r.direction === 'out' ? (
                    <MoneyText amount={r.amount} className="text-destructive" />
                ) : (
                    ''
                ),
        },
        ...(showRunning
            ? [
                  {
                      key: 'running',
                      label: 'Saldo',
                      align: 'right' as const,
                      className: 'hidden @5xl:table-cell',
                      render: (r: CashRow) =>
                          r.running === null ? (
                              ''
                          ) : (
                              <MoneyText
                                  amount={r.running}
                                  className="font-medium"
                              />
                          ),
                  },
              ]
            : []),
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

    const tiles = [
        {
            label: 'Saldo awal periode',
            value: summary.opening,
            hint: 'Sisa kas sebelum periode',
        },
        {
            label: 'Total kas masuk',
            value: summary.in,
            hint: 'Pendapatan & koreksi',
            tone: 'text-success',
        },
        {
            label: 'Total kas keluar',
            value: summary.out,
            hint: 'Belanja & koreksi',
            tone: 'text-destructive',
        },
        {
            label: 'Saldo akhir',
            value: summary.closing,
            hint: 'Kas tersedia',
            tone: summary.closing < 0 ? 'text-destructive' : undefined,
        },
    ];

    return (
        <StaffLayout
            breadcrumbs={[
                { title: 'Akuntansi & Kas', href: '#' },
                {
                    title: 'Buku Kas Umum',
                    href: CashBookController.index.url(),
                },
            ]}
        >
            <Head title="Buku Kas Umum" />

            <div className="@container space-y-4">
                <PageHeader
                    title="Buku Kas Umum"
                    description="Jurnal kas masuk dan keluar dengan saldo berjalan. Entri tidak bisa diubah atau dihapus; kesalahan dikoreksi dengan entri pembalik."
                    actions={
                        <>
                            <ExportButtons
                                pdfHref={`${CashBookController.exportPdf.url()}?${exportQuery}`}
                                excelHref={`${CashBookController.exportExcel.url()}?${exportQuery}`}
                            />
                            <Button
                                type="button"
                                onClick={openExpense}
                                disabled={filters.unit === null}
                                title={
                                    filters.unit === null
                                        ? 'Pilih unit dulu'
                                        : undefined
                                }
                            >
                                <MinusCircle
                                    className="size-4"
                                    strokeWidth={1.75}
                                />
                                Catat pengeluaran
                            </Button>
                        </>
                    }
                />

                <section
                    aria-label={`Ringkasan ${periodLabel}`}
                    className="grid grid-cols-2 gap-3 @3xl:grid-cols-4"
                >
                    {tiles.map((t) => (
                        <div
                            key={t.label}
                            className="rounded-xl border border-border bg-card p-4 shadow-xs"
                        >
                            <p className="text-xs text-muted-foreground">
                                {t.label}
                            </p>
                            <MoneyText
                                amount={t.value}
                                className={cn(
                                    'mt-1 block text-xl font-semibold',
                                    t.tone,
                                )}
                            />
                            <p className="mt-0.5 text-xs text-muted-foreground">
                                {t.hint}
                            </p>
                        </div>
                    ))}
                </section>
                <p className="text-xs text-muted-foreground">
                    {filters.unit === null
                        ? 'Jurnal gabungan semua unit · '
                        : ''}
                    Periode: {periodLabel}
                </p>

                {filters.unit !== null && fundingAccounts.length > 0 && (
                    <section
                        aria-label="Saldo per sumber dana"
                        className="rounded-xl border border-border bg-card p-4 shadow-xs"
                    >
                        <h2 className="text-sm font-semibold">
                            Saldo per sumber dana (sepanjang waktu)
                        </h2>
                        <ul className="mt-2 grid gap-2 @lg:grid-cols-2 @4xl:grid-cols-3">
                            {fundingAccounts.map((f) => (
                                <li
                                    key={f.id}
                                    className="flex items-center justify-between gap-3 rounded-md bg-muted/40 px-3 py-2 text-sm"
                                >
                                    <span className="truncate">{f.name}</span>
                                    <MoneyText
                                        amount={f.balance}
                                        className={cn(
                                            'font-medium',
                                            f.balance <= 0 &&
                                                'text-muted-foreground',
                                        )}
                                    />
                                </li>
                            ))}
                        </ul>
                    </section>
                )}

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
                            placeholder="Cari uraian, bukti, toko"
                            aria-label="Cari uraian, nomor bukti, atau toko"
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
                            visit({ unit: v === ALL ? null : v, page: 1 })
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
                        value={filters.direction ?? ALL}
                        onValueChange={(v) =>
                            visit({ direction: v === ALL ? null : v, page: 1 })
                        }
                    >
                        <SelectTrigger
                            className="w-full @lg:w-40"
                            aria-label="Saring arus kas"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL}>Masuk & keluar</SelectItem>
                            <SelectItem value="in">Kas masuk</SelectItem>
                            <SelectItem value="out">Kas keluar</SelectItem>
                        </SelectContent>
                    </Select>
                    <Select
                        value={filters.box ?? ALL}
                        onValueChange={(v) =>
                            visit({ box: v === ALL ? null : v, page: 1 })
                        }
                    >
                        <SelectTrigger
                            className="w-full @lg:w-44"
                            aria-label="Saring kas"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL}>Tunai & bank</SelectItem>
                            {boxOptions.map((o) => (
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
                        title: 'Belum ada mutasi kas',
                        description:
                            'Tidak ada kas masuk atau keluar pada periode dan saringan ini.',
                    }}
                />
            </div>

            <FormDialog
                open={expenseOpen}
                onOpenChange={setExpenseOpen}
                title="Catat pengeluaran kas"
                description={`Unit ${unitOptions.find((u) => u.value === expense.data.unit_id)?.label ?? ''}. Pengeluaran harus dibiayai sumber dana yang saldonya cukup.`}
                onSubmit={submitExpense}
                processing={expense.processing}
                submitLabel="Simpan pengeluaran"
            >
                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="ex-account">Pos pengeluaran</Label>
                        <Select
                            value={expense.data.cash_account_id || undefined}
                            onValueChange={(v) =>
                                expense.setData((d) => ({
                                    ...d,
                                    cash_account_id: v,
                                    funding_account_id: '',
                                }))
                            }
                        >
                            <SelectTrigger id="ex-account">
                                <SelectValue
                                    placeholder={
                                        expenseAccounts.length === 0
                                            ? 'Belum ada pos pengeluaran di unit ini'
                                            : 'Pilih pos'
                                    }
                                />
                            </SelectTrigger>
                            <SelectContent>
                                {expenseAccounts.map((a) => (
                                    <SelectItem key={a.id} value={String(a.id)}>
                                        {a.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={expense.errors.cash_account_id} />
                    </div>
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="ex-funding">
                            Sumber dana pembiayaan
                        </Label>
                        <Select
                            value={expense.data.funding_account_id || undefined}
                            onValueChange={(v) =>
                                expense.setData('funding_account_id', v)
                            }
                        >
                            <SelectTrigger id="ex-funding">
                                <SelectValue placeholder="Pilih sumber dana" />
                            </SelectTrigger>
                            <SelectContent>
                                {fundingChoices.map((f) => (
                                    <SelectItem key={f.id} value={String(f.id)}>
                                        {f.name} · sisa{' '}
                                        {f.balance.toLocaleString('id-ID')}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError
                            message={expense.errors.funding_account_id}
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="ex-amount">Nominal (Rp)</Label>
                        <Input
                            id="ex-amount"
                            inputMode="numeric"
                            value={expense.data.amount}
                            onChange={(e) =>
                                expense.setData(
                                    'amount',
                                    e.target.value
                                        .replace(/\D/g, '')
                                        .slice(0, 11),
                                )
                            }
                            className="tabular-nums"
                            required
                        />
                        {overBudget && (
                            <p className="text-xs text-destructive">
                                Melebihi saldo sumber dana (
                                {selectedFunding?.balance.toLocaleString(
                                    'id-ID',
                                )}
                                ).
                            </p>
                        )}
                        <InputError message={expense.errors.amount} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="ex-date">Tanggal transaksi</Label>
                        <DateInput
                            id="ex-date"
                            value={expense.data.entry_date}
                            onChange={(e) =>
                                expense.setData('entry_date', e.target.value)
                            }
                            required
                        />
                        <InputError message={expense.errors.entry_date} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="ex-box">Dibayar dari</Label>
                        <Select
                            value={expense.data.cash_box}
                            onValueChange={(v) =>
                                expense.setData('cash_box', v)
                            }
                        >
                            <SelectTrigger id="ex-box">
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
                        <Label htmlFor="ex-ref">No. kuitansi / bukti</Label>
                        <Input
                            id="ex-ref"
                            value={expense.data.reference_no}
                            onChange={(e) =>
                                expense.setData('reference_no', e.target.value)
                            }
                            maxLength={50}
                        />
                        <InputError message={expense.errors.reference_no} />
                    </div>
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="ex-party">Nama toko / penerima</Label>
                        <Input
                            id="ex-party"
                            value={expense.data.counterparty}
                            onChange={(e) =>
                                expense.setData('counterparty', e.target.value)
                            }
                            maxLength={120}
                        />
                    </div>
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="ex-desc">
                            Uraian / rincian belanja
                        </Label>
                        <Input
                            id="ex-desc"
                            value={expense.data.description}
                            onChange={(e) =>
                                expense.setData('description', e.target.value)
                            }
                            maxLength={255}
                            required
                        />
                        <InputError message={expense.errors.description} />
                    </div>
                </div>
            </FormDialog>

            <FormDialog
                open={reversing !== null}
                onOpenChange={(o) => !o && setReversing(null)}
                title="Koreksi entri buku kas"
                description={
                    reversing
                        ? `${reversing.description} · ${reversing.amount.toLocaleString('id-ID')} rupiah. Dibuat entri pembalik bertanggal hari ini; entri asli tetap tercatat.`
                        : undefined
                }
                onSubmit={submitReverse}
                processing={reverseForm.processing}
                submitLabel="Buat entri koreksi"
            >
                <div className="grid gap-2">
                    <Label htmlFor="rv-reason">Alasan koreksi</Label>
                    <Input
                        id="rv-reason"
                        value={reverseForm.data.reason}
                        onChange={(e) =>
                            reverseForm.setData('reason', e.target.value)
                        }
                        placeholder="Mis. Nominal salah ketik"
                        required
                    />
                    <InputError message={reverseForm.errors.reason} />
                </div>
            </FormDialog>
        </StaffLayout>
    );
}

CashBook.layout = (page: ReactNode) => page;
