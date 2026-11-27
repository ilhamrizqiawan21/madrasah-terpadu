import { Head, router, useForm } from '@inertiajs/react';
import { Copy, KeyRound, UserCheck, UserPlus, UserX } from 'lucide-react';
import type { FormEvent } from 'react';
import { useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import StaffUserController from '@/actions/App/Http/Controllers/Admin/StaffUserController';
import { ConfirmDialog } from '@/components/app/confirm-dialog';
import type { DataTableColumn } from '@/components/app/data-table';
import { DataTable } from '@/components/app/data-table';
import { DateText } from '@/components/app/date-text';
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
import { dashboard } from '@/routes/admin';
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
};

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
};

export default function StaffUsersIndex({
    users,
    filters,
    roleOptions,
    unitOptions,
}: Props) {
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
                ([, value]) => value !== '' && value !== undefined,
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
            form.put(StaffUserController.update.url(editing.id), options);
        } else {
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

    const hasFilters =
        filters.search !== '' || filters.role !== '' || filters.status !== '';

    const rolesError =
        form.errors.roles ??
        Object.entries(form.errors).find(([key]) =>
            key.startsWith('roles.'),
        )?.[1];

    const columns: DataTableColumn<StaffUser>[] = [
        {
            key: 'name',
            label: 'Nama',
            sortable: true,
            render: (user) => (
                <div className="min-w-40">
                    <div className="font-medium text-foreground">
                        {user.name}
                        {user.is_self && (
                            <span className="ml-1.5 text-xs font-normal text-muted-foreground">
                                (Anda)
                            </span>
                        )}
                    </div>
                    <div className="text-xs text-muted-foreground">
                        {user.username ?? '—'}
                    </div>
                </div>
            ),
        },
        {
            key: 'roles',
            label: 'Peran',
            render: (user) =>
                user.roles.length > 0 ? (
                    <div className="flex flex-wrap gap-1">
                        {user.roles.map((role) => (
                            <StatusBadge key={role.value} variant="muted">
                                {role.label}
                            </StatusBadge>
                        ))}
                    </div>
                ) : (
                    <span className="text-muted-foreground">
                        Belum ada peran
                    </span>
                ),
        },
        {
            key: 'profile',
            label: 'Unit & penugasan',
            render: (user) => {
                const unitCode = user.profile?.unit_code;
                const position = user.profile?.position;
                const nip = user.profile?.nip;
                const homerooms = user.homeroom_classrooms ?? [];

                if (!unitCode && !position && !nip && homerooms.length === 0) {
                    return <span className="text-muted-foreground">—</span>;
                }

                return (
                    <div className="min-w-36 text-sm">
                        {(unitCode || position) && (
                            <div>
                                {[unitCode, position]
                                    .filter(Boolean)
                                    .join(' · ')}
                            </div>
                        )}
                        {nip && (
                            <div className="text-xs text-muted-foreground tabular-nums">
                                NIP {nip}
                            </div>
                        )}
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
            key: 'phone',
            label: 'Nomor HP',
            className: 'tabular-nums whitespace-nowrap',
            render: (user) =>
                user.phone ?? <span className="text-muted-foreground">—</span>,
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
            key: 'last_login_at',
            label: 'Login terakhir',
            sortable: true,
            className: 'whitespace-nowrap',
            render: (user) => (
                <DateText
                    value={user.last_login_at}
                    mode="datetime"
                    fallback="Belum pernah"
                />
            ),
        },
        {
            key: 'actions',
            label: 'Aksi',
            align: 'center',
            render: (user) => (
                <RowActions onEdit={() => openEdit(user)}>
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
                { title: 'Beranda', href: dashboard() },
                {
                    title: 'Pengguna staf',
                    href: StaffUserController.index(),
                },
            ]}
        >
            <Head title="Pengguna staf" />

            <div className="space-y-4">
                <PageHeader
                    title="Pengguna staf"
                    description="Akun guru, bendahara, BK, kepala unit, petugas gerbang, dan kasir kantin."
                    actions={
                        <Button type="button" onClick={openCreate}>
                            <UserPlus className="size-4" strokeWidth={1.75} />
                            Tambah staf
                        </Button>
                    }
                />

                <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
                    <Input
                        type="search"
                        value={search}
                        onChange={(event) => changeSearch(event.target.value)}
                        placeholder="Cari nama, username, atau nomor HP"
                        aria-label="Cari pengguna staf"
                        className="w-full sm:max-w-xs"
                    />
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
                                      title: 'Tidak ada staf yang cocok',
                                      description:
                                          'Ubah kata kunci atau saringan untuk melihat staf lain.',
                                  }
                                : {
                                      title: 'Belum ada akun staf',
                                      description:
                                          'Tambahkan akun staf agar guru dan petugas dapat masuk sesuai perannya.',
                                      action: (
                                          <Button
                                              type="button"
                                              onClick={openCreate}
                                          >
                                              <UserPlus
                                                  className="size-4"
                                                  strokeWidth={1.75}
                                              />
                                              Tambah staf
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
                title={editing ? 'Ubah akun staf' : 'Tambah akun staf'}
                description={
                    editing
                        ? undefined
                        : 'Kata sandi awal dibuat otomatis dan ditampilkan sekali setelah disimpan.'
                }
                onSubmit={submit}
                processing={form.processing}
            >
                <div className="grid gap-2">
                    <Label htmlFor="staff-name">Nama lengkap</Label>
                    <Input
                        id="staff-name"
                        value={form.data.name}
                        onChange={(event) =>
                            form.setData('name', event.target.value)
                        }
                        autoComplete="off"
                        required
                    />
                    <InputError message={form.errors.name} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="staff-username">Username</Label>
                    <Input
                        id="staff-username"
                        value={form.data.username}
                        onChange={(event) =>
                            form.setData('username', event.target.value)
                        }
                        autoComplete="off"
                        autoCapitalize="none"
                        required
                    />
                    <InputError message={form.errors.username} />
                </div>
                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="grid gap-2">
                        <Label htmlFor="staff-phone">Nomor HP (opsional)</Label>
                        <Input
                            id="staff-phone"
                            type="tel"
                            value={form.data.phone}
                            onChange={(event) =>
                                form.setData('phone', event.target.value)
                            }
                            autoComplete="off"
                        />
                        <InputError message={form.errors.phone} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="staff-email">Email (opsional)</Label>
                        <Input
                            id="staff-email"
                            type="email"
                            value={form.data.email}
                            onChange={(event) =>
                                form.setData('email', event.target.value)
                            }
                            autoComplete="off"
                        />
                        <InputError message={form.errors.email} />
                    </div>
                </div>
                <fieldset className="grid gap-2">
                    <legend className="mb-1 text-sm font-medium">Peran</legend>
                    <div className="grid gap-2 sm:grid-cols-2">
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
                                    <Label htmlFor={id} className="font-normal">
                                        {role.label}
                                    </Label>
                                </div>
                            );
                        })}
                    </div>
                    <InputError message={rolesError} />
                </fieldset>
                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="grid gap-2">
                        <Label htmlFor="staff-unit">Unit (opsional)</Label>
                        <Select
                            value={
                                form.data.unit_id !== null
                                    ? String(form.data.unit_id)
                                    : ALL
                            }
                            onValueChange={(value) =>
                                form.setData(
                                    'unit_id',
                                    value === ALL ? null : Number(value),
                                )
                            }
                        >
                            <SelectTrigger id="staff-unit">
                                <SelectValue placeholder="Semua / Yayasan" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={ALL}>
                                    Semua / Yayasan
                                </SelectItem>
                                {unitOptions.map((unit) => (
                                    <SelectItem
                                        key={unit.id}
                                        value={String(unit.id)}
                                    >
                                        {unit.code} · {unit.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={form.errors.unit_id} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="staff-nip">NIP (opsional)</Label>
                        <Input
                            id="staff-nip"
                            value={form.data.nip}
                            onChange={(event) =>
                                form.setData('nip', event.target.value)
                            }
                            autoComplete="off"
                        />
                        <InputError message={form.errors.nip} />
                    </div>
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="staff-position">Jabatan (opsional)</Label>
                    <Input
                        id="staff-position"
                        value={form.data.position}
                        onChange={(event) =>
                            form.setData('position', event.target.value)
                        }
                        autoComplete="off"
                    />
                    <InputError message={form.errors.position} />
                </div>
            </FormDialog>

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
