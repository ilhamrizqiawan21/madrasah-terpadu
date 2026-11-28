import { Head, router, useForm } from '@inertiajs/react';
import { ArrowDown, ArrowUp, Lock, Plus, Power, PowerOff } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { useState } from 'react';
import HabitItemController from '@/actions/App/Http/Controllers/Admin/HabitItemController';
import type { DataTableColumn } from '@/components/app/data-table';
import { DataTable } from '@/components/app/data-table';
import { FormDialog } from '@/components/app/form-dialog';
import { PageHeader } from '@/components/app/page-header';
import { RowActions } from '@/components/app/row-actions';
import { StatusBadge } from '@/components/app/status-badge';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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

type HabitItemRow = {
    id: number;
    unit_id: number | null;
    unit_name: string;
    name: string;
    aspect: 'spiritual' | 'social';
    aspect_label: string;
    sort_order: number;
    is_active: boolean;
    is_locked: boolean;
};

type UnitOption = { id: number; code: string; name: string };
type AspectOption = { value: string; label: string };

type Props = {
    items: HabitItemRow[];
    units: UnitOption[];
    aspects: AspectOption[];
    filters: { unit_id: string; aspect: string };
    canManage: boolean;
    streakConfig: { enabled: boolean; days: number; points: number };
};

type ItemForm = {
    unit_id: string;
    name: string;
    aspect: 'spiritual' | 'social';
};

const ALL_UNITS = 'semua';

export default function HabitItemsIndex({
    items,
    units,
    aspects,
    filters,
    canManage,
    streakConfig,
}: Props) {
    const [formOpen, setFormOpen] = useState(false);
    const [editing, setEditing] = useState<HabitItemRow | null>(null);

    const form = useForm<ItemForm>({
        unit_id: ALL_UNITS,
        name: '',
        aspect: 'spiritual',
    });

    const streakForm = useForm({
        enabled: streakConfig.enabled,
        days: String(streakConfig.days),
        points: String(streakConfig.points),
    });

    function saveStreakConfig(e: FormEvent<HTMLFormElement>) {
        e.preventDefault();
        router.put(
            HabitItemController.updateStreakConfig.url(),
            {
                enabled: streakForm.data.enabled,
                days: Number(streakForm.data.days),
                points: Number(streakForm.data.points),
            },
            {
                preserveScroll: true,
                onError: (errors) =>
                    streakForm.setError(
                        errors as Record<'enabled' | 'days' | 'points', string>,
                    ),
            },
        );
    }

    function visitFilters(next: Partial<{ unit_id: string; aspect: string }>) {
        const merged = { ...filters, ...next };
        router.get(
            HabitItemController.index.url({
                query: {
                    unit_id: merged.unit_id || undefined,
                    aspect: merged.aspect || undefined,
                },
            }),
            {},
            { preserveState: true, replace: true },
        );
    }

    function openCreate() {
        setEditing(null);
        form.setData({ unit_id: ALL_UNITS, name: '', aspect: 'spiritual' });
        form.clearErrors();
        setFormOpen(true);
    }

    function openEdit(row: HabitItemRow) {
        setEditing(row);
        form.setData({
            unit_id: row.unit_id !== null ? String(row.unit_id) : ALL_UNITS,
            name: row.name,
            aspect: row.aspect,
        });
        form.clearErrors();
        setFormOpen(true);
    }

    function submitForm(e: FormEvent<HTMLFormElement>) {
        e.preventDefault();
        const payload = {
            unit_id:
                form.data.unit_id === ALL_UNITS
                    ? null
                    : Number(form.data.unit_id),
            name: form.data.name,
            aspect: form.data.aspect,
        };

        if (editing) {
            router.put(HabitItemController.update.url(editing.id), payload, {
                preserveScroll: true,
                onSuccess: () => setFormOpen(false),
                onError: (errors) =>
                    form.setError(errors as Record<keyof ItemForm, string>),
            });
            return;
        }

        router.post(HabitItemController.store.url(), payload, {
            preserveScroll: true,
            onSuccess: () => setFormOpen(false),
            onError: (errors) =>
                form.setError(errors as Record<keyof ItemForm, string>),
        });
    }

    function moveItem(row: HabitItemRow, direction: 'up' | 'down') {
        router.post(
            HabitItemController.move.url(row.id),
            { direction },
            { preserveScroll: true },
        );
    }

    function toggleItem(row: HabitItemRow) {
        router.patch(
            HabitItemController.toggle.url(row.id),
            { is_active: !row.is_active },
            { preserveScroll: true },
        );
    }

    function deleteItem(row: HabitItemRow) {
        router.delete(HabitItemController.destroy.url(row.id), {
            preserveScroll: true,
        });
    }

    const columns: DataTableColumn<HabitItemRow>[] = [
        {
            key: 'sort_order',
            label: 'Urutan',
            align: 'center',
            render: (row) => (
                <div className="flex items-center justify-center gap-1">
                    <span className="w-6 text-center text-xs text-muted-foreground tabular-nums">
                        {row.sort_order}
                    </span>
                    {canManage ? (
                        <>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                className="h-7 w-7"
                                aria-label={`Naikkan urutan ${row.name}`}
                                onClick={() => moveItem(row, 'up')}
                            >
                                <ArrowUp className="h-3.5 w-3.5" />
                            </Button>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                className="h-7 w-7"
                                aria-label={`Turunkan urutan ${row.name}`}
                                onClick={() => moveItem(row, 'down')}
                            >
                                <ArrowDown className="h-3.5 w-3.5" />
                            </Button>
                        </>
                    ) : null}
                </div>
            ),
        },
        {
            key: 'name',
            label: 'Item pembiasaan',
            render: (row) => (
                <div className="flex items-center gap-2">
                    <span className="font-medium text-foreground">
                        {row.name}
                    </span>
                    {row.is_locked ? (
                        <Tooltip>
                            <TooltipTrigger asChild>
                                <span
                                    tabIndex={0}
                                    aria-label="Item wajib sekolah"
                                    className="inline-flex text-muted-foreground"
                                >
                                    <Lock className="h-3.5 w-3.5" />
                                </span>
                            </TooltipTrigger>
                            <TooltipContent>Item wajib sekolah</TooltipContent>
                        </Tooltip>
                    ) : null}
                </div>
            ),
        },
        {
            key: 'aspect',
            label: 'Aspek',
            align: 'center',
            render: (row) => (
                <StatusBadge
                    variant={row.aspect === 'spiritual' ? 'default' : 'muted'}
                >
                    {row.aspect_label}
                </StatusBadge>
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
            key: 'status',
            label: 'Status',
            align: 'center',
            render: (row) => (
                <StatusBadge variant={row.is_active ? 'success' : 'muted'}>
                    {row.is_active ? 'Aktif' : 'Nonaktif'}
                </StatusBadge>
            ),
        },
        {
            key: 'actions',
            label: 'Aksi',
            align: 'center',
            render: (row) => {
                if (!canManage || row.is_locked) {
                    return (
                        <span className="text-xs text-muted-foreground">—</span>
                    );
                }

                return (
                    <RowActions
                        onEdit={() => openEdit(row)}
                        onDelete={() => deleteItem(row)}
                        itemName={row.name}
                        deleteTitle="Hapus item pembiasaan?"
                        deleteDescription="Item yang sudah memiliki catatan tidak akan dihapus, melainkan dinonaktifkan."
                    >
                        <Tooltip>
                            <TooltipTrigger asChild>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    aria-label={
                                        row.is_active
                                            ? `Nonaktifkan ${row.name}`
                                            : `Aktifkan ${row.name}`
                                    }
                                    onClick={() => toggleItem(row)}
                                >
                                    {row.is_active ? (
                                        <PowerOff className="h-4 w-4 text-muted-foreground" />
                                    ) : (
                                        <Power className="h-4 w-4 text-primary" />
                                    )}
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent>
                                {row.is_active
                                    ? 'Nonaktifkan item'
                                    : 'Aktifkan item'}
                            </TooltipContent>
                        </Tooltip>
                    </RowActions>
                );
            },
        },
    ];

    return (
        <>
            <Head title="Item pembiasaan" />
            <div className="space-y-6">
                <PageHeader
                    title="Item pembiasaan (mutabaah)"
                    description="Master kegiatan pembiasaan harian aspek spiritual dan sosial."
                    actions={
                        canManage ? (
                            <Button type="button" onClick={openCreate}>
                                <Plus className="mr-2 h-4 w-4" />
                                Tambah item
                            </Button>
                        ) : null
                    }
                />

                {canManage ? (
                    <form
                        onSubmit={saveStreakConfig}
                        className="rounded-lg border border-border bg-card p-4 shadow-xs"
                    >
                        <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                            <div className="space-y-2">
                                <label className="flex cursor-pointer items-center gap-2.5 text-sm font-medium text-foreground">
                                    <Checkbox
                                        checked={streakForm.data.enabled}
                                        onCheckedChange={(v) =>
                                            streakForm.setData(
                                                'enabled',
                                                v === true,
                                            )
                                        }
                                    />
                                    <span>
                                        Berikan poin kebaikan otomatis untuk
                                        streak mutabaah
                                    </span>
                                </label>
                                <p className="text-xs text-muted-foreground">
                                    Atur sendiri jumlah hari berturut-turut
                                    terverifikasi dan bobot poinnya.
                                </p>
                            </div>

                            <div className="flex flex-wrap items-end gap-3">
                                <div className="w-28 space-y-1">
                                    <Label
                                        htmlFor="streak-days"
                                        className="text-xs"
                                    >
                                        Hari berturut
                                    </Label>
                                    <Input
                                        id="streak-days"
                                        type="number"
                                        min={1}
                                        max={90}
                                        value={streakForm.data.days}
                                        onChange={(e) =>
                                            streakForm.setData(
                                                'days',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </div>
                                <div className="w-28 space-y-1">
                                    <Label
                                        htmlFor="streak-points"
                                        className="text-xs"
                                    >
                                        Poin kebaikan
                                    </Label>
                                    <Input
                                        id="streak-points"
                                        type="number"
                                        min={1}
                                        max={500}
                                        value={streakForm.data.points}
                                        onChange={(e) =>
                                            streakForm.setData(
                                                'points',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </div>
                                <Button
                                    type="submit"
                                    variant="outline"
                                    disabled={streakForm.processing}
                                >
                                    Simpan aturan
                                </Button>
                            </div>
                        </div>
                    </form>
                ) : null}

                <div className="flex flex-wrap items-center gap-3">
                    <Select
                        value={filters.unit_id || ALL_UNITS}
                        onValueChange={(v) =>
                            visitFilters({ unit_id: v === ALL_UNITS ? '' : v })
                        }
                    >
                        <SelectTrigger
                            className="w-[180px]"
                            aria-label="Filter unit"
                        >
                            <SelectValue placeholder="Semua unit" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL_UNITS}>
                                Semua unit
                            </SelectItem>
                            {units.map((u) => (
                                <SelectItem key={u.id} value={String(u.id)}>
                                    {u.code} — {u.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    <Select
                        value={filters.aspect || 'semua'}
                        onValueChange={(v) =>
                            visitFilters({ aspect: v === 'semua' ? '' : v })
                        }
                    >
                        <SelectTrigger
                            className="w-[160px]"
                            aria-label="Filter aspek"
                        >
                            <SelectValue placeholder="Semua aspek" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="semua">Semua aspek</SelectItem>
                            {aspects.map((a) => (
                                <SelectItem key={a.value} value={a.value}>
                                    {a.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                <DataTable
                    columns={columns}
                    data={items}
                    rowKey={(row) => row.id}
                    empty={{
                        title: 'Belum ada item pembiasaan',
                        description:
                            'Tambahkan item pembiasaan harian untuk dicentang oleh wali murid.',
                    }}
                />
            </div>

            <FormDialog
                open={formOpen}
                onOpenChange={setFormOpen}
                title={
                    editing ? 'Ubah item pembiasaan' : 'Tambah item pembiasaan'
                }
                onSubmit={submitForm}
                processing={form.processing}
            >
                <div className="space-y-4">
                    <div className="space-y-2">
                        <Label htmlFor="habit-unit">Unit</Label>
                        <Select
                            value={form.data.unit_id}
                            onValueChange={(v) => form.setData('unit_id', v)}
                        >
                            <SelectTrigger id="habit-unit">
                                <SelectValue placeholder="Semua unit" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={ALL_UNITS}>
                                    Semua unit
                                </SelectItem>
                                {units.map((u) => (
                                    <SelectItem key={u.id} value={String(u.id)}>
                                        {u.code} — {u.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={form.errors.unit_id} />
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="habit-name">Nama kegiatan</Label>
                        <Input
                            id="habit-name"
                            value={form.data.name}
                            onChange={(e) =>
                                form.setData('name', e.target.value)
                            }
                            placeholder="Contoh: Membaca Al-Qur'an 1 halaman"
                            required
                        />
                        <InputError message={form.errors.name} />
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="habit-aspect">Aspek</Label>
                        <Select
                            value={form.data.aspect}
                            onValueChange={(v) =>
                                form.setData('aspect', v as ItemForm['aspect'])
                            }
                        >
                            <SelectTrigger id="habit-aspect">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {aspects.map((a) => (
                                    <SelectItem key={a.value} value={a.value}>
                                        {a.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={form.errors.aspect} />
                    </div>
                </div>
            </FormDialog>
        </>
    );
}

HabitItemsIndex.layout = (page: ReactNode) => (
    <StaffLayout
        breadcrumbs={[
            { title: 'Kesiswaan & Asrama', href: '#' },
            { title: 'Item pembiasaan', href: HabitItemController.index.url() },
        ]}
    >
        {page}
    </StaffLayout>
);
