import { Head, router, useForm } from '@inertiajs/react';
import {
    BellRing,
    Copy,
    KeyRound,
    Plus,
    Printer,
    UserCheck,
    UserPlus,
    UserX,
    X,
} from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import GuardianController from '@/actions/App/Http/Controllers/Admin/GuardianController';
import StudentController from '@/actions/App/Http/Controllers/Admin/StudentController';
import { ConfirmDialog } from '@/components/app/confirm-dialog';
import type { DataTableColumn } from '@/components/app/data-table';
import { DataTable } from '@/components/app/data-table';
import { ErrorState } from '@/components/app/error-state';
import { FormDialog } from '@/components/app/form-dialog';
import { PageHeader } from '@/components/app/page-header';
import { RowActions } from '@/components/app/row-actions';
import { StatusBadge } from '@/components/app/status-badge';
import type {
    GuardianCredentials,
    LetterIdentity,
} from '@/components/guardians/account-letter';
import { GuardianAccountLetter } from '@/components/guardians/account-letter';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import StaffLayout from '@/layouts/staff-layout';
import type { PaginatedResource } from '@/types';

type Relation = 'ayah' | 'ibu' | 'wali';

type Child = {
    id: number;
    name: string;
    nis: string;
    classroom: string | null;
    is_primary: boolean;
};

type Guardian = {
    id: number;
    name: string;
    phone: string;
    username: string | null;
    relation: { value: Relation; label: string };
    address: string | null;
    family_card_number: string | null;
    email: string | null;
    is_active: boolean;
    is_staff_account: boolean;
    children: Child[];
};

type StudentHit = {
    id: number;
    name: string;
    nis: string;
    unit: string;
    classroom: string | null;
};

type SelectedChild = StudentHit & { is_primary: boolean };

type UnitOption = { id: number; code: string; name: string };

type Filters = {
    unit_id: number | null;
    status: string;
    search: string;
};

type Props = {
    guardians: PaginatedResource<Guardian>;
    filters: Filters;
    unitHasGuardians: boolean;
    academicYearLabel: string | null;
    unitOptions: UnitOption[];
    relationOptions: { value: Relation; label: string }[];
    maxChildren: number;
    canSendTestNotification: boolean;
    letter: LetterIdentity;
};

type GuardianForm = {
    name: string;
    phone: string;
    relation: Relation | '';
    address: string;
    family_card_number: string;
    email: string;
    username: string;
    password: string;
    children: { student_id: number; is_primary: boolean }[];
};

/** Tautan WhatsApp berisi info akun (tanpa kata sandi) untuk dikirim manual oleh admin. */
function whatsappLink(guardian: Guardian): string {
    const digits = guardian.phone.replace(/\D/g, '');
    const number = digits.startsWith('0') ? `62${digits.slice(1)}` : digits;
    const text = `Assalamu'alaikum ${guardian.name}. Akun aplikasi madrasah Anda: username ${guardian.username ?? guardian.phone}. Masuk di ${window.location.origin}/login. Bila lupa kata sandi, hubungi admin sekolah.`;

    return `https://wa.me/${number}?text=${encodeURIComponent(text)}`;
}

type SearchState = 'idle' | 'loading' | 'done' | 'failed';

const emptyForm: GuardianForm = {
    name: '',
    phone: '',
    relation: '',
    address: '',
    family_card_number: '',
    email: '',
    username: '',
    password: '',
    children: [],
};

function StudentPicker({
    selected,
    max,
    onAdd,
}: {
    selected: SelectedChild[];
    max: number;
    onAdd: (student: StudentHit) => void;
}) {
    const [query, setQuery] = useState('');
    const [hits, setHits] = useState<StudentHit[]>([]);
    const [state, setState] = useState<SearchState>('idle');
    const timer = useRef<number | undefined>(undefined);
    const controller = useRef<AbortController | null>(null);
    const input = useRef<HTMLInputElement>(null);

    useEffect(
        () => () => {
            window.clearTimeout(timer.current);
            controller.current?.abort();
        },
        [],
    );

    const run = async (value: string): Promise<void> => {
        controller.current?.abort();
        const current = new AbortController();
        controller.current = current;
        setState('loading');

        try {
            const response = await fetch(
                GuardianController.searchStudents.url({ query: { q: value } }),
                {
                    headers: { Accept: 'application/json' },
                    signal: current.signal,
                },
            );

            if (!response.ok) {
                throw new Error(String(response.status));
            }

            const body = (await response.json()) as { data: StudentHit[] };
            setHits(body.data);
            setState('done');
        } catch (error) {
            if (
                !(error instanceof DOMException && error.name === 'AbortError')
            ) {
                setState('failed');
            }
        }
    };

    const change = (value: string): void => {
        setQuery(value);
        window.clearTimeout(timer.current);

        if (value.trim().length < 2) {
            controller.current?.abort();
            setHits([]);
            setState('idle');

            return;
        }

        timer.current = window.setTimeout(() => void run(value.trim()), 300);
    };

    const full = selected.length >= max;
    const visible = hits.filter(
        (hit) => !selected.some((child) => child.id === hit.id),
    );

    let status: ReactNode = null;

    if (state === 'loading') {
        status = (
            <span className="inline-flex items-center gap-2">
                <Spinner className="size-3.5" /> Mencari siswa…
            </span>
        );
    } else if (state === 'failed') {
        status = (
            <span className="text-destructive">
                Pencarian gagal.{' '}
                <button
                    type="button"
                    className="underline underline-offset-2"
                    onClick={() => void run(query.trim())}
                >
                    Coba lagi
                </button>
            </span>
        );
    } else if (state === 'done' && visible.length === 0) {
        status = 'Tidak ada siswa aktif yang cocok.';
    }

    return (
        <div className="grid gap-2">
            <Input
                ref={input}
                id="guardian-child-search"
                type="search"
                value={query}
                onChange={(event) => change(event.target.value)}
                placeholder={
                    full
                        ? `Paling banyak ${max} anak`
                        : 'Cari nama atau NIS siswa'
                }
                autoComplete="off"
                disabled={full}
                aria-describedby="guardian-child-search-status"
            />
            <div
                id="guardian-child-search-status"
                aria-live="polite"
                className="text-xs text-muted-foreground empty:hidden"
            >
                {status}
            </div>
            {visible.length > 0 && !full && (
                <ul
                    aria-label="Hasil pencarian siswa"
                    className="max-h-48 divide-y divide-border overflow-y-auto rounded-md border border-border"
                >
                    {visible.map((hit) => (
                        <li key={hit.id}>
                            <button
                                type="button"
                                onClick={() => {
                                    onAdd(hit);
                                    input.current?.focus();
                                }}
                                className="flex w-full items-center justify-between gap-3 px-3 py-2 text-left text-sm hover:bg-muted focus-visible:bg-muted focus-visible:outline-none"
                            >
                                <span className="min-w-0">
                                    <span className="block truncate font-medium text-foreground">
                                        {hit.name}
                                    </span>
                                    <span className="block text-xs text-muted-foreground tabular-nums">
                                        {hit.nis} · {hit.unit} ·{' '}
                                        {hit.classroom ?? 'Belum ada rombel'}
                                    </span>
                                </span>
                                <Plus
                                    className="size-4 shrink-0 text-muted-foreground"
                                    strokeWidth={1.75}
                                    aria-hidden="true"
                                />
                            </button>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}

export default function GuardiansIndex({
    guardians,
    filters,
    unitHasGuardians,
    academicYearLabel,
    unitOptions,
    relationOptions,
    maxChildren,
    canSendTestNotification,
    letter,
}: Props) {
    const [search, setSearch] = useState(filters.search);
    const [loading, setLoading] = useState(false);
    const [loadFailed, setLoadFailed] = useState(false);
    const [editing, setEditing] = useState<Guardian | null>(null);
    const [formOpen, setFormOpen] = useState(false);
    const [selected, setSelected] = useState<SelectedChild[]>([]);
    const [statusTarget, setStatusTarget] = useState<Guardian | null>(null);
    const [resetTarget, setResetTarget] = useState<Guardian | null>(null);
    const [testNotifTarget, setTestNotifTarget] = useState<Guardian | null>(
        null,
    );
    const [acting, setActing] = useState(false);
    const [credentials, setCredentials] = useState<GuardianCredentials | null>(
        null,
    );
    const searchTimer = useRef<number | undefined>(undefined);

    const unit = unitOptions.find((option) => option.id === filters.unit_id);
    const form = useForm<GuardianForm>(emptyForm);

    useEffect(() => {
        return router.on('flash', (event) => {
            const flash = (event as CustomEvent).detail?.flash;
            const issued = flash?.credentials as
                | GuardianCredentials
                | undefined;

            if (issued) {
                setCredentials(issued);
            }
        });
    }, []);

    useEffect(() => () => window.clearTimeout(searchTimer.current), []);

    const visit = (
        changes: Partial<Filters> & { page?: number },
        searchValue: string = search,
    ): void => {
        const query = { ...filters, search: searchValue, ...changes };
        const params = Object.fromEntries(
            Object.entries(query).filter(
                ([, value]) =>
                    value !== '' && value !== null && value !== undefined,
            ),
        );

        router.get(GuardianController.index.url(), params, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['guardians', 'filters', 'unitHasGuardians'],
            onStart: () => {
                setLoading(true);
                setLoadFailed(false);
            },
            onFinish: () => setLoading(false),
            onNetworkError: () => {
                setLoadFailed(true);

                return false;
            },
            onHttpException: () => {
                setLoadFailed(true);

                return false;
            },
        });
    };

    const changeSearch = (value: string): void => {
        setSearch(value);
        window.clearTimeout(searchTimer.current);
        searchTimer.current = window.setTimeout(
            () => visit({ page: 1 }, value),
            300,
        );
    };

    const syncChildren = (next: SelectedChild[]): void => {
        setSelected(next);
        form.setData(
            'children',
            next.map((child) => ({
                student_id: child.id,
                is_primary: child.is_primary,
            })),
        );
        form.clearErrors('children');
    };

    const setField = <K extends 'name' | 'phone' | 'address' | 'relation'>(
        key: K,
        value: GuardianForm[K],
    ): void => {
        form.setData((data) => ({ ...data, [key]: value }));
        form.clearErrors(key);
    };

    const openCreate = (): void => {
        setEditing(null);
        setSelected([]);
        form.clearErrors();
        form.setData(emptyForm);
        setFormOpen(true);
    };

    const openEdit = (guardian: Guardian): void => {
        const children = guardian.children.map((child) => ({
            id: child.id,
            name: child.name,
            nis: child.nis,
            unit: '',
            classroom: child.classroom,
            is_primary: child.is_primary,
        }));

        setEditing(guardian);
        setSelected(children);
        form.clearErrors();
        form.setData({
            name: guardian.name,
            phone: guardian.phone,
            relation: guardian.relation.value,
            address: guardian.address ?? '',
            family_card_number: guardian.family_card_number ?? '',
            email: guardian.email ?? '',
            username: guardian.username ?? '',
            password: '',
            children: children.map((child) => ({
                student_id: child.id,
                is_primary: child.is_primary,
            })),
        });
        setFormOpen(true);
    };

    const submit = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();

        const options = {
            preserveScroll: true,
            onSuccess: () => setFormOpen(false),
        };

        if (editing) {
            form.put(GuardianController.update.url(editing.id), options);
        } else {
            form.post(GuardianController.store.url(), options);
        }
    };

    const confirmStatus = (): void => {
        if (!statusTarget) {
            return;
        }

        router.patch(
            GuardianController.updateStatus.url(statusTarget.id),
            { is_active: !statusTarget.is_active },
            {
                preserveScroll: true,
                onStart: () => setActing(true),
                onFinish: () => {
                    setActing(false);
                    setStatusTarget(null);
                },
            },
        );
    };

    const confirmReset = (): void => {
        if (!resetTarget) {
            return;
        }

        router.post(
            GuardianController.resetPassword.url(resetTarget.id),
            {},
            {
                preserveScroll: true,
                onStart: () => setActing(true),
                onFinish: () => {
                    setActing(false);
                    setResetTarget(null);
                },
            },
        );
    };

    const confirmTestNotification = (): void => {
        if (!testNotifTarget) {
            return;
        }

        router.post(
            GuardianController.sendTestNotification.url(testNotifTarget.id),
            {},
            {
                preserveScroll: true,
                onStart: () => setActing(true),
                onFinish: () => {
                    setActing(false);
                    setTestNotifTarget(null);
                },
            },
        );
    };

    const copyPassword = async (): Promise<void> => {
        if (!credentials) {
            return;
        }

        try {
            await navigator.clipboard.writeText(credentials.password);
            toast.success('Kata sandi disalin.');
        } catch {
            toast.error('Gagal menyalin. Catat kata sandi secara manual.');
        }
    };

    const childrenError =
        form.errors.children ??
        Object.entries(form.errors).find(([key]) =>
            key.startsWith('children.'),
        )?.[1];

    const hasFilters = filters.search !== '' || filters.status !== 'active';

    const addButton = (
        <Button type="button" onClick={openCreate}>
            <UserPlus className="size-4" strokeWidth={1.75} />
            Tambah wali
        </Button>
    );

    const emptyState = (): {
        title: string;
        description: string;
        action?: ReactNode;
    } => {
        if (unitOptions.length === 0) {
            return {
                title: 'Belum ada unit aktif',
                description:
                    'Aktifkan atau tambahkan unit di Pengaturan > Unit & jam presensi.',
            };
        }

        if (!unit) {
            return {
                title: 'Pilih unit',
                description: 'Pilih unit di atas untuk melihat walinya.',
            };
        }

        if (unitHasGuardians || hasFilters) {
            return {
                title: 'Tidak ada wali yang cocok',
                description: `Tidak ada wali siswa ${unit.code} dengan saringan ini. Ubah kata kunci atau status akun.`,
            };
        }

        return {
            title: `Belum ada wali siswa ${unit.code}`,
            description:
                'Tambahkan wali lalu hubungkan dengan anaknya. Username wali adalah nomor HP-nya.',
            action: addButton,
        };
    };

    const iconButton = (
        label: string,
        tooltip: string,
        icon: ReactNode,
        onClick: () => void,
        destructive = false,
    ): ReactNode => (
        <Tooltip>
            <TooltipTrigger asChild>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    aria-label={label}
                    onClick={onClick}
                    className={
                        destructive
                            ? 'text-destructive hover:bg-destructive/10 hover:text-destructive'
                            : undefined
                    }
                >
                    {icon}
                </Button>
            </TooltipTrigger>
            <TooltipContent>{tooltip}</TooltipContent>
        </Tooltip>
    );

    const columns: DataTableColumn<Guardian>[] = [
        {
            key: 'name',
            label: 'Nama orang tua / wali',
            render: (guardian) => (
                <div className="min-w-36">
                    <div className="font-medium text-foreground">
                        {guardian.name}
                    </div>
                    <div className="text-xs text-muted-foreground">
                        {guardian.relation.label} · @
                        {guardian.username ?? guardian.phone}
                    </div>
                </div>
            ),
        },
        {
            key: 'family_card_number',
            label: 'No. KK & alamat',
            className: 'hidden @5xl:table-cell',
            render: (guardian) => (
                <div className="max-w-56 text-xs">
                    <div className="tabular-nums">
                        {guardian.family_card_number ?? '—'}
                    </div>
                    <div className="line-clamp-2 text-muted-foreground">
                        {guardian.address ?? '—'}
                    </div>
                </div>
            ),
        },
        {
            key: 'phone',
            label: 'WhatsApp',
            className: 'tabular-nums whitespace-nowrap',
            render: (guardian) => (
                <a
                    href={whatsappLink(guardian)}
                    target="_blank"
                    rel="noreferrer"
                    className="text-primary hover:underline"
                    aria-label={`Kirim info akun lewat WhatsApp ke ${guardian.name}`}
                >
                    {guardian.phone}
                </a>
            ),
        },
        {
            key: 'children',
            label: 'Anak',
            render: (guardian) => (
                <ul className="min-w-44 space-y-0.5">
                    {guardian.children.map((child) => (
                        <li key={child.id}>
                            {child.name}
                            <span className="text-muted-foreground">
                                {' · '}
                                {child.classroom ?? 'Belum ada rombel'}
                                {child.is_primary ? ' · utama' : ''}
                            </span>
                        </li>
                    ))}
                </ul>
            ),
        },
        {
            key: 'status',
            label: 'Status akun',
            align: 'center',
            render: (guardian) => (
                <div className="inline-flex flex-wrap justify-center gap-1">
                    {guardian.is_active ? (
                        <StatusBadge variant="success">Aktif</StatusBadge>
                    ) : (
                        <StatusBadge variant="muted">Nonaktif</StatusBadge>
                    )}
                    {guardian.is_staff_account && (
                        <StatusBadge variant="muted">Akun staf</StatusBadge>
                    )}
                </div>
            ),
        },
        {
            key: 'actions',
            label: 'Aksi',
            align: 'center',
            render: (guardian) => (
                <RowActions
                    itemName={guardian.name}
                    onEdit={() => openEdit(guardian)}
                >
                    {canSendTestNotification &&
                        guardian.is_active &&
                        iconButton(
                            `Kirim notifikasi uji ke ${guardian.name}`,
                            'Kirim notifikasi uji',
                            <BellRing className="size-4" strokeWidth={1.75} />,
                            () => setTestNotifTarget(guardian),
                        )}
                    {guardian.is_staff_account ? (
                        // Kata sandi & status akun staf dikelola di Pengguna staf; kolom tetap sejajar.
                        <span aria-hidden="true" className="w-[4.75rem]" />
                    ) : (
                        <>
                            {iconButton(
                                `Reset kata sandi ${guardian.name}`,
                                'Reset kata sandi & cetak surat akun',
                                <KeyRound
                                    className="size-4"
                                    strokeWidth={1.75}
                                />,
                                () => setResetTarget(guardian),
                            )}
                            {iconButton(
                                `${guardian.is_active ? 'Nonaktifkan' : 'Aktifkan'} ${guardian.name}`,
                                guardian.is_active ? 'Nonaktifkan' : 'Aktifkan',
                                guardian.is_active ? (
                                    <UserX
                                        className="size-4"
                                        strokeWidth={1.75}
                                    />
                                ) : (
                                    <UserCheck
                                        className="size-4"
                                        strokeWidth={1.75}
                                    />
                                ),
                                () => setStatusTarget(guardian),
                                guardian.is_active,
                            )}
                        </>
                    )}
                </RowActions>
            ),
        },
    ];

    return (
        <StaffLayout
            breadcrumbs={[
                { title: 'Pengguna', href: StudentController.index() },
                { title: 'Orang Tua / Wali', href: GuardianController.index() },
            ]}
        >
            <Head title="Orang Tua / Wali" />

            <div className="@container space-y-4">
                <PageHeader
                    title="Orang Tua / Wali"
                    description={
                        academicYearLabel
                            ? `Akun orang tua/wali dan anak yang terhubung. Rombel ditampilkan untuk ${academicYearLabel}.`
                            : 'Akun orang tua/wali dan anak yang terhubung.'
                    }
                    actions={
                        <>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => window.print()}
                            >
                                <Printer
                                    className="size-4"
                                    strokeWidth={1.75}
                                />
                                Cetak daftar wali
                            </Button>
                            {addButton}
                        </>
                    }
                />

                <div className="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center">
                    <Input
                        type="search"
                        value={search}
                        onChange={(event) => changeSearch(event.target.value)}
                        placeholder="Cari nama, No. KK, nomor HP, atau anak"
                        aria-label="Cari wali"
                        className="w-full sm:max-w-xs"
                    />
                    <Select
                        value={
                            filters.unit_id === null
                                ? undefined
                                : String(filters.unit_id)
                        }
                        onValueChange={(value) =>
                            visit({ unit_id: Number(value), page: 1 })
                        }
                        disabled={unitOptions.length === 0}
                    >
                        <SelectTrigger
                            className="w-full sm:w-60"
                            aria-label="Saring unit anak"
                        >
                            <SelectValue placeholder="Pilih unit" />
                        </SelectTrigger>
                        <SelectContent>
                            {unitOptions.map((option) => (
                                <SelectItem
                                    key={option.id}
                                    value={String(option.id)}
                                >
                                    {option.code} · {option.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <Select
                        value={filters.status}
                        onValueChange={(value) =>
                            visit({ status: value, page: 1 })
                        }
                    >
                        <SelectTrigger
                            className="w-full sm:w-44"
                            aria-label="Saring status akun"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="active">Akun aktif</SelectItem>
                            <SelectItem value="inactive">
                                Akun nonaktif
                            </SelectItem>
                            <SelectItem value="all">Semua status</SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                {loadFailed ? (
                    <ErrorState
                        description="Daftar wali tidak dapat dimuat. Periksa koneksi lalu coba lagi."
                        onRetry={() => visit({})}
                    />
                ) : (
                    <DataTable
                        columns={columns}
                        data={guardians.data}
                        rowKey={(guardian) => guardian.id}
                        loading={loading}
                        pagination={{
                            currentPage: guardians.meta.current_page,
                            lastPage: guardians.meta.last_page,
                            perPage: guardians.meta.per_page,
                            total: guardians.meta.total,
                            from: guardians.meta.from,
                            to: guardians.meta.to,
                        }}
                        onPageChange={(page) => visit({ page })}
                        empty={emptyState()}
                    />
                )}
            </div>

            <FormDialog
                open={formOpen}
                onOpenChange={setFormOpen}
                title={editing ? 'Ubah wali' : 'Tambah wali'}
                description={
                    editing
                        ? editing.is_staff_account
                            ? 'Akun ini juga akun staf; username dan kata sandinya tetap.'
                            : 'Mengubah nomor HP juga mengubah username login.'
                        : 'Kata sandi awal dibuat otomatis, tampil sekali, dan bisa dicetak sebagai surat akun.'
                }
                onSubmit={submit}
                processing={form.processing}
                contentClassName="max-h-[calc(100dvh-2rem)] overflow-y-auto sm:max-w-xl"
            >
                <div className="grid gap-2">
                    <Label htmlFor="guardian-name">Nama wali</Label>
                    <Input
                        id="guardian-name"
                        value={form.data.name}
                        onChange={(event) =>
                            setField('name', event.target.value)
                        }
                        maxLength={100}
                        autoComplete="off"
                        required
                        aria-invalid={Boolean(form.errors.name)}
                    />
                    <InputError message={form.errors.name} />
                </div>
                <div className="grid items-start gap-4 sm:grid-cols-2">
                    <div className="grid gap-2">
                        <Label htmlFor="guardian-phone">
                            Nomor HP (username)
                        </Label>
                        <Input
                            id="guardian-phone"
                            type="tel"
                            inputMode="tel"
                            value={form.data.phone}
                            onChange={(event) =>
                                setField('phone', event.target.value)
                            }
                            placeholder="08…"
                            autoComplete="off"
                            required
                            aria-invalid={Boolean(form.errors.phone)}
                        />
                        <InputError message={form.errors.phone} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="guardian-relation">Hubungan</Label>
                        <Select
                            value={form.data.relation || undefined}
                            onValueChange={(value) =>
                                setField('relation', value as Relation)
                            }
                        >
                            <SelectTrigger
                                id="guardian-relation"
                                className="w-full"
                                aria-invalid={Boolean(form.errors.relation)}
                            >
                                <SelectValue placeholder="Pilih hubungan" />
                            </SelectTrigger>
                            <SelectContent>
                                {relationOptions.map((option) => (
                                    <SelectItem
                                        key={option.value}
                                        value={option.value}
                                    >
                                        {option.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={form.errors.relation} />
                    </div>
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="guardian-address">Alamat (opsional)</Label>
                    <Input
                        id="guardian-address"
                        value={form.data.address}
                        onChange={(event) =>
                            setField('address', event.target.value)
                        }
                        maxLength={500}
                        autoComplete="off"
                    />
                    <InputError message={form.errors.address} />
                </div>
                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="grid gap-2">
                        <Label htmlFor="guardian-kk">
                            No. Kartu Keluarga (opsional)
                        </Label>
                        <Input
                            id="guardian-kk"
                            value={form.data.family_card_number}
                            onChange={(event) =>
                                setField(
                                    'family_card_number',
                                    event.target.value,
                                )
                            }
                            inputMode="numeric"
                            maxLength={16}
                            autoComplete="off"
                        />
                        <InputError message={form.errors.family_card_number} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="guardian-email">Email (opsional)</Label>
                        <Input
                            id="guardian-email"
                            type="email"
                            value={form.data.email}
                            onChange={(event) =>
                                setField('email', event.target.value)
                            }
                            autoComplete="off"
                        />
                        <InputError message={form.errors.email} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="guardian-username">
                            Username (kosongkan = nomor HP)
                        </Label>
                        <Input
                            id="guardian-username"
                            value={form.data.username}
                            onChange={(event) =>
                                setField(
                                    'username',
                                    event.target.value.toLowerCase(),
                                )
                            }
                            autoComplete="off"
                            autoCapitalize="none"
                        />
                        <InputError message={form.errors.username} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="guardian-password">
                            {editing
                                ? 'Kata sandi baru (kosongkan bila tetap)'
                                : 'Kata sandi (kosongkan = dibuat otomatis)'}
                        </Label>
                        <Input
                            id="guardian-password"
                            type="password"
                            value={form.data.password}
                            onChange={(event) =>
                                setField('password', event.target.value)
                            }
                            autoComplete="new-password"
                        />
                        <InputError message={form.errors.password} />
                    </div>
                </div>
                <fieldset className="grid gap-2">
                    <legend className="mb-1 text-sm font-medium">
                        <label htmlFor="guardian-child-search">Anak</label>
                    </legend>
                    {selected.length > 0 && (
                        <ul className="divide-y divide-border rounded-md border border-border">
                            {selected.map((child) => {
                                const id = `guardian-primary-${child.id}`;

                                return (
                                    <li
                                        key={child.id}
                                        className="flex flex-wrap items-center gap-x-3 gap-y-1 px-3 py-2 text-sm"
                                    >
                                        <span className="min-w-0 flex-1">
                                            <span className="block truncate font-medium text-foreground">
                                                {child.name}
                                            </span>
                                            <span className="block text-xs text-muted-foreground tabular-nums">
                                                {child.nis} ·{' '}
                                                {child.classroom ??
                                                    'Belum ada rombel'}
                                            </span>
                                        </span>
                                        <span className="flex items-center gap-2">
                                            <Checkbox
                                                id={id}
                                                checked={child.is_primary}
                                                onCheckedChange={(checked) =>
                                                    syncChildren(
                                                        selected.map((item) =>
                                                            item.id === child.id
                                                                ? {
                                                                      ...item,
                                                                      is_primary:
                                                                          checked ===
                                                                          true,
                                                                  }
                                                                : item,
                                                        ),
                                                    )
                                                }
                                            />
                                            <Label
                                                htmlFor={id}
                                                className="font-normal"
                                            >
                                                Wali utama
                                            </Label>
                                        </span>
                                        <Tooltip>
                                            <TooltipTrigger asChild>
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="icon"
                                                    aria-label={`Lepas ${child.name}`}
                                                    onClick={() =>
                                                        syncChildren(
                                                            selected.filter(
                                                                (item) =>
                                                                    item.id !==
                                                                    child.id,
                                                            ),
                                                        )
                                                    }
                                                >
                                                    <X
                                                        className="size-4"
                                                        strokeWidth={1.75}
                                                    />
                                                </Button>
                                            </TooltipTrigger>
                                            <TooltipContent>
                                                Lepas
                                            </TooltipContent>
                                        </Tooltip>
                                    </li>
                                );
                            })}
                        </ul>
                    )}
                    <StudentPicker
                        selected={selected}
                        max={maxChildren}
                        onAdd={(student) =>
                            syncChildren([
                                ...selected,
                                { ...student, is_primary: false },
                            ])
                        }
                    />
                    <InputError message={childrenError} />
                </fieldset>
            </FormDialog>

            <ConfirmDialog
                open={statusTarget !== null}
                onOpenChange={(open) => !open && setStatusTarget(null)}
                title={
                    statusTarget?.is_active
                        ? 'Nonaktifkan akun wali?'
                        : 'Aktifkan akun wali?'
                }
                description={
                    statusTarget?.is_active
                        ? `${statusTarget.name} tidak dapat masuk sampai akunnya diaktifkan kembali. Hubungan dengan anak tetap tersimpan.`
                        : `${statusTarget?.name ?? ''} dapat masuk kembali dengan kata sandi yang sama.`
                }
                confirmLabel={
                    statusTarget?.is_active ? 'Nonaktifkan' : 'Aktifkan'
                }
                variant={statusTarget?.is_active ? 'destructive' : 'default'}
                loading={acting}
                onConfirm={confirmStatus}
            />

            <ConfirmDialog
                open={resetTarget !== null}
                onOpenChange={(open) => !open && setResetTarget(null)}
                title="Reset kata sandi wali?"
                description={`Kata sandi ${resetTarget?.name ?? ''} diganti dengan yang baru dan surat akun bisa dicetak ulang. Kata sandi lama tidak berlaku lagi.`}
                confirmLabel="Reset kata sandi"
                variant="default"
                loading={acting}
                onConfirm={confirmReset}
            />

            <ConfirmDialog
                open={testNotifTarget !== null}
                onOpenChange={(open) => !open && setTestNotifTarget(null)}
                title="Kirim notifikasi uji?"
                description={`Kirim notifikasi uji ke kotak masuk dan perangkat push milik ${testNotifTarget?.name ?? ''} untuk memastikan notifikasi masuk.`}
                confirmLabel="Kirim notifikasi uji"
                variant="default"
                loading={acting}
                onConfirm={confirmTestNotification}
            />

            <Dialog
                open={credentials !== null}
                onOpenChange={(open) => !open && setCredentials(null)}
            >
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Akun {credentials?.name}</DialogTitle>
                        <DialogDescription>
                            Kata sandi hanya ditampilkan sekali. Cetak surat
                            akun atau salin sebelum menutup.
                        </DialogDescription>
                    </DialogHeader>
                    <dl className="grid gap-3 rounded-lg border border-border bg-muted/40 p-4 text-sm">
                        <div className="grid gap-0.5">
                            <dt className="text-muted-foreground">Username</dt>
                            <dd className="font-mono text-foreground">
                                {credentials?.username ?? '—'}
                            </dd>
                        </div>
                        <div className="grid gap-0.5">
                            <dt className="text-muted-foreground">
                                Kata sandi
                            </dt>
                            <dd className="font-mono text-base tracking-wide text-foreground">
                                {credentials?.password}
                            </dd>
                        </div>
                        <div className="grid gap-0.5">
                            <dt className="text-muted-foreground">Anak</dt>
                            <dd className="text-foreground">
                                {credentials?.children
                                    .map((child) => child.name)
                                    .join(', ')}
                            </dd>
                        </div>
                    </dl>
                    <DialogFooter className="gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={copyPassword}
                        >
                            <Copy className="size-4" strokeWidth={1.75} />
                            Salin kata sandi
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => window.print()}
                        >
                            <Printer className="size-4" strokeWidth={1.75} />
                            Cetak surat akun
                        </Button>
                        <Button
                            type="button"
                            onClick={() => setCredentials(null)}
                        >
                            Selesai
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {credentials && (
                <GuardianAccountLetter
                    identity={letter}
                    credentials={credentials}
                />
            )}
        </StaffLayout>
    );
}
