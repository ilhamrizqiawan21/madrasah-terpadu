import { Head, router, useForm } from '@inertiajs/react';
import {
    Copy,
    KeyRound,
    Printer,
    Trash2,
    UserCheck,
    UserPlus,
    UserX,
} from 'lucide-react';
import type { ComponentProps, FormEvent, ReactNode } from 'react';
import { useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import StaffUserController from '@/actions/App/Http/Controllers/Admin/StaffUserController';
import { ConfirmDialog } from '@/components/app/confirm-dialog';
import type { DataTableColumn } from '@/components/app/data-table';
import { DataTable } from '@/components/app/data-table';
import { DateInput } from '@/components/app/date-input';
import { MoneyText } from '@/components/app/money-text';
import { ErrorState } from '@/components/app/error-state';
import { FormDialog } from '@/components/app/form-dialog';
import { PageHeader } from '@/components/app/page-header';
import { RowActions } from '@/components/app/row-actions';
import { StatusBadge } from '@/components/app/status-badge';
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
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import StaffLayout from '@/layouts/staff-layout';
import type {
    IssuedCredentials,
    PaginatedResource,
    RoleOption,
    StaffUser,
} from '@/types';

type Filters = {
    search: string;
    role: string;
    status: string;
    sort: string;
    direction: 'asc' | 'desc';
    unit: number | null;
    assignment: string;
};

type Option = { value: string; label: string };

type UnitOption = {
    id: number;
    code: string;
    name: string;
};

type Props = {
    users: PaginatedResource<StaffUser>;
    filters: Filters;
    roleOptions: RoleOption[];
    unitOptions: UnitOption[];
    assignmentOptions: Option[];
    employmentOptions: Option[];
};

type StaffForm = {
    name: string;
    username: string;
    phone: string;
    email: string;
    roles: string[];
    unit_id: number | null;
    nip: string;
    position: string;
    title_prefix: string;
    title_suffix: string;
    nik: string;
    nuptk: string;
    gender: string;
    birth_place: string;
    birth_date: string;
    address: string;
    assignment_type: string;
    employment_status: string;
    base_salary: string;
    allowance: string;
    hourly_rate: string;
    password: string;
    photo: File | null;
};

const ALL = 'semua';

const emptyForm: StaffForm = {
    name: '',
    username: '',
    phone: '',
    email: '',
    roles: [],
    unit_id: null,
    nip: '',
    position: '',
    title_prefix: '',
    title_suffix: '',
    nik: '',
    nuptk: '',
    gender: '',
    birth_place: '',
    birth_date: '',
    address: '',
    assignment_type: 'guru',
    employment_status: 'gty',
    base_salary: '',
    allowance: '',
    hourly_rate: '',
    password: '',
    photo: null,
};

function fullName(user: StaffUser): string {
    const prefix = user.profile?.title_prefix;
    const suffix = user.profile?.title_suffix;

    return (
        [prefix, user.name].filter(Boolean).join(' ') +
        (suffix ? `, ${suffix}` : '')
    );
}

export default function StaffUsersIndex({
    users,
    filters,
    roleOptions,
    unitOptions,
    assignmentOptions,
    employmentOptions,
}: Props) {
    const [selectedIds, setSelectedIds] = useState<number[]>([]);
    const [bulkOpen, setBulkOpen] = useState(false);
    const [deletingId, setDeletingId] = useState<number | null>(null);
    const [search, setSearch] = useState(filters.search);
    const [loading, setLoading] = useState(false);
    const [loadFailed, setLoadFailed] = useState(false);
    const [editing, setEditing] = useState<StaffUser | null>(null);
    const [formOpen, setFormOpen] = useState(false);
    const [statusTarget, setStatusTarget] = useState<StaffUser | null>(null);
    const [resetTarget, setResetTarget] = useState<StaffUser | null>(null);
    const [acting, setActing] = useState(false);
    const [credentials, setCredentials] = useState<IssuedCredentials | null>(
        null,
    );
    const searchTimer = useRef<number | undefined>(undefined);

    const form = useForm<StaffForm>(emptyForm);

    useEffect(() => {
        return router.on('flash', (event) => {
            const flash = (event as CustomEvent).detail?.flash;
            const issued = flash?.credentials as IssuedCredentials | undefined;

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
                    value !== '' && value !== undefined && value !== null,
            ),
        );

        router.get(StaffUserController.index.url(), params, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['users', 'filters'],
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

    const openCreate = (): void => {
        setEditing(null);
        form.clearErrors();
        form.setData(emptyForm);
        setFormOpen(true);
    };

    const openEdit = (user: StaffUser): void => {
        setEditing(user);
        form.clearErrors();
        form.setData({
            name: user.name,
            username: user.username ?? '',
            phone: user.phone ?? '',
            email: user.email ?? '',
            roles: user.roles.map((role) => role.value),
            unit_id: user.profile?.unit_id ?? null,
            nip: user.profile?.nip ?? '',
            position: user.profile?.position ?? '',
            title_prefix: user.profile?.title_prefix ?? '',
            title_suffix: user.profile?.title_suffix ?? '',
            nik: user.profile?.nik ?? '',
            nuptk: user.profile?.nuptk ?? '',
            gender: user.profile?.gender ?? '',
            birth_place: user.profile?.birth_place ?? '',
            birth_date: user.profile?.birth_date ?? '',
            address: user.profile?.address ?? '',
            assignment_type: user.profile?.assignment_type ?? 'guru',
            employment_status: user.profile?.employment_status ?? 'gty',
            base_salary: user.profile ? String(user.profile.base_salary) : '',
            allowance: user.profile ? String(user.profile.allowance) : '',
            hourly_rate: user.profile ? String(user.profile.hourly_rate) : '',
            password: '',
            photo: null,
        });
        setFormOpen(true);
    };

    const submit = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();

        const options = {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => setFormOpen(false),
        };

        if (editing) {
            // Unggahan foto butuh multipart: kirim POST dengan _method=PUT.
            form.transform((data) => ({ ...data, _method: 'put' }));
            form.post(StaffUserController.update.url(editing.id), options);
        } else {
            form.transform((data) => data);
            form.post(StaffUserController.store.url(), options);
        }
    };

    const toggleRole = (value: string, checked: boolean): void => {
        form.setData(
            'roles',
            checked
                ? [...form.data.roles, value]
                : form.data.roles.filter((role) => role !== value),
        );
    };

    const confirmStatus = (): void => {
        if (!statusTarget) {
            return;
        }

        router.patch(
            StaffUserController.updateStatus.url(statusTarget.id),
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
            StaffUserController.resetPassword.url(resetTarget.id),
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

    const destroyOne = (user: StaffUser): void => {
        router.delete(StaffUserController.destroy.url(user.id), {
            preserveScroll: true,
            onStart: () => setDeletingId(user.id),
            onFinish: () => setDeletingId(null),
        });
    };

    const destroySelected = (): void => {
        router.post(
            StaffUserController.bulkDestroy.url(),
            { ids: selectedIds },
            {
                preserveScroll: true,
                onStart: () => setActing(true),
                onFinish: () => {
                    setActing(false);
                    setBulkOpen(false);
                    setSelectedIds([]);
                },
            },
        );
    };

    const toggleSelected = (id: number, checked: boolean): void => {
        setSelectedIds((ids) =>
            checked ? [...ids, id] : ids.filter((value) => value !== id),
        );
    };

    const selectableIds = users.data
        .filter((user) => !user.is_self)
        .map((user) => user.id);
    const allSelected =
        selectableIds.length > 0 &&
        selectableIds.every((id) => selectedIds.includes(id));

    const hasFilters =
        filters.search !== '' ||
        filters.role !== '' ||
        filters.status !== '' ||
        filters.unit !== null ||
        filters.assignment !== '';

    const rolesError =
        form.errors.roles ??
        Object.entries(form.errors).find(([key]) =>
            key.startsWith('roles.'),
        )?.[1];

    const columns: DataTableColumn<StaffUser>[] = [
        {
            key: 'select',
            label: 'Pilih',
            align: 'center',
            render: (user) =>
                user.is_self ? null : (
                    <Checkbox
                        checked={selectedIds.includes(user.id)}
                        onCheckedChange={(checked) =>
                            toggleSelected(user.id, checked === true)
                        }
                        aria-label={`Pilih ${user.name}`}
                    />
                ),
        },
        {
            key: 'name',
            label: 'Nama lengkap & gelar',
            sortable: true,
            render: (user) => (
                <div className="flex min-w-48 items-center gap-3">
                    {user.profile?.photo_url ? (
                        <img
                            src={user.profile.photo_url}
                            alt=""
                            className="size-10 shrink-0 rounded-full object-cover"
                        />
                    ) : (
                        <span
                            aria-hidden
                            className="flex size-10 shrink-0 items-center justify-center rounded-full bg-muted text-xs font-semibold text-muted-foreground"
                        >
                            {user.name.slice(0, 2).toUpperCase()}
                        </span>
                    )}
                    <div>
                        <div className="font-medium text-foreground">
                            {fullName(user)}
                            {user.is_self && (
                                <span className="ml-1.5 text-xs font-normal text-muted-foreground">
                                    (Anda)
                                </span>
                            )}
                        </div>
                        <div className="text-xs text-muted-foreground">
                            @{user.username ?? '—'}
                        </div>
                    </div>
                </div>
            ),
        },
        {
            key: 'ids',
            label: 'NIP / NUPTK / NIK',
            render: (user) => (
                <div className="text-xs whitespace-nowrap text-muted-foreground tabular-nums">
                    <div>NIP: {user.profile?.nip ?? '—'}</div>
                    <div>NUPTK: {user.profile?.nuptk ?? '—'}</div>
                    <div>NIK: {user.profile?.nik ?? '—'}</div>
                </div>
            ),
        },
        {
            key: 'assignment',
            label: 'Tipe & status kerja',
            render: (user) => (
                <div className="min-w-32 space-y-1">
                    <div className="text-sm">
                        {user.profile?.assignment_label ?? '—'}
                    </div>
                    <div className="flex flex-wrap gap-1">
                        {user.profile?.employment_label && (
                            <StatusBadge variant="muted">
                                {user.profile.employment_label}
                            </StatusBadge>
                        )}
                        {user.roles.map((role) => (
                            <StatusBadge key={role.value} variant="default">
                                {role.label}
                            </StatusBadge>
                        ))}
                    </div>
                </div>
            ),
        },
        {
            key: 'unit',
            label: 'Unit penempatan',
            render: (user) => {
                const homerooms = user.homeroom_classrooms ?? [];

                return (
                    <div className="min-w-28 text-sm">
                        <div>{user.profile?.unit_code ?? 'Yayasan'}</div>
                        {homerooms.length > 0 && (
                            <div className="text-xs text-muted-foreground">
                                Wali kelas: {homerooms.join(', ')}
                            </div>
                        )}
                    </div>
                );
            },
        },
        {
            key: 'contact',
            label: 'Kontak',
            render: (user) => (
                <div className="text-xs tabular-nums">
                    <div>{user.phone ?? '—'}</div>
                    <div className="text-muted-foreground">
                        {user.email ?? '—'}
                    </div>
                </div>
            ),
        },
        {
            key: 'base_salary',
            label: 'Gaji pokok',
            align: 'right',
            sortable: true,
            render: (user) => (
                <MoneyText amount={user.profile?.base_salary ?? 0} />
            ),
        },
        {
            key: 'status',
            label: 'Status',
            align: 'center',
            render: (user) =>
                user.is_active ? (
                    <StatusBadge variant="success">Aktif</StatusBadge>
                ) : (
                    <StatusBadge variant="muted">Nonaktif</StatusBadge>
                ),
        },
        {
            key: 'actions',
            label: 'Aksi',
            align: 'center',
            render: (user) => (
                <RowActions
                    onEdit={() => openEdit(user)}
                    onDelete={user.is_self ? undefined : () => destroyOne(user)}
                    deleting={deletingId === user.id}
                    itemName={user.name}
                    deleteTitle="Hapus data pegawai?"
                    deleteDescription={`Akun ${user.name} dihapus dan tidak dapat masuk lagi. Riwayat transaksi dan catatan yang pernah dibuat tetap tersimpan.`}
                >
                    <Tooltip>
                        <TooltipTrigger asChild>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                aria-label={`Reset kata sandi ${user.name}`}
                                onClick={() => setResetTarget(user)}
                            >
                                <KeyRound
                                    className="size-4"
                                    strokeWidth={1.75}
                                />
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent>Reset kata sandi</TooltipContent>
                    </Tooltip>
                    {user.is_self ? (
                        // Menjaga kolom aksi tetap sejajar; akun sendiri tidak bisa dinonaktifkan.
                        <span aria-hidden="true" className="size-9" />
                    ) : (
                        <Tooltip>
                            <TooltipTrigger asChild>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    aria-label={`${user.is_active ? 'Nonaktifkan' : 'Aktifkan'} ${user.name}`}
                                    onClick={() => setStatusTarget(user)}
                                    className={
                                        user.is_active
                                            ? 'text-destructive hover:bg-destructive/10 hover:text-destructive'
                                            : undefined
                                    }
                                >
                                    {user.is_active ? (
                                        <UserX
                                            className="size-4"
                                            strokeWidth={1.75}
                                        />
                                    ) : (
                                        <UserCheck
                                            className="size-4"
                                            strokeWidth={1.75}
                                        />
                                    )}
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent>
                                {user.is_active ? 'Nonaktifkan' : 'Aktifkan'}
                            </TooltipContent>
                        </Tooltip>
                    )}
                </RowActions>
            ),
        },
    ];

    return (
        <StaffLayout
            breadcrumbs={[
                { title: 'Pengguna', href: '#' },
                {
                    title: 'Guru & Staf',
                    href: StaffUserController.index(),
                },
            ]}
        >
            <Head title="Guru & Staf" />

            <div className="space-y-4">
                <PageHeader
                    title="Guru & Staf"
                    description="Buku induk pendidik dan tenaga kependidikan beserta akun masuknya."
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
                                Cetak daftar
                            </Button>
                            <Button type="button" onClick={openCreate}>
                                <UserPlus
                                    className="size-4"
                                    strokeWidth={1.75}
                                />
                                Tambah pegawai
                            </Button>
                        </>
                    }
                />

                <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
                    <Input
                        type="search"
                        value={search}
                        onChange={(event) => changeSearch(event.target.value)}
                        placeholder="Cari nama, username, HP, NIP, NUPTK, NIK"
                        aria-label="Cari pengguna staf"
                        className="w-full sm:max-w-xs"
                    />
                    <Select
                        value={
                            filters.unit === null ? ALL : String(filters.unit)
                        }
                        onValueChange={(value) =>
                            visit({
                                unit: value === ALL ? null : Number(value),
                                page: 1,
                            })
                        }
                    >
                        <SelectTrigger
                            className="w-full sm:w-44"
                            aria-label="Saring unit"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL}>Semua unit</SelectItem>
                            {unitOptions.map((unit) => (
                                <SelectItem
                                    key={unit.id}
                                    value={String(unit.id)}
                                >
                                    {unit.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <Select
                        value={
                            filters.assignment === '' ? ALL : filters.assignment
                        }
                        onValueChange={(value) =>
                            visit({
                                assignment: value === ALL ? '' : value,
                                page: 1,
                            })
                        }
                    >
                        <SelectTrigger
                            className="w-full sm:w-52"
                            aria-label="Saring tipe penugasan"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL}>
                                Semua tipe penugasan
                            </SelectItem>
                            {assignmentOptions.map((option) => (
                                <SelectItem
                                    key={option.value}
                                    value={option.value}
                                >
                                    {option.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <Select
                        value={filters.role === '' ? ALL : filters.role}
                        onValueChange={(value) =>
                            visit({ role: value === ALL ? '' : value, page: 1 })
                        }
                    >
                        <SelectTrigger
                            className="w-full sm:w-48"
                            aria-label="Saring peran"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL}>Semua peran</SelectItem>
                            {roleOptions.map((role) => (
                                <SelectItem key={role.value} value={role.value}>
                                    {role.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <Select
                        value={filters.status === '' ? ALL : filters.status}
                        onValueChange={(value) =>
                            visit({
                                status: value === ALL ? '' : value,
                                page: 1,
                            })
                        }
                    >
                        <SelectTrigger
                            className="w-full sm:w-40"
                            aria-label="Saring status"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL}>Semua status</SelectItem>
                            <SelectItem value="active">Aktif</SelectItem>
                            <SelectItem value="inactive">Nonaktif</SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div className="flex flex-wrap items-center gap-2 print:hidden">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() =>
                            setSelectedIds(allSelected ? [] : selectableIds)
                        }
                        disabled={selectableIds.length === 0}
                    >
                        {allSelected
                            ? 'Batal pilih'
                            : 'Pilih semua di halaman ini'}
                    </Button>
                    {selectedIds.length > 0 && (
                        <Button
                            type="button"
                            variant="destructive"
                            size="sm"
                            onClick={() => setBulkOpen(true)}
                        >
                            <Trash2 className="size-4" strokeWidth={1.75} />
                            Hapus terpilih ({selectedIds.length})
                        </Button>
                    )}
                    <span className="ml-auto text-sm text-muted-foreground">
                        {users.meta.total} pegawai terdaftar
                    </span>
                </div>

                {loadFailed ? (
                    <ErrorState
                        description="Daftar pengguna staf tidak dapat dimuat. Periksa koneksi lalu coba lagi."
                        onRetry={() => visit({})}
                    />
                ) : (
                    <DataTable
                        columns={columns}
                        data={users.data}
                        rowKey={(user) => user.id}
                        loading={loading}
                        sort={{
                            key: filters.sort,
                            direction: filters.direction,
                        }}
                        onSortChange={(key, direction) =>
                            visit({ sort: key, direction, page: 1 })
                        }
                        pagination={{
                            currentPage: users.meta.current_page,
                            lastPage: users.meta.last_page,
                            perPage: users.meta.per_page,
                            total: users.meta.total,
                            from: users.meta.from,
                            to: users.meta.to,
                        }}
                        onPageChange={(page) => visit({ page })}
                        empty={
                            hasFilters
                                ? {
                                      title: 'Tidak ada pegawai yang cocok',
                                      description:
                                          'Ubah kata kunci atau saringan untuk melihat staf lain.',
                                  }
                                : {
                                      title: 'Belum ada data pegawai',
                                      description:
                                          'Tambahkan guru dan staf agar mereka dapat masuk sesuai perannya.',
                                      action: (
                                          <Button
                                              type="button"
                                              onClick={openCreate}
                                          >
                                              <UserPlus
                                                  className="size-4"
                                                  strokeWidth={1.75}
                                              />
                                              Tambah pegawai
                                          </Button>
                                      ),
                                  }
                        }
                    />
                )}
            </div>

            <FormDialog
                open={formOpen}
                onOpenChange={setFormOpen}
                title={
                    editing ? 'Ubah data pegawai' : 'Tambah pegawai / guru baru'
                }
                description="Biodata pendidik dan tenaga kependidikan beserta akun masuknya."
                onSubmit={submit}
                processing={form.processing}
                submitLabel="Simpan profil pegawai"
                contentClassName="sm:max-w-3xl"
            >
                <FieldSelect
                    id="staff-unit"
                    label="Unit penempatan"
                    value={
                        form.data.unit_id !== null
                            ? String(form.data.unit_id)
                            : ALL
                    }
                    onChange={(value) =>
                        form.setData(
                            'unit_id',
                            value === ALL ? null : Number(value),
                        )
                    }
                    options={[
                        { value: ALL, label: 'Yayasan (semua unit)' },
                        ...unitOptions.map((u) => ({
                            value: String(u.id),
                            label: u.name,
                        })),
                    ]}
                    error={form.errors.unit_id}
                />

                <FormSection title="1. Identitas pribadi & gelar">
                    <div className="grid gap-4 sm:grid-cols-[8rem_1fr_9rem]">
                        <Field
                            id="staff-prefix"
                            label="Gelar depan"
                            value={form.data.title_prefix}
                            onChange={(v) => form.setData('title_prefix', v)}
                            error={form.errors.title_prefix}
                            placeholder="Dr."
                        />
                        <Field
                            id="staff-name"
                            label="Nama lengkap"
                            value={form.data.name}
                            onChange={(v) => form.setData('name', v)}
                            error={form.errors.name}
                            required
                        />
                        <Field
                            id="staff-suffix"
                            label="Gelar belakang"
                            value={form.data.title_suffix}
                            onChange={(v) => form.setData('title_suffix', v)}
                            error={form.errors.title_suffix}
                            placeholder="S.Pd"
                        />
                    </div>
                    <div className="grid gap-4 sm:grid-cols-3">
                        <Field
                            id="staff-nik"
                            label="NIK"
                            value={form.data.nik}
                            onChange={(v) => form.setData('nik', v)}
                            error={form.errors.nik}
                            inputMode="numeric"
                            maxLength={16}
                        />
                        <Field
                            id="staff-nuptk"
                            label="NUPTK (jika ada)"
                            value={form.data.nuptk}
                            onChange={(v) => form.setData('nuptk', v)}
                            error={form.errors.nuptk}
                            inputMode="numeric"
                            maxLength={16}
                        />
                        <Field
                            id="staff-nip"
                            label="NIP (PNS / yayasan)"
                            value={form.data.nip}
                            onChange={(v) => form.setData('nip', v)}
                            error={form.errors.nip}
                        />
                    </div>
                    <div className="grid gap-4 sm:grid-cols-3">
                        <FieldSelect
                            id="staff-gender"
                            label="Jenis kelamin"
                            value={
                                form.data.gender === '' ? ALL : form.data.gender
                            }
                            onChange={(v) =>
                                form.setData('gender', v === ALL ? '' : v)
                            }
                            options={[
                                { value: ALL, label: 'Pilih' },
                                { value: 'L', label: 'Laki-laki' },
                                { value: 'P', label: 'Perempuan' },
                            ]}
                            error={form.errors.gender}
                        />
                        <Field
                            id="staff-birth-place"
                            label="Tempat lahir"
                            value={form.data.birth_place}
                            onChange={(v) => form.setData('birth_place', v)}
                            error={form.errors.birth_place}
                        />
                        <div className="grid gap-2">
                            <Label htmlFor="staff-birth-date">
                                Tanggal lahir
                            </Label>
                            <DateInput
                                id="staff-birth-date"
                                value={form.data.birth_date}
                                onChange={(e) =>
                                    form.setData('birth_date', e.target.value)
                                }
                            />
                            <InputError message={form.errors.birth_date} />
                        </div>
                    </div>
                    <Field
                        id="staff-address"
                        label="Alamat domisili"
                        value={form.data.address}
                        onChange={(v) => form.setData('address', v)}
                        error={form.errors.address}
                    />
                </FormSection>

                <FormSection title="2. Status kepegawaian & gaji">
                    <div className="grid gap-4 sm:grid-cols-3">
                        <FieldSelect
                            id="staff-assignment"
                            label="Tipe penugasan"
                            value={form.data.assignment_type}
                            onChange={(v) => form.setData('assignment_type', v)}
                            options={assignmentOptions}
                            error={form.errors.assignment_type}
                        />
                        <FieldSelect
                            id="staff-employment"
                            label="Status ikatan kerja"
                            value={form.data.employment_status}
                            onChange={(v) =>
                                form.setData('employment_status', v)
                            }
                            options={employmentOptions}
                            error={form.errors.employment_status}
                        />
                        <Field
                            id="staff-position"
                            label="Jabatan (opsional)"
                            value={form.data.position}
                            onChange={(v) => form.setData('position', v)}
                            error={form.errors.position}
                        />
                    </div>
                    <div className="grid gap-4 sm:grid-cols-3">
                        <Field
                            id="staff-salary"
                            label="Gaji pokok (Rp)"
                            value={form.data.base_salary}
                            onChange={(v) => form.setData('base_salary', v)}
                            error={form.errors.base_salary}
                            inputMode="numeric"
                        />
                        <Field
                            id="staff-allowance"
                            label="Tunjangan (Rp)"
                            value={form.data.allowance}
                            onChange={(v) => form.setData('allowance', v)}
                            error={form.errors.allowance}
                            inputMode="numeric"
                        />
                        <Field
                            id="staff-hourly"
                            label="Honor per JP (Rp)"
                            value={form.data.hourly_rate}
                            onChange={(v) => form.setData('hourly_rate', v)}
                            error={form.errors.hourly_rate}
                            inputMode="numeric"
                        />
                    </div>
                </FormSection>

                <FormSection title="3. Akun masuk">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field
                            id="staff-username"
                            label="Username"
                            value={form.data.username}
                            onChange={(v) => form.setData('username', v)}
                            error={form.errors.username}
                            required
                            autoCapitalize="none"
                        />
                        <Field
                            id="staff-password"
                            label={
                                editing
                                    ? 'Kata sandi baru (kosongkan bila tetap)'
                                    : 'Kata sandi (kosongkan untuk dibuat otomatis)'
                            }
                            value={form.data.password}
                            onChange={(v) => form.setData('password', v)}
                            error={form.errors.password}
                            type="password"
                            autoComplete="new-password"
                        />
                        <Field
                            id="staff-phone"
                            label="Nomor HP / WhatsApp"
                            value={form.data.phone}
                            onChange={(v) => form.setData('phone', v)}
                            error={form.errors.phone}
                            type="tel"
                        />
                        <Field
                            id="staff-email"
                            label="Email"
                            value={form.data.email}
                            onChange={(v) => form.setData('email', v)}
                            error={form.errors.email}
                            type="email"
                        />
                    </div>
                    <fieldset className="grid gap-2">
                        <legend className="mb-1 text-sm font-medium">
                            Peran (hak akses)
                        </legend>
                        <div className="grid gap-2 sm:grid-cols-3">
                            {roleOptions.map((role) => {
                                const id = `staff-role-${role.value}`;

                                return (
                                    <div
                                        key={role.value}
                                        className="flex items-center gap-2"
                                    >
                                        <Checkbox
                                            id={id}
                                            checked={form.data.roles.includes(
                                                role.value,
                                            )}
                                            onCheckedChange={(checked) =>
                                                toggleRole(
                                                    role.value,
                                                    checked === true,
                                                )
                                            }
                                        />
                                        <Label
                                            htmlFor={id}
                                            className="font-normal"
                                        >
                                            {role.label}
                                        </Label>
                                    </div>
                                );
                            })}
                        </div>
                        <InputError message={rolesError} />
                    </fieldset>
                </FormSection>

                <FormSection title="4. Pas foto">
                    <div className="grid gap-2">
                        <Label htmlFor="staff-photo">
                            Foto (JPG/PNG/WebP, maks 2 MB)
                        </Label>
                        <Input
                            id="staff-photo"
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            onChange={(e) =>
                                form.setData(
                                    'photo',
                                    e.target.files?.[0] ?? null,
                                )
                            }
                        />
                        <InputError message={form.errors.photo} />
                    </div>
                </FormSection>
            </FormDialog>

            <ConfirmDialog
                open={bulkOpen}
                onOpenChange={setBulkOpen}
                title={`Hapus ${selectedIds.length} data pegawai?`}
                description="Akun terpilih dihapus dan tidak dapat masuk lagi. Akun Anda sendiri dan admin aktif terakhir otomatis dilewati."
                confirmLabel="Hapus terpilih"
                variant="destructive"
                loading={acting}
                onConfirm={destroySelected}
            />

            <ConfirmDialog
                open={statusTarget !== null}
                onOpenChange={(open) => !open && setStatusTarget(null)}
                title={
                    statusTarget?.is_active
                        ? 'Nonaktifkan akun staf?'
                        : 'Aktifkan akun staf?'
                }
                description={
                    statusTarget?.is_active
                        ? `${statusTarget.name} akan langsung keluar dan tidak dapat masuk sampai akunnya diaktifkan kembali.`
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
                title="Reset kata sandi?"
                description={`Kata sandi ${resetTarget?.name ?? ''} diganti dengan kata sandi acak baru. Kata sandi lama tidak berlaku lagi.`}
                confirmLabel="Reset kata sandi"
                variant="default"
                loading={acting}
                onConfirm={confirmReset}
            />

            <Dialog
                open={credentials !== null}
                onOpenChange={(open) => !open && setCredentials(null)}
            >
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>
                            Kata sandi untuk {credentials?.name}
                        </DialogTitle>
                        <DialogDescription>
                            Hanya ditampilkan sekali. Salin atau catat sebelum
                            menutup, lalu serahkan langsung ke pemilik akun.
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
                            onClick={() => setCredentials(null)}
                        >
                            Selesai
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </StaffLayout>
    );
}

function FormSection({
    title,
    children,
}: {
    title: string;
    children: ReactNode;
}) {
    return (
        <fieldset className="grid gap-4 rounded-lg border border-border p-4">
            <legend className="px-1 text-sm font-semibold">{title}</legend>
            {children}
        </fieldset>
    );
}

function Field({
    id,
    label,
    value,
    onChange,
    error,
    ...rest
}: {
    id: string;
    label: string;
    value: string;
    onChange: (value: string) => void;
    error?: string;
} & Omit<ComponentProps<typeof Input>, 'id' | 'value' | 'onChange'>) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>{label}</Label>
            <Input
                id={id}
                value={value}
                onChange={(e) => onChange(e.target.value)}
                autoComplete="off"
                {...rest}
            />
            <InputError message={error} />
        </div>
    );
}

function FieldSelect({
    id,
    label,
    value,
    onChange,
    options,
    error,
}: {
    id: string;
    label: string;
    value: string;
    onChange: (value: string) => void;
    options: { value: string; label: string }[];
    error?: string;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>{label}</Label>
            <Select value={value} onValueChange={onChange}>
                <SelectTrigger id={id}>
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    {options.map((option) => (
                        <SelectItem key={option.value} value={option.value}>
                            {option.label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
            <InputError message={error} />
        </div>
    );
}
