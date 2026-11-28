import { Head, router, useForm } from '@inertiajs/react';
import { Plus, Power, PowerOff } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { useState } from 'react';
import StudentDiscountController from '@/actions/App/Http/Controllers/Admin/StudentDiscountController';
import type { DataTableColumn } from '@/components/app/data-table';
import { DataTable } from '@/components/app/data-table';
import { DateInput } from '@/components/app/date-input';
import { DateText } from '@/components/app/date-text';
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

type DiscountRow = {
    id: number;
    student_id: number;
    student_name: string;
    student_nis: string;
    unit_code: string;
    fee_type_id: number;
    fee_type_name: string;
    kind: 'percent' | 'fixed';
    kind_label: string;
    value: number;
    valid_from: string;
    valid_until: string | null;
    reason: string;
    is_active: boolean;
};

type Props = {
    discounts: PaginatedResource<DiscountRow>;
    students: { id: number; nis: string; name: string }[];
    feeTypes: { id: number; name: string }[];
    kinds: { value: string; label: string }[];
    filters: { search: string; fee_type_id: string; status: string };
    canManage: boolean;
};

const ALL_VALUE = 'semua';

export default function DiscountsIndex({
    discounts,
    students,
    feeTypes,
    kinds,
    filters,
    canManage,
}: Props) {
    const [search, setSearch] = useState(filters.search);
    const [loading, setLoading] = useState(false);
    const [formOpen, setFormOpen] = useState(false);
    const [editing, setEditing] = useState<DiscountRow | null>(null);

    const form = useForm({
        student_id: students[0] ? String(students[0].id) : '',
        fee_type_id: feeTypes[0] ? String(feeTypes[0].id) : '',
        kind: 'percent' as 'percent' | 'fixed',
        value: '50',
        valid_from: '',
        valid_until: '',
        reason: '',
    });

    function visitFilters(next: Partial<Props['filters']>, page?: number) {
        const merged = {
            search,
            fee_type_id: filters.fee_type_id,
            status: filters.status,
            ...next,
        };

        router.get(
            StudentDiscountController.index.url({
                query: {
                    search: merged.search || undefined,
                    fee_type_id: merged.fee_type_id || undefined,
                    status: merged.status || undefined,
                    page: page && page > 1 ? page : undefined,
                },
            }),
            {},
            {
                preserveState: true,
                replace: true,
                onStart: () => setLoading(true),
                onFinish: () => setLoading(false),
            },
        );
    }

    function openCreate() {
        setEditing(null);
        form.clearErrors();
        form.setData({
            student_id: students[0] ? String(students[0].id) : '',
            fee_type_id: feeTypes[0] ? String(feeTypes[0].id) : '',
            kind: 'percent',
            value: '50',
            valid_from: '',
            valid_until: '',
            reason: '',
        });
        setFormOpen(true);
    }

    function openEdit(row: DiscountRow) {
        setEditing(row);
        form.clearErrors();
        form.setData({
            student_id: String(row.student_id),
            fee_type_id: String(row.fee_type_id),
            kind: row.kind,
            value: String(row.value),
            valid_from: row.valid_from,
            valid_until: row.valid_until ?? '',
            reason: row.reason,
        });
        setFormOpen(true);
    }

    function submitForm(e: FormEvent<HTMLFormElement>) {
        e.preventDefault();
        form.transform((data) => ({
            ...data,
            student_id: Number(data.student_id),
            fee_type_id: Number(data.fee_type_id),
            value: Number(data.value || 0),
            valid_until: data.valid_until || null,
        }));

        if (editing) {
            form.put(StudentDiscountController.update.url(editing.id), {
                preserveScroll: true,
                onSuccess: () => setFormOpen(false),
            });

            return;
        }

        form.post(StudentDiscountController.store.url(), {
            preserveScroll: true,
            onSuccess: () => setFormOpen(false),
        });
    }

    function handleToggle(row: DiscountRow) {
        router.patch(
            StudentDiscountController.toggle.url(row.id),
            { is_active: !row.is_active },
            { preserveScroll: true },
        );
    }

    const columns: DataTableColumn<DiscountRow>[] = [
        {
            key: 'student',
            label: 'Siswa',
            render: (row) => (
                <div>
                    <div className="font-medium">{row.student_name}</div>
                    <div className="text-xs text-muted-foreground tabular-nums">
                        {row.student_nis} · {row.unit_code}
                    </div>
                </div>
            ),
        },
        {
            key: 'fee_type',
            label: 'Jenis tagihan',
            render: (row) => row.fee_type_name,
        },
        {
            key: 'value',
            label: 'Potongan',
            align: 'right',
            render: (row) =>
                row.kind === 'percent' ? (
                    <span className="font-medium tabular-nums">
                        {row.value}%
                    </span>
                ) : (
                    <MoneyText amount={row.value} className="font-medium" />
                ),
        },
        {
            key: 'validity',
            label: 'Masa berlaku',
            render: (row) => (
                <div className="text-xs text-muted-foreground">
                    <DateText value={row.valid_from} /> s.d.{' '}
                    {row.valid_until ? (
                        <DateText value={row.valid_until} />
                    ) : (
                        'Seterusnya'
                    )}
                </div>
            ),
        },
        {
            key: 'reason',
            label: 'Alasan',
            render: (row) => row.reason,
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
                      render: (row: DiscountRow) => (
                          <RowActions
                              itemName={row.student_name}
                              onEdit={() => openEdit(row)}
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
                                                  ? `Nonaktifkan ${row.student_name}`
                                                  : `Aktifkan ${row.student_name}`
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

    return (
        <StaffLayout
            breadcrumbs={[
                {
                    title: 'Keuangan Siswa',
                    href: StudentDiscountController.index.url(),
                },
                {
                    title: 'Diskon & beasiswa',
                    href: StudentDiscountController.index.url(),
                },
            ]}
        >
            <Head title="Diskon & beasiswa" />

            <div className="space-y-6">
                <PageHeader
                    title="Diskon & beasiswa"
                    description="Potongan berulang per siswa per jenis tagihan yang otomatis tercatat sebagai penyesuaian saat penerbitan."
                    actions={
                        canManage ? (
                            <Button onClick={openCreate}>
                                <Plus className="size-4" strokeWidth={1.75} />
                                Tambah potongan
                            </Button>
                        ) : undefined
                    }
                />

                <div className="flex flex-wrap items-center gap-3">
                    <form
                        className="w-full sm:w-56"
                        onSubmit={(e) => {
                            e.preventDefault();
                            visitFilters({ search }, 1);
                        }}
                    >
                        <Input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Cari nama atau NIS..."
                            aria-label="Cari siswa"
                        />
                    </form>

                    <Select
                        value={filters.fee_type_id || ALL_VALUE}
                        onValueChange={(v) =>
                            visitFilters(
                                { fee_type_id: v === ALL_VALUE ? '' : v },
                                1,
                            )
                        }
                    >
                        <SelectTrigger
                            className="w-full sm:w-44"
                            aria-label="Filter jenis tagihan"
                        >
                            <SelectValue placeholder="Semua jenis" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL_VALUE}>
                                Semua jenis
                            </SelectItem>
                            {feeTypes.map((ft) => (
                                <SelectItem key={ft.id} value={String(ft.id)}>
                                    {ft.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    <Select
                        value={filters.status || ALL_VALUE}
                        onValueChange={(v) =>
                            visitFilters(
                                { status: v === ALL_VALUE ? '' : v },
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
                </div>

                <DataTable
                    columns={columns}
                    data={discounts.data}
                    rowKey={(row) => row.id}
                    loading={loading}
                    pagination={{
                        currentPage: discounts.meta.current_page,
                        lastPage: discounts.meta.last_page,
                        perPage: discounts.meta.per_page,
                        total: discounts.meta.total,
                        from: discounts.meta.from,
                        to: discounts.meta.to,
                    }}
                    onPageChange={(page) => visitFilters({}, page)}
                    empty={{
                        title: 'Belum ada potongan berulang',
                        description:
                            'Tambahkan diskon atau beasiswa siswa agar otomatis diterapkan saat penerbitan tagihan.',
                    }}
                />
            </div>

            <FormDialog
                open={formOpen}
                onOpenChange={setFormOpen}
                title={editing ? 'Ubah potongan' : 'Tambah potongan'}
                description="Potongan berlaku otomatis untuk tagihan yang diterbitkan dalam rentang masa berlaku."
                onSubmit={submitForm}
                processing={form.processing}
                submitLabel={editing ? 'Simpan perubahan' : 'Simpan'}
            >
                <div className="space-y-4">
                    <div className="space-y-1.5">
                        <Label htmlFor="disc-student">Siswa</Label>
                        <Select
                            value={form.data.student_id}
                            onValueChange={(v) => form.setData('student_id', v)}
                        >
                            <SelectTrigger id="disc-student">
                                <SelectValue placeholder="Pilih siswa" />
                            </SelectTrigger>
                            <SelectContent>
                                {students.map((s) => (
                                    <SelectItem key={s.id} value={String(s.id)}>
                                        {s.name} ({s.nis})
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={form.errors.student_id} />
                    </div>

                    <div className="space-y-1.5">
                        <Label htmlFor="disc-fee-type">Jenis tagihan</Label>
                        <Select
                            value={form.data.fee_type_id}
                            onValueChange={(v) =>
                                form.setData('fee_type_id', v)
                            }
                        >
                            <SelectTrigger id="disc-fee-type">
                                <SelectValue placeholder="Pilih jenis" />
                            </SelectTrigger>
                            <SelectContent>
                                {feeTypes.map((ft) => (
                                    <SelectItem
                                        key={ft.id}
                                        value={String(ft.id)}
                                    >
                                        {ft.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={form.errors.fee_type_id} />
                    </div>

                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div className="space-y-1.5">
                            <Label htmlFor="disc-kind">Bentuk</Label>
                            <Select
                                value={form.data.kind}
                                onValueChange={(v) =>
                                    form.setData(
                                        'kind',
                                        v as 'percent' | 'fixed',
                                    )
                                }
                            >
                                <SelectTrigger id="disc-kind">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {kinds.map((k) => (
                                        <SelectItem
                                            key={k.value}
                                            value={k.value}
                                        >
                                            {k.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={form.errors.kind} />
                        </div>

                        <div className="space-y-1.5">
                            <Label htmlFor="disc-value">
                                {form.data.kind === 'percent'
                                    ? 'Persen (1–100)'
                                    : 'Nominal (Rp)'}
                            </Label>
                            <Input
                                id="disc-value"
                                type="number"
                                min={1}
                                max={
                                    form.data.kind === 'percent'
                                        ? 100
                                        : undefined
                                }
                                value={form.data.value}
                                onChange={(e) =>
                                    form.setData('value', e.target.value)
                                }
                                className="text-right tabular-nums"
                                required
                            />
                            <InputError message={form.errors.value} />
                        </div>
                    </div>

                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div className="space-y-1.5">
                            <Label htmlFor="disc-from">Berlaku mulai</Label>
                            <DateInput
                                id="disc-from"
                                value={form.data.valid_from}
                                onChange={(e) =>
                                    form.setData('valid_from', e.target.value)
                                }
                            />
                            <InputError message={form.errors.valid_from} />
                        </div>
                        <div className="space-y-1.5">
                            <Label htmlFor="disc-until">
                                Berlaku sampai (opsional)
                            </Label>
                            <DateInput
                                id="disc-until"
                                value={form.data.valid_until}
                                onChange={(e) =>
                                    form.setData('valid_until', e.target.value)
                                }
                            />
                            <InputError message={form.errors.valid_until} />
                        </div>
                    </div>

                    <div className="space-y-1.5">
                        <Label htmlFor="disc-reason">Alasan</Label>
                        <Input
                            id="disc-reason"
                            value={form.data.reason}
                            onChange={(e) =>
                                form.setData('reason', e.target.value)
                            }
                            placeholder="Mis. Beasiswa tahfidz atau anak guru"
                            maxLength={255}
                            required
                        />
                        <InputError message={form.errors.reason} />
                    </div>
                </div>
            </FormDialog>
        </StaffLayout>
    );
}

DiscountsIndex.layout = (page: ReactNode) => page;
