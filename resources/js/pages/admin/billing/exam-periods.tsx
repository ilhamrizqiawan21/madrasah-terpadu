import { Head, router, useForm } from '@inertiajs/react';
import { CheckCircle2, Lock, Plus, Trash2 } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import ExamPeriodController from '@/actions/App/Http/Controllers/Admin/ExamPeriodController';
import type { DataTableColumn } from '@/components/app/data-table';
import { DataTable } from '@/components/app/data-table';
import { DateInput } from '@/components/app/date-input';
import { DateText } from '@/components/app/date-text';
import { FormDialog } from '@/components/app/form-dialog';
import { PageHeader } from '@/components/app/page-header';
import { RowActions } from '@/components/app/row-actions';
import type { StatusBadgeVariant } from '@/components/app/status-badge';
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
import { dashboard } from '@/routes/admin';
import type { PaginatedResource } from '@/types';

type RequirementRow = {
    id: number;
    fee_type_id: number;
    fee_type_name: string;
    invoice_scope: { value: 'up_to_period' | 'all_open'; label: string };
    period_until: string | null;
};

type ExamPeriodRow = {
    id: number;
    unit_id: number;
    unit_code: string;
    unit_name: string;
    name: string;
    delivery: { value: 'cbt' | 'paper'; label: string };
    starts_on: string;
    ends_on: string;
    status: { value: 'draft' | 'active' | 'closed'; label: string };
    requirements: RequirementRow[];
};

type RequirementInput = {
    fee_type_id: string;
    invoice_scope: 'up_to_period' | 'all_open';
    period_until: string;
};

type Props = {
    periods: PaginatedResource<ExamPeriodRow>;
    units: { id: number; code: string; name: string }[];
    feeTypes: {
        id: number;
        unit_id: number | null;
        name: string;
        recurrence: string;
    }[];
    deliveries: { value: 'cbt' | 'paper'; label: string }[];
    statuses: { value: 'draft' | 'active' | 'closed'; label: string }[];
    scopes: { value: 'up_to_period' | 'all_open'; label: string }[];
    filters: { unit: string; status: string };
    canManage: boolean;
};

const ALL_VALUE = 'semua';

const STATUS_VARIANT: Record<
    ExamPeriodRow['status']['value'],
    StatusBadgeVariant
> = {
    draft: 'muted',
    active: 'success',
    closed: 'default',
};

export default function ExamPeriodsIndex({
    periods,
    units,
    feeTypes,
    deliveries,
    statuses,
    scopes,
    filters,
    canManage,
}: Props) {
    const [loading, setLoading] = useState(false);
    const [formOpen, setFormOpen] = useState(false);
    const [editing, setEditing] = useState<ExamPeriodRow | null>(null);

    const defaultUnitId = units[0] ? String(units[0].id) : '';
    const availableFeesFor = (unitIdStr: string) =>
        feeTypes.filter(
            (f) => f.unit_id === null || String(f.unit_id) === unitIdStr,
        );

    const form = useForm<{
        unit_id: string;
        name: string;
        delivery: 'cbt' | 'paper';
        starts_on: string;
        ends_on: string;
        requirements: RequirementInput[];
    }>({
        unit_id: defaultUnitId,
        name: '',
        delivery: 'cbt',
        starts_on: '',
        ends_on: '',
        requirements: [],
    });

    function visitFilters(next: Partial<Props['filters']>, page?: number) {
        const merged = {
            unit: filters.unit,
            status: filters.status,
            ...next,
        };
        const params: Record<string, string | number> = {};
        if (merged.unit !== '' && merged.unit !== ALL_VALUE) {
            params.unit = merged.unit;
        }
        if (merged.status !== '' && merged.status !== ALL_VALUE) {
            params.status = merged.status;
        }
        if (page && page > 1) {
            params.page = page;
        }
        router.get(ExamPeriodController.index(), params, {
            preserveScroll: true,
            preserveState: true,
            onStart: () => setLoading(true),
            onFinish: () => setLoading(false),
        });
    }

    function openCreate() {
        const unitId = defaultUnitId;
        const unitFees = availableFeesFor(unitId);
        setEditing(null);
        form.clearErrors();
        form.setData({
            unit_id: unitId,
            name: '',
            delivery: 'cbt',
            starts_on: '',
            ends_on: '',
            requirements: unitFees[0]
                ? [
                      {
                          fee_type_id: String(unitFees[0].id),
                          invoice_scope: 'all_open',
                          period_until: '',
                      },
                  ]
                : [],
        });
        setFormOpen(true);
    }

    function openEdit(row: ExamPeriodRow) {
        setEditing(row);
        form.clearErrors();
        form.setData({
            unit_id: String(row.unit_id),
            name: row.name,
            delivery: row.delivery.value,
            starts_on: row.starts_on,
            ends_on: row.ends_on,
            requirements: row.requirements.map((req) => ({
                fee_type_id: String(req.fee_type_id),
                invoice_scope: req.invoice_scope.value,
                period_until: req.period_until ?? '',
            })),
        });
        setFormOpen(true);
    }

    function addRequirementRow() {
        const unitFees = availableFeesFor(form.data.unit_id);
        const usedIds = new Set(
            form.data.requirements.map((r) => r.fee_type_id),
        );
        const nextFee =
            unitFees.find((f) => !usedIds.has(String(f.id))) ?? unitFees[0];
        if (!nextFee) {
            return;
        }
        form.setData('requirements', [
            ...form.data.requirements,
            {
                fee_type_id: String(nextFee.id),
                invoice_scope: 'all_open',
                period_until: '',
            },
        ]);
    }

    function updateRequirement(
        index: number,
        patch: Partial<RequirementInput>,
    ) {
        const next = form.data.requirements.map((row, idx) =>
            idx === index ? { ...row, ...patch } : row,
        );
        form.setData('requirements', next);
    }

    function removeRequirement(index: number) {
        form.setData(
            'requirements',
            form.data.requirements.filter((_, idx) => idx !== index),
        );
    }

    function submitForm(e: FormEvent) {
        e.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => setFormOpen(false),
        };
        if (editing) {
            form.put(
                ExamPeriodController.update({ examPeriod: editing.id }).url,
                options,
            );
        } else {
            form.post(ExamPeriodController.store().url, options);
        }
    }

    function changeStatus(row: ExamPeriodRow, target: 'activate' | 'close') {
        const route =
            target === 'activate'
                ? ExamPeriodController.activate({ examPeriod: row.id })
                : ExamPeriodController.close({ examPeriod: row.id });
        router.patch(route, {}, { preserveScroll: true });
    }

    const columns: DataTableColumn<ExamPeriodRow>[] = [
        {
            key: 'name',
            label: 'Periode ujian',
            render: (row) => (
                <div>
                    <div className="font-medium">{row.name}</div>
                    <div className="text-xs text-muted-foreground">
                        {row.unit_code} · {row.delivery.label}
                    </div>
                </div>
            ),
        },
        {
            key: 'dates',
            label: 'Pelaksanaan',
            render: (row) => (
                <div className="text-sm">
                    <DateText value={row.starts_on} /> –{' '}
                    <DateText value={row.ends_on} />
                </div>
            ),
        },
        {
            key: 'requirements',
            label: 'Syarat tagihan wajib lunas',
            render: (row) => (
                <ul className="space-y-0.5 text-xs">
                    {row.requirements.map((req) => (
                        <li key={req.id}>
                            <span className="font-medium">
                                {req.fee_type_name}
                            </span>{' '}
                            <span className="text-muted-foreground">
                                ({req.invoice_scope.label}
                                {req.period_until
                                    ? ` s.d. ${req.period_until.slice(0, 7)}`
                                    : ''}
                                )
                            </span>
                        </li>
                    ))}
                </ul>
            ),
        },
        {
            key: 'status',
            label: 'Status',
            align: 'center',
            render: (row) => (
                <StatusBadge variant={STATUS_VARIANT[row.status.value]}>
                    {row.status.label}
                </StatusBadge>
            ),
        },
        {
            key: 'actions',
            label: 'Aksi',
            align: 'center',
            render: (row) => (
                <RowActions
                    onEdit={canManage ? () => openEdit(row) : undefined}
                    itemName={row.name}
                >
                    {canManage && row.status.value === 'draft' && (
                        <Tooltip>
                            <TooltipTrigger asChild>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    aria-label={`Aktifkan ${row.name}`}
                                    onClick={() =>
                                        changeStatus(row, 'activate')
                                    }
                                >
                                    <CheckCircle2 className="size-4 text-primary" />
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent>Aktifkan periode</TooltipContent>
                        </Tooltip>
                    )}
                    {canManage && row.status.value === 'active' && (
                        <Tooltip>
                            <TooltipTrigger asChild>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    aria-label={`Tutup ${row.name}`}
                                    onClick={() => changeStatus(row, 'close')}
                                >
                                    <Lock className="size-4" />
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent>Tutup periode</TooltipContent>
                        </Tooltip>
                    )}
                </RowActions>
            ),
        },
    ];

    const unitFeeOptions = availableFeesFor(form.data.unit_id);

    return (
        <StaffLayout
            breadcrumbs={[
                { title: 'Beranda', href: dashboard() },
                {
                    title: 'Kelayakan ujian',
                    href: ExamPeriodController.index(),
                },
            ]}
        >
            <Head title="Kelayakan ujian" />

            <div className="space-y-6">
                <PageHeader
                    title="Periode & syarat kelayakan ujian"
                    description="Tetapkan periode ujian dan tagihan wajib lunas per unit."
                    actions={
                        canManage && (
                            <Button onClick={openCreate}>
                                <Plus className="size-4" />
                                Tambah periode
                            </Button>
                        )
                    }
                />

                <div className="flex flex-wrap items-center gap-3">
                    <Select
                        value={filters.unit === '' ? ALL_VALUE : filters.unit}
                        onValueChange={(value) =>
                            visitFilters({
                                unit: value === ALL_VALUE ? 'all' : value,
                            })
                        }
                    >
                        <SelectTrigger className="w-48">
                            <SelectValue placeholder="Semua unit" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Semua unit</SelectItem>
                            {units.map((u) => (
                                <SelectItem key={u.id} value={u.code}>
                                    {u.code} — {u.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    <Select
                        value={
                            filters.status === '' ? ALL_VALUE : filters.status
                        }
                        onValueChange={(value) =>
                            visitFilters({
                                status: value === ALL_VALUE ? '' : value,
                            })
                        }
                    >
                        <SelectTrigger className="w-44">
                            <SelectValue placeholder="Semua status" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL_VALUE}>
                                Semua status
                            </SelectItem>
                            {statuses.map((s) => (
                                <SelectItem key={s.value} value={s.value}>
                                    {s.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                <DataTable
                    columns={columns}
                    data={periods.data}
                    rowKey={(row) => row.id}
                    loading={loading}
                    pagination={{
                        currentPage: periods.meta.current_page,
                        lastPage: periods.meta.last_page,
                        total: periods.meta.total,
                        perPage: periods.meta.per_page,
                        from: periods.meta.from,
                        to: periods.meta.to,
                    }}
                    onPageChange={(page) => visitFilters({}, page)}
                    empty={{
                        title: 'Belum ada periode ujian',
                        description:
                            'Tambahkan periode ujian (mis. PAS Ganjil) beserta syarat tagihan wajib lunas.',
                        action: canManage ? (
                            <Button onClick={openCreate}>Tambah periode</Button>
                        ) : undefined,
                    }}
                />
            </div>

            <FormDialog
                open={formOpen}
                onOpenChange={setFormOpen}
                title={editing ? 'Ubah periode ujian' : 'Tambah periode ujian'}
                onSubmit={submitForm}
                processing={form.processing}
                submitLabel={editing ? 'Simpan perubahan' : 'Simpan periode'}
            >
                <div className="space-y-4">
                    {!editing && (
                        <div className="space-y-1.5">
                            <Label htmlFor="ep-unit">Unit</Label>
                            <Select
                                value={form.data.unit_id}
                                onValueChange={(value) => {
                                    const nextFees = availableFeesFor(value);
                                    form.setData({
                                        ...form.data,
                                        unit_id: value,
                                        requirements: nextFees[0]
                                            ? [
                                                  {
                                                      fee_type_id: String(
                                                          nextFees[0].id,
                                                      ),
                                                      invoice_scope: 'all_open',
                                                      period_until: '',
                                                  },
                                              ]
                                            : [],
                                    });
                                }}
                            >
                                <SelectTrigger id="ep-unit">
                                    <SelectValue placeholder="Pilih unit" />
                                </SelectTrigger>
                                <SelectContent>
                                    {units.map((u) => (
                                        <SelectItem
                                            key={u.id}
                                            value={String(u.id)}
                                        >
                                            {u.code} — {u.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={form.errors.unit_id} />
                        </div>
                    )}

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="space-y-1.5">
                            <Label htmlFor="ep-name">Nama periode</Label>
                            <Input
                                id="ep-name"
                                value={form.data.name}
                                onChange={(e) =>
                                    form.setData('name', e.target.value)
                                }
                                placeholder="Mis. PAS Ganjil 2026"
                            />
                            <InputError message={form.errors.name} />
                        </div>

                        <div className="space-y-1.5">
                            <Label htmlFor="ep-delivery">Pelaksanaan</Label>
                            <Select
                                value={form.data.delivery}
                                onValueChange={(v) =>
                                    form.setData(
                                        'delivery',
                                        v as 'cbt' | 'paper',
                                    )
                                }
                            >
                                <SelectTrigger id="ep-delivery">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {deliveries.map((d) => (
                                        <SelectItem
                                            key={d.value}
                                            value={d.value}
                                        >
                                            {d.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={form.errors.delivery} />
                        </div>
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="space-y-1.5">
                            <Label htmlFor="ep-start">Tanggal mulai</Label>
                            <DateInput
                                id="ep-start"
                                value={form.data.starts_on}
                                onChange={(e) =>
                                    form.setData('starts_on', e.target.value)
                                }
                            />
                            <InputError message={form.errors.starts_on} />
                        </div>

                        <div className="space-y-1.5">
                            <Label htmlFor="ep-end">Tanggal selesai</Label>
                            <DateInput
                                id="ep-end"
                                value={form.data.ends_on}
                                onChange={(e) =>
                                    form.setData('ends_on', e.target.value)
                                }
                            />
                            <InputError message={form.errors.ends_on} />
                        </div>
                    </div>

                    <div className="space-y-2 border-t border-border pt-3">
                        <div className="flex items-center justify-between gap-2">
                            <Label>Syarat tagihan wajib lunas</Label>
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                onClick={addRequirementRow}
                                disabled={unitFeeOptions.length === 0}
                            >
                                <Plus className="size-3.5" />
                                Tambah syarat
                            </Button>
                        </div>

                        <InputError message={form.errors.requirements} />

                        {form.data.requirements.length === 0 ? (
                            <p className="text-xs text-muted-foreground">
                                Tambahkan minimal satu jenis tagihan yang wajib
                                lunas.
                            </p>
                        ) : (
                            <div className="space-y-2">
                                {form.data.requirements.map((req, index) => (
                                    <div
                                        key={index}
                                        className="grid items-start gap-2 rounded-lg border border-border bg-muted/30 p-2.5 sm:grid-cols-[1fr_1fr_auto]"
                                    >
                                        <div className="space-y-1">
                                            <Select
                                                value={req.fee_type_id}
                                                onValueChange={(v) =>
                                                    updateRequirement(index, {
                                                        fee_type_id: v,
                                                    })
                                                }
                                            >
                                                <SelectTrigger>
                                                    <SelectValue placeholder="Jenis tagihan" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {unitFeeOptions.map((f) => (
                                                        <SelectItem
                                                            key={f.id}
                                                            value={String(f.id)}
                                                        >
                                                            {f.name}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                            <InputError
                                                message={
                                                    (
                                                        form.errors as Record<
                                                            string,
                                                            string
                                                        >
                                                    )[
                                                        `requirements.${index}.fee_type_id`
                                                    ]
                                                }
                                            />
                                        </div>

                                        <div className="space-y-1.5">
                                            <Select
                                                value={req.invoice_scope}
                                                onValueChange={(v) =>
                                                    updateRequirement(index, {
                                                        invoice_scope: v as
                                                            | 'up_to_period'
                                                            | 'all_open',
                                                    })
                                                }
                                            >
                                                <SelectTrigger>
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {scopes.map((s) => (
                                                        <SelectItem
                                                            key={s.value}
                                                            value={s.value}
                                                        >
                                                            {s.label}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>

                                            {req.invoice_scope ===
                                                'up_to_period' && (
                                                <DateInput
                                                    value={req.period_until}
                                                    onChange={(e) =>
                                                        updateRequirement(
                                                            index,
                                                            {
                                                                period_until:
                                                                    e.target
                                                                        .value,
                                                            },
                                                        )
                                                    }
                                                    placeholder="Batas periode (dd/mm/yyyy)"
                                                />
                                            )}
                                            <InputError
                                                message={
                                                    (
                                                        form.errors as Record<
                                                            string,
                                                            string
                                                        >
                                                    )[
                                                        `requirements.${index}.period_until`
                                                    ]
                                                }
                                            />
                                        </div>

                                        <Button
                                            type="button"
                                            size="icon"
                                            variant="ghost"
                                            aria-label="Hapus syarat"
                                            onClick={() =>
                                                removeRequirement(index)
                                            }
                                        >
                                            <Trash2 className="size-4 text-destructive" />
                                        </Button>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>
                </div>
            </FormDialog>
        </StaffLayout>
    );
}
