import { Head, router, useForm } from '@inertiajs/react';
import { Banknote, FileText, Search, Wallet as WalletIcon } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { useEffect, useMemo, useRef, useState } from 'react';
import WalletController from '@/actions/App/Http/Controllers/Admin/WalletController';
import WalletTopupHistoryController from '@/actions/App/Http/Controllers/Admin/WalletTopupHistoryController';
import type { DataTableColumn } from '@/components/app/data-table';
import { DataTable } from '@/components/app/data-table';
import { DateInput } from '@/components/app/date-input';
import { DateText } from '@/components/app/date-text';
import { ExportButtons } from '@/components/app/export-buttons';
import { FormDialog } from '@/components/app/form-dialog';
import { MoneyText } from '@/components/app/money-text';
import { PageHeader } from '@/components/app/page-header';
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
import { formatTime } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { PaginatedResource } from '@/types';

type Option = { value: string; label: string };
type Row = {
    id: number;
    created_at: string;
    nis: string;
    name: string;
    unit_code: string;
    method: 'cash' | 'online';
    method_label: string;
    note: string | null;
    actor: string;
    amount: number;
    cancelled: boolean;
    cancel_reason: string | null;
};
type StudentPick = {
    id: number;
    nis: string;
    name: string;
    classroom_id: number | null;
    classroom: string | null;
};
type Filters = {
    period: string;
    from: string | null;
    to: string | null;
    unit: number | null;
    classroom: number | null;
    method: 'cash' | 'online' | null;
    search: string;
};

type Props = {
    rows: PaginatedResource<Row>;
    summary: {
        count: number;
        gross: number;
        cash: number;
        online: number;
        cancelled: number;
        net: number;
    };
    filters: Filters;
    periodLabel: string;
    periodOptions: Option[];
    unitOptions: Option[];
    classroomOptions: Option[];
    students: StudentPick[];
    limits: { min_top_up: number; max_top_up: number };
    canTopup: boolean;
    canCancel: boolean;
};

const ALL = 'semua';
const QUICK = [20_000, 50_000, 100_000];

export default function WalletTopups(props: Props) {
    const {
        rows,
        summary,
        filters,
        periodLabel,
        periodOptions,
        unitOptions,
        classroomOptions,
        students,
        limits,
        canTopup,
        canCancel,
    } = props;
    const [search, setSearch] = useState(filters.search);
    const [loading, setLoading] = useState(false);
    const timer = useRef<number | undefined>(undefined);

    const [topupOpen, setTopupOpen] = useState(false);
    const [pickClass, setPickClass] = useState<string>(ALL);
    const [pickSearch, setPickSearch] = useState('');
    const topup = useForm({
        student_id: '',
        amount: '',
        client_uuid: '',
        note: '',
    });

    const [cancelling, setCancelling] = useState<Row | null>(null);
    const cancelForm = useForm({ reason: '' });

    useEffect(() => () => window.clearTimeout(timer.current), []);

    const query = (
        changes: Partial<
            Record<keyof Filters | 'page', string | number | null>
        > = {},
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
        router.get(
            WalletTopupHistoryController.index.url(),
            query(changes, q),
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                onStart: () => setLoading(true),
                onFinish: () => setLoading(false),
            },
        );

    const exportQuery = new URLSearchParams(query({ page: null })).toString();

    const pickList = useMemo(() => {
        const term = pickSearch.trim().toLowerCase();
        return students
            .filter(
                (s) =>
                    pickClass === ALL || String(s.classroom_id) === pickClass,
            )
            .filter(
                (s) =>
                    term === '' ||
                    s.name.toLowerCase().includes(term) ||
                    s.nis.toLowerCase().includes(term),
            )
            .slice(0, 40);
    }, [students, pickClass, pickSearch]);
    const picked =
        students.find((s) => String(s.id) === topup.data.student_id) ?? null;
    const amount = Number.parseInt(topup.data.amount || '0', 10) || 0;

    const openTopup = () => {
        topup.clearErrors();
        topup.setData({
            student_id: '',
            amount: '',
            client_uuid: crypto.randomUUID(),
            note: '',
        });
        setPickSearch('');
        setPickClass(
            filters.classroom === null ? ALL : String(filters.classroom),
        );
        setTopupOpen(true);
    };

    const submitTopup = (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        topup.post(WalletController.topUp().url, {
            preserveScroll: true,
            onSuccess: () => setTopupOpen(false),
        });
    };

    const submitCancel = (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        if (!cancelling) {
            return;
        }
        cancelForm.post(
            WalletTopupHistoryController.cancel.url(cancelling.id),
            {
                preserveScroll: true,
                onSuccess: () => setCancelling(null),
            },
        );
    };

    const columns: DataTableColumn<Row>[] = [
        {
            key: 'time',
            label: 'Waktu',
            className: 'whitespace-nowrap',
            render: (r) => (
                <div>
                    <DateText value={r.created_at} />
                    <div className="text-xs text-muted-foreground tabular-nums">
                        {formatTime(r.created_at)} WITA
                    </div>
                </div>
            ),
        },
        {
            key: 'student',
            label: 'Siswa',
            render: (r) => (
                <div>
                    <div className="font-medium">{r.name}</div>
                    <div className="text-xs text-muted-foreground">
                        {r.nis} · {r.unit_code}
                    </div>
                </div>
            ),
        },
        {
            key: 'method',
            label: 'Metode',
            render: (r) => (
                <div className="space-y-0.5">
                    <StatusBadge
                        variant={r.method === 'cash' ? 'default' : 'attention'}
                    >
                        {r.method_label}
                    </StatusBadge>
                    {r.note &&
                        ![
                            'Isi saldo tunai di loket',
                            'Isi saldo online',
                        ].includes(r.note) && (
                            <div className="max-w-56 truncate text-xs text-muted-foreground">
                                {r.note}
                            </div>
                        )}
                </div>
            ),
        },
        {
            key: 'actor',
            label: 'Petugas',
            className: 'hidden @3xl:table-cell',
            render: (r) => r.actor,
        },
        {
            key: 'amount',
            label: 'Nominal',
            align: 'right',
            render: (r) =>
                r.cancelled ? (
                    <div className="text-right">
                        <MoneyText
                            amount={r.amount}
                            className="text-muted-foreground line-through"
                        />
                        <div>
                            <StatusBadge variant="destructive">
                                Dibatalkan
                            </StatusBadge>
                        </div>
                        {r.cancel_reason && (
                            <div className="max-w-48 text-xs text-muted-foreground">
                                {r.cancel_reason}
                            </div>
                        )}
                    </div>
                ) : (
                    <MoneyText amount={r.amount} className="font-semibold" />
                ),
        },
        {
            key: 'actions',
            label: 'Aksi',
            align: 'center',
            render: (r) => (
                <div className="flex flex-wrap justify-center gap-2">
                    {r.method === 'cash' && (
                        <Button asChild size="sm" variant="outline">
                            <a
                                href={WalletController.receipt.url(r.id)}
                                target="_blank"
                                rel="noreferrer"
                            >
                                <FileText
                                    className="size-4"
                                    strokeWidth={1.75}
                                />
                                Kuitansi
                            </a>
                        </Button>
                    )}
                    {canCancel && r.method === 'cash' && !r.cancelled && (
                        <Button
                            size="sm"
                            variant="outline"
                            className="text-destructive"
                            onClick={() => {
                                cancelForm.reset();
                                cancelForm.clearErrors();
                                setCancelling(r);
                            }}
                        >
                            Batalkan
                        </Button>
                    )}
                </div>
            ),
        },
    ];

    const tiles: {
        label: string;
        value: number;
        hint: string;
        tone?: string;
    }[] = [
        {
            label: 'Total masuk',
            value: summary.net,
            hint: `${summary.count} transaksi`,
        },
        { label: 'Tunai di loket', value: summary.cash, hint: 'Setoran kasir' },
        { label: 'Online', value: summary.online, hint: 'Midtrans' },
        {
            label: 'Dibatalkan',
            value: summary.cancelled,
            hint: 'Tidak dihitung',
            tone: summary.cancelled > 0 ? 'text-destructive' : undefined,
        },
    ];

    return (
        <StaffLayout
            breadcrumbs={[
                { title: 'Kantin', href: '#' },
                {
                    title: 'Riwayat Isi Saldo',
                    href: WalletTopupHistoryController.index.url(),
                },
            ]}
        >
            <Head title="Riwayat Isi Saldo" />

            <div className="@container space-y-4">
                <PageHeader
                    title="Riwayat Isi Saldo"
                    description="Jurnal isi saldo dompet kantin, tunai di loket dan online. Setoran tunai yang salah dibatalkan dengan alasan; saldo tidak pernah ditimpa."
                    actions={
                        <>
                            <ExportButtons
                                pdfHref={`${WalletTopupHistoryController.exportPdf.url()}?${exportQuery}`}
                                excelHref={`${WalletTopupHistoryController.exportExcel.url()}?${exportQuery}`}
                            />
                            {canTopup && (
                                <Button type="button" onClick={openTopup}>
                                    <Banknote
                                        className="size-4"
                                        strokeWidth={1.75}
                                    />
                                    Setor saldo tunai
                                </Button>
                            )}
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
                    Periode: {periodLabel}
                </p>

                <div className="flex flex-wrap gap-2">
                    <div className="relative w-full @3xl:w-64">
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
                            placeholder="Cari nama siswa atau NIS"
                            aria-label="Cari nama siswa atau NIS"
                            className="pl-9"
                        />
                    </div>
                    <Select
                        value={filters.period}
                        onValueChange={(v) => visit({ period: v, page: 1 })}
                    >
                        <SelectTrigger
                            className="w-full @lg:w-44"
                            aria-label="Periode"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {periodOptions.map((o) => (
                                <SelectItem key={o.value} value={o.value}>
                                    {o.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    {filters.period === 'custom' && (
                        <>
                            <DateInput
                                value={filters.from ?? ''}
                                onChange={(e) =>
                                    visit({
                                        from: e.target.value || null,
                                        page: 1,
                                    })
                                }
                                aria-label="Dari tanggal"
                                className="w-full @lg:w-40"
                            />
                            <DateInput
                                value={filters.to ?? ''}
                                onChange={(e) =>
                                    visit({
                                        to: e.target.value || null,
                                        page: 1,
                                    })
                                }
                                aria-label="Sampai tanggal"
                                className="w-full @lg:w-40"
                            />
                        </>
                    )}
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
                            filters.classroom === null
                                ? ALL
                                : String(filters.classroom)
                        }
                        onValueChange={(v) =>
                            visit({ classroom: v === ALL ? null : v, page: 1 })
                        }
                    >
                        <SelectTrigger
                            className="w-full @lg:w-40"
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
                    <Select
                        value={filters.method ?? ALL}
                        onValueChange={(v) =>
                            visit({ method: v === ALL ? null : v, page: 1 })
                        }
                    >
                        <SelectTrigger
                            className="w-full @lg:w-40"
                            aria-label="Saring metode"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL}>Semua metode</SelectItem>
                            <SelectItem value="cash">Tunai di loket</SelectItem>
                            <SelectItem value="online">Online</SelectItem>
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
                        title: 'Belum ada isi saldo',
                        description:
                            filters.period === 'all'
                                ? 'Belum ada isi saldo yang cocok dengan saringan ini.'
                                : 'Tidak ada isi saldo pada periode ini. Ubah periode ke "Semua waktu" untuk melihat seluruh riwayat.',
                    }}
                />
            </div>

            <FormDialog
                open={topupOpen}
                onOpenChange={setTopupOpen}
                title="Setor saldo tunai"
                description="Uang tunai diterima di loket lalu dicatat ke dompet kantin siswa. Wali langsung mendapat notifikasi."
                onSubmit={submitTopup}
                processing={topup.processing}
                submitLabel={
                    amount > 0 && picked
                        ? `Setor ${amount.toLocaleString('id-ID')} rupiah`
                        : 'Setor saldo'
                }
            >
                <div className="grid gap-2 sm:grid-cols-[10rem_1fr]">
                    <Select value={pickClass} onValueChange={setPickClass}>
                        <SelectTrigger aria-label="Saring rombel siswa">
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
                    <Input
                        value={pickSearch}
                        onChange={(e) => setPickSearch(e.target.value)}
                        placeholder="Cari nama atau NIS"
                        aria-label="Cari siswa penerima"
                    />
                </div>
                <div
                    role="listbox"
                    aria-label="Pilih siswa penerima saldo"
                    className="max-h-56 divide-y divide-border overflow-y-auto rounded-md border border-border"
                >
                    {pickList.length === 0 ? (
                        <p className="px-3 py-6 text-center text-sm text-muted-foreground">
                            Tidak ada siswa yang cocok.
                        </p>
                    ) : (
                        pickList.map((s) => (
                            <button
                                key={s.id}
                                type="button"
                                role="option"
                                aria-selected={
                                    String(s.id) === topup.data.student_id
                                }
                                onClick={() =>
                                    topup.setData('student_id', String(s.id))
                                }
                                className={cn(
                                    'flex w-full items-center justify-between gap-2 px-3 py-2 text-left text-sm hover:bg-muted focus-visible:bg-muted focus-visible:outline-none',
                                    String(s.id) === topup.data.student_id &&
                                        'bg-primary/10',
                                )}
                            >
                                <span>
                                    <span className="font-medium">
                                        {s.name}
                                    </span>{' '}
                                    <span className="text-muted-foreground">
                                        · {s.nis}
                                    </span>
                                </span>
                                <span className="text-xs text-muted-foreground">
                                    {s.classroom ?? '—'}
                                </span>
                            </button>
                        ))
                    )}
                </div>
                <InputError message={topup.errors.student_id} />
                {picked && (
                    <p className="flex items-center gap-2 text-sm">
                        <WalletIcon
                            className="size-4 text-primary"
                            strokeWidth={1.75}
                        />
                        Penerima:{' '}
                        <span className="font-medium">{picked.name}</span> (
                        {picked.classroom ?? 'tanpa rombel'})
                    </p>
                )}

                <div className="grid gap-2">
                    <Label htmlFor="topup-amount">
                        Nominal uang tunai diterima (Rp)
                    </Label>
                    <div className="flex flex-wrap gap-2">
                        {QUICK.map((q) => (
                            <Button
                                key={q}
                                type="button"
                                size="sm"
                                variant={amount === q ? 'soft' : 'outline'}
                                onClick={() =>
                                    topup.setData('amount', String(q))
                                }
                            >
                                <MoneyText amount={q} />
                            </Button>
                        ))}
                    </div>
                    <Input
                        id="topup-amount"
                        inputMode="numeric"
                        value={topup.data.amount}
                        onChange={(e) =>
                            topup.setData(
                                'amount',
                                e.target.value.replace(/\D/g, '').slice(0, 7),
                            )
                        }
                        placeholder={`Min. ${limits.min_top_up.toLocaleString('id-ID')} · maks. ${limits.max_top_up.toLocaleString('id-ID')}`}
                        className="tabular-nums"
                        required
                    />
                    <InputError message={topup.errors.amount} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="topup-note">Catatan (opsional)</Label>
                    <Input
                        id="topup-note"
                        value={topup.data.note}
                        onChange={(e) => topup.setData('note', e.target.value)}
                        maxLength={200}
                        placeholder="Mis. Dititipkan kakaknya"
                    />
                </div>
            </FormDialog>

            <FormDialog
                open={cancelling !== null}
                onOpenChange={(o) => !o && setCancelling(null)}
                title="Batalkan isi saldo tunai?"
                description={
                    cancelling
                        ? `${cancelling.name} · ${cancelling.amount.toLocaleString('id-ID')} rupiah. Saldo siswa dikurangi sebesar nominal ini, entri tetap tercatat sebagai dibatalkan, dan wali diberi tahu.`
                        : undefined
                }
                onSubmit={submitCancel}
                processing={cancelForm.processing}
                submitLabel="Ya, batalkan"
            >
                <div className="grid gap-2">
                    <Label htmlFor="cancel-reason">Alasan pembatalan</Label>
                    <Input
                        id="cancel-reason"
                        value={cancelForm.data.reason}
                        onChange={(e) =>
                            cancelForm.setData('reason', e.target.value)
                        }
                        placeholder="Mis. Salah pilih siswa"
                        required
                    />
                    <InputError message={cancelForm.errors.reason} />
                </div>
            </FormDialog>
        </StaffLayout>
    );
}

WalletTopups.layout = (page: ReactNode) => page;
