import { Head, router, useForm } from '@inertiajs/react';
import { Plus, Power, PowerOff } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { useState } from 'react';
import FeeTypeController from '@/actions/App/Http/Controllers/Admin/FeeTypeController';
import type { DataTableColumn } from '@/components/app/data-table';
import { DataTable } from '@/components/app/data-table';
import { ErrorState } from '@/components/app/error-state';
import { FormDialog } from '@/components/app/form-dialog';
import { MoneyText } from '@/components/app/money-text';
import { PageHeader } from '@/components/app/page-header';
import { RowActions } from '@/components/app/row-actions';
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
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import StaffLayout from '@/layouts/staff-layout';
import type { PaginatedResource } from '@/types';

type FeeTypeRow = {
    id: number;
    unit_id: number | null;
    unit_name: string;
    name: string;
    category: string;
    recurrence: 'monthly' | 'once';
    recurrence_label: string;
    default_amount: number;
    is_active: boolean;
};

type UnitOption = { id: number; code: string; name: string };
type RecurrenceOption = { value: string; label: string };

type Filters = {
    search: string;
    unit_id: string;
    recurrence: string;
    status: string;
};

type Props = {
    feeTypes: PaginatedResource<FeeTypeRow>;
    units: UnitOption[];
    recurrences: RecurrenceOption[];
    filters: Filters;
    canManage: boolean;
};

type FeeTypeForm = {
    unit_id: string;
    name: string;
    category: string;
    recurrence: 'monthly' | 'once';
    default_amount: string;
};

const ALL_VALUE = 'semua';
const CATEGORY_PRESETS = [
    'SPP',
    'Uang Pangkal',
    'Kegiatan',
    'Seragam',
    'Buku',
    'Ujian',
];

export default function FeeTypesIndex({
    feeTypes,
    units,
    recurrences,
    filters,
    canManage,
}: Props) {
    const [search, setSearch] = useState(filters.search);
    const [loading, setLoading] = useState(false);
    const [loadFailed, setLoadFailed] = useState(false);
    const [formOpen, setFormOpen] = useState(false);
    const [editing, setEditing] = useState<FeeTypeRow | null>(null);

    const form = useForm<FeeTypeForm>({
        unit_id: ALL_VALUE,
        name: '',
        category: 'SPP',
        recurrence: 'monthly',
        default_amount: '0',
    });

    function visitFilters(next: Partial<Filters>, page?: number) {
        const merged = {
            search,
            unit_id: filters.unit_id,
            recurrence: filters.recurrence,
            status: filters.status,
            ...next,
        };

        router.get(
            FeeTypeController.index.url({
                query: {
                    search: merged.search || undefined,
                    unit_id: merged.unit_id || undefined,
                    recurrence: merged.recurrence || undefined,
                    status: merged.status || undefined,
                    page: page && page > 1 ? page : undefined,
                },
            }),
            {},
            {
                preserveState: true,
                replace: true,
                onStart: () => {
                    setLoading(true);
                    setLoadFailed(false);
                },
                onFinish: () => setLoading(false),
                onError: () => setLoadFailed(true),
            },
        );
    }

    function openCreate() {
        setEditing(null);
        form.clearErrors();
        form.setData({
            unit_id: filters.unit_id || ALL_VALUE,
            name: '',
            category: 'SPP',
            recurrence: 'monthly',
            default_amount: '0',
        });
        setFormOpen(true);
    }

    function openEdit(row: FeeTypeRow) {
        setEditing(row);
        form.clearErrors();
        form.setData({
            unit_id: row.unit_id !== null ? String(row.unit_id) : ALL_VALUE,
            name: row.name,
            category: row.category,
            recurrence: row.recurrence,
            default_amount: String(row.default_amount),
        });
        setFormOpen(true);
    }

    function submitForm(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        const payload = {
            unit_id:
                form.data.unit_id === ALL_VALUE
                    ? null
                    : Number(form.data.unit_id),
            name: form.data.name,
            category: form.data.category,
            recurrence: form.data.recurrence,
            default_amount:
                form.data.default_amount.trim() === ''
                    ? 0
                    : Number(form.data.default_amount),
        };

        form.transform(() => payload);

        if (editing) {
            form.put(FeeTypeController.update.url(editing.id), {
                preserveScroll: true,
                onSuccess: () => setFormOpen(false),
            });

            return;
        }

        form.post(FeeTypeController.store.url(), {
            preserveScroll: true,
            onSuccess: () => setFormOpen(false),
        });
    }

    function handleToggle(row: FeeTypeRow) {
        router.patch(
            FeeTypeController.toggle.url(row.id),
            { is_active: !row.is_active },
            { preserveScroll: true },
        );
    }

    function handleDelete(row: FeeTypeRow) {
        router.delete(FeeTypeController.destroy.url(row.id), {
            preserveScroll: true,
        });
    }

    const columns: DataTableColumn<FeeTypeRow>[] = [
        {
            key: 'name',
            label: 'Nama jenis tagihan',
            render: (row) => (
                <div className="font-medium text-foreground">{row.name}</div>
            ),
        },
        {
            key: 'category',
            label: 'Kategori',
            render: (row) => (
                <StatusBadge variant="muted">{row.category}</StatusBadge>
            ),
        },
        {
            key: 'unit',
            label: 'Unit',
            render: (row) => (
                <span className="text-sm text-muted-foreground">
                    {row.unit_name}
                </span>
            ),
        },
        {
            key: 'recurrence',
            label: 'Sifat',
            align: 'center',
            render: (row) => (
                <StatusBadge variant="muted">
                    {row.recurrence_label}
                </StatusBadge>
            ),
        },
        {
            key: 'default_amount',
            label: 'Nominal default',
            align: 'right',
            render: (row) => (
                <MoneyText
                    amount={row.default_amount}
                    className="font-medium"
                />
            ),
        },
        {
            key: 'status',
            label: 'Status',
            align: 'center',
            render: (row) => (
                <StatusBadge variant={row.is_active ? 'success' : 'muted'}>
                    {row.is_active ? 'Aktif' : 'Nonaktif'}
                </StatusBadge>
            ),
        },
        ...(canManage
            ? [
                  {
                      key: 'actions',
                      label: 'Aksi',
                      align: 'center' as const,
                      render: (row: FeeTypeRow) => (
                          <RowActions
                              itemName={row.name}
                              onEdit={() => openEdit(row)}
                              onDelete={() => handleDelete(row)}
                              deleteTitle="Hapus jenis tagihan?"
                              deleteDescription={`Jenis tagihan "${row.name}" akan dihapus permanen jika belum pernah dipakai pada tagihan.`}
                          >
                              <Tooltip>
                                  <TooltipTrigger asChild>
                                      <Button
                                          type="button"
                                          variant="ghost"
                                          size="icon"
                                          onClick={() => handleToggle(row)}
                                          aria-label={
                                              row.is_active
                                                  ? `Nonaktifkan ${row.name}`
                                                  : `Aktifkan ${row.name}`
                                          }
                                      >
                                          {row.is_active ? (
                                              <PowerOff
                                                  className="size-4 text-muted-foreground"
                                                  strokeWidth={1.75}
                                              />
                                          ) : (
                                              <Power
                                                  className="size-4 text-primary"
                                                  strokeWidth={1.75}
                                              />
                                          )}
                                      </Button>
                                  </TooltipTrigger>
                                  <TooltipContent>
                                      {row.is_active
                                          ? 'Nonaktifkan'
                                          : 'Aktifkan'}
                                  </TooltipContent>
                              </Tooltip>
                          </RowActions>
                      ),
                  },
              ]
            : []),
    ];

    const hasActiveFilters =
        filters.search !== '' ||
        filters.unit_id !== '' ||
        filters.recurrence !== '' ||
        filters.status !== '';

    return (
        <StaffLayout
            breadcrumbs={[
                { title: 'Keuangan', href: FeeTypeController.index.url() },
                { title: 'Jenis tagihan', href: FeeTypeController.index.url() },
            ]}
        >
            <Head title="Jenis tagihan" />

            <div className="space-y-6">
                <PageHeader
                    title="Jenis tagihan"
                    description="Master pos tagihan per unit, sifat bulanan atau sekali, dan nominal bawaan."
                    actions={
                        canManage ? (
                            <Button onClick={openCreate}>
                                <Plus className="size-4" strokeWidth={1.75} />
                                Tambah jenis tagihan
                            </Button>
                        ) : undefined
                    }
                />

                <div className="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
                    <form
                        className="flex-1 sm:max-w-xs"
                        onSubmit={(e) => {
                            e.preventDefault();
                            visitFilters({ search }, 1);
                        }}
                    >
                        <Input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Cari nama atau kategori..."
                            aria-label="Cari jenis tagihan"
                        />
                    </form>

                    <Select
                        value={filters.unit_id || ALL_VALUE}
                        onValueChange={(value) =>
                            visitFilters(
                                { unit_id: value === ALL_VALUE ? '' : value },
                                1,
                            )
                        }
                    >
                        <SelectTrigger
                            className="w-full sm:w-44"
                            aria-label="Filter unit"
                        >
                            <SelectValue placeholder="Semua unit" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL_VALUE}>
                                Semua unit
                            </SelectItem>
                            {units.map((unit) => (
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
                        value={filters.recurrence || ALL_VALUE}
                        onValueChange={(value) =>
                            visitFilters(
                                {
                                    recurrence:
                                        value === ALL_VALUE ? '' : value,
                                },
                                1,
                            )
                        }
                    >
                        <SelectTrigger
                            className="w-full sm:w-40"
                            aria-label="Filter sifat tagihan"
                        >
                            <SelectValue placeholder="Semua sifat" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL_VALUE}>
                                Semua sifat
                            </SelectItem>
                            {recurrences.map((item) => (
                                <SelectItem key={item.value} value={item.value}>
                                    {item.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    <Select
                        value={filters.status || ALL_VALUE}
                        onValueChange={(value) =>
                            visitFilters(
                                { status: value === ALL_VALUE ? '' : value },
                                1,
                            )
                        }
                    >
                        <SelectTrigger
                            className="w-full sm:w-40"
                            aria-label="Filter status"
                        >
                            <SelectValue placeholder="Semua status" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL_VALUE}>
                                Semua status
                            </SelectItem>
                            <SelectItem value="active">Aktif</SelectItem>
                            <SelectItem value="inactive">Nonaktif</SelectItem>
                        </SelectContent>
                    </Select>

                    {hasActiveFilters && (
                        <Button
                            type="button"
                            variant="ghost"
                            onClick={() => {
                                setSearch('');
                                visitFilters(
                                    {
                                        search: '',
                                        unit_id: '',
                                        recurrence: '',
                                        status: '',
                                    },
                                    1,
                                );
                            }}
                        >
                            Atur ulang
                        </Button>
                    )}
                </div>

                {loadFailed ? (
                    <ErrorState
                        title="Gagal memuat jenis tagihan"
                        description="Periksa koneksi Anda lalu coba muat ulang daftar jenis tagihan."
                        onRetry={() => visitFilters({})}
                    />
                ) : (
                    <DataTable
                        columns={columns}
                        data={feeTypes.data}
                        rowKey={(row) => row.id}
                        loading={loading}
                        pagination={{
                            currentPage: feeTypes.meta.current_page,
                            lastPage: feeTypes.meta.last_page,
                            perPage: feeTypes.meta.per_page,
                            total: feeTypes.meta.total,
                            from: feeTypes.meta.from,
                            to: feeTypes.meta.to,
                        }}
                        onPageChange={(page) => visitFilters({}, page)}
                        empty={{
                            title: hasActiveFilters
                                ? 'Tidak ada jenis tagihan yang cocok'
                                : 'Belum ada jenis tagihan',
                            description: hasActiveFilters
                                ? 'Ubah kata kunci atau filter untuk melihat jenis tagihan lainnya.'
                                : 'Tambahkan SPP atau pos biaya lain untuk mulai menerbitkan tagihan siswa.',
                            action:
                                !hasActiveFilters && canManage ? (
                                    <Button onClick={openCreate}>
                                        Tambah jenis tagihan
                                    </Button>
                                ) : undefined,
                        }}
                    />
                )}
            </div>

            <FormDialog
                open={formOpen}
                onOpenChange={setFormOpen}
                title={editing ? 'Ubah jenis tagihan' : 'Tambah jenis tagihan'}
                description="Tentukan unit, kategori pos, sifat perulangan, dan nominal default dalam rupiah."
                onSubmit={submitForm}
                processing={form.processing}
                submitLabel={editing ? 'Simpan perubahan' : 'Simpan'}
            >
                <div className="space-y-4">
                    <div className="space-y-1.5">
                        <Label htmlFor="fee-unit">Unit</Label>
                        <Select
                            value={form.data.unit_id}
                            onValueChange={(value) =>
                                form.setData('unit_id', value)
                            }
                        >
                            <SelectTrigger id="fee-unit">
                                <SelectValue placeholder="Pilih unit" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={ALL_VALUE}>
                                    Semua unit
                                </SelectItem>
                                {units.map((unit) => (
                                    <SelectItem
                                        key={unit.id}
                                        value={String(unit.id)}
                                    >
                                        {unit.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={form.errors.unit_id} />
                    </div>

                    <div className="space-y-1.5">
                        <Label htmlFor="fee-name">Nama jenis tagihan</Label>
                        <Input
                            id="fee-name"
                            value={form.data.name}
                            onChange={(e) =>
                                form.setData('name', e.target.value)
                            }
                            placeholder="Mis. SPP Bulanan MI"
                            maxLength={120}
                            required
                        />
                        <InputError message={form.errors.name} />
                    </div>

                    <div className="space-y-1.5">
                        <Label htmlFor="fee-category">Kategori pos</Label>
                        <Input
                            id="fee-category"
                            value={form.data.category}
                            onChange={(e) =>
                                form.setData('category', e.target.value)
                            }
                            placeholder="Mis. SPP"
                            maxLength={30}
                            required
                        />
                        <div className="flex flex-wrap gap-1.5 pt-1">
                            {CATEGORY_PRESETS.map((preset) => (
                                <Button
                                    key={preset}
                                    type="button"
                                    variant={
                                        form.data.category === preset
                                            ? 'secondary'
                                            : 'outline'
                                    }
                                    size="sm"
                                    className="h-7 px-2 text-xs"
                                    onClick={() =>
                                        form.setData('category', preset)
                                    }
                                >
                                    {preset}
                                </Button>
                            ))}
                        </div>
                        <InputError message={form.errors.category} />
                    </div>

                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div className="space-y-1.5">
                            <Label htmlFor="fee-recurrence">Sifat</Label>
                            <Select
                                value={form.data.recurrence}
                                onValueChange={(value) =>
                                    form.setData(
                                        'recurrence',
                                        value as 'monthly' | 'once',
                                    )
                                }
                            >
                                <SelectTrigger id="fee-recurrence">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {recurrences.map((item) => (
                                        <SelectItem
                                            key={item.value}
                                            value={item.value}
                                        >
                                            {item.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={form.errors.recurrence} />
                        </div>

                        <div className="space-y-1.5">
                            <Label htmlFor="fee-amount">
                                Nominal default (Rp)
                            </Label>
                            <Input
                                id="fee-amount"
                                type="number"
                                min={0}
                                step={1000}
                                value={form.data.default_amount}
                                onChange={(e) =>
                                    form.setData(
                                        'default_amount',
                                        e.target.value,
                                    )
                                }
                                className="text-right tabular-nums"
                                required
                            />
                            <InputError message={form.errors.default_amount} />
                        </div>
                    </div>
                </div>
            </FormDialog>
        </StaffLayout>
    );
}

FeeTypesIndex.layout = (page: ReactNode) => page;
