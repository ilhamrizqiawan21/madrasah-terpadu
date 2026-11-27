import { Head, router, useForm } from '@inertiajs/react';
import { Plus, Power, PowerOff } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { useState } from 'react';
import MemorizationMaterialController from '@/actions/App/Http/Controllers/Admin/MemorizationMaterialController';
import { ArabicText } from '@/components/app/arabic-text';
import type { DataTableColumn } from '@/components/app/data-table';
import { DataTable } from '@/components/app/data-table';
import { FormDialog } from '@/components/app/form-dialog';
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
import { Textarea } from '@/components/ui/textarea';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import StaffLayout from '@/layouts/staff-layout';
import type { PaginatedResource } from '@/types';

type MaterialRow = {
    id: number;
    unit_id: number | null;
    unit_name: string;
    kind: 'hadith' | 'prayer';
    kind_label: string;
    title: string;
    arabic_text: string | null;
    translation: string | null;
    source: string | null;
    sort_order: number;
    is_active: boolean;
};

type UnitOption = { id: number; code: string; name: string };

type Props = {
    materials: PaginatedResource<MaterialRow>;
    units: UnitOption[];
    filters: { kind: string; unit_id: string };
    canManage: boolean;
};

type MaterialForm = {
    unit_id: string;
    kind: 'hadith' | 'prayer';
    title: string;
    arabic_text: string;
    translation: string;
    source: string;
    sort_order: string;
};

const ALL_UNITS = 'semua';

export default function MemorizationMaterialsIndex({
    materials,
    units,
    filters,
    canManage,
}: Props) {
    const [formOpen, setFormOpen] = useState(false);
    const [editing, setEditing] = useState<MaterialRow | null>(null);

    const form = useForm<MaterialForm>({
        unit_id: ALL_UNITS,
        kind: 'hadith',
        title: '',
        arabic_text: '',
        translation: '',
        source: '',
        sort_order: '1',
    });

    function visitFilters(
        next: Partial<{ kind: string; unit_id: string }>,
        page?: number,
    ) {
        const merged = { ...filters, ...next };
        router.get(
            MemorizationMaterialController.index.url({
                query: {
                    kind: merged.kind || undefined,
                    unit_id: merged.unit_id || undefined,
                    page: page && page > 1 ? page : undefined,
                },
            }),
            {},
            { preserveState: true, replace: true },
        );
    }

    function openCreate() {
        setEditing(null);
        form.setData({
            unit_id: ALL_UNITS,
            kind: 'hadith',
            title: '',
            arabic_text: '',
            translation: '',
            source: '',
            sort_order: '1',
        });
        form.clearErrors();
        setFormOpen(true);
    }

    function openEdit(row: MaterialRow) {
        setEditing(row);
        form.setData({
            unit_id: row.unit_id !== null ? String(row.unit_id) : ALL_UNITS,
            kind: row.kind,
            title: row.title,
            arabic_text: row.arabic_text ?? '',
            translation: row.translation ?? '',
            source: row.source ?? '',
            sort_order: String(row.sort_order),
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
            kind: form.data.kind,
            title: form.data.title,
            arabic_text: form.data.arabic_text || null,
            translation: form.data.translation || null,
            source: form.data.source || null,
            sort_order: Number(form.data.sort_order) || 1,
        };

        if (editing) {
            router.put(
                MemorizationMaterialController.update.url(editing.id),
                payload,
                {
                    preserveScroll: true,
                    onSuccess: () => setFormOpen(false),
                    onError: (errors) =>
                        form.setError(
                            errors as Record<keyof MaterialForm, string>,
                        ),
                },
            );
            return;
        }

        router.post(MemorizationMaterialController.store.url(), payload, {
            preserveScroll: true,
            onSuccess: () => setFormOpen(false),
            onError: (errors) =>
                form.setError(errors as Record<keyof MaterialForm, string>),
        });
    }

    function toggleMaterial(row: MaterialRow) {
        router.patch(
            MemorizationMaterialController.toggle.url(row.id),
            { is_active: !row.is_active },
            { preserveScroll: true },
        );
    }

    function deleteMaterial(row: MaterialRow) {
        router.delete(MemorizationMaterialController.destroy.url(row.id), {
            preserveScroll: true,
        });
    }

    const columns: DataTableColumn<MaterialRow>[] = [
        {
            key: 'title',
            label: 'Materi',
            render: (row) => (
                <div className="space-y-1">
                    <div className="font-medium text-foreground">
                        {row.title}
                    </div>
                    {row.arabic_text ? (
                        <ArabicText
                            text={row.arabic_text}
                            className="text-base"
                        />
                    ) : null}
                    {row.translation ? (
                        <div className="text-xs text-muted-foreground">
                            {row.translation}
                        </div>
                    ) : null}
                    {row.source ? (
                        <div className="text-xs text-muted-foreground">
                            Sumber: {row.source}
                        </div>
                    ) : null}
                </div>
            ),
        },
        {
            key: 'kind',
            label: 'Jenis',
            align: 'center',
            render: (row) => (
                <StatusBadge
                    variant={row.kind === 'hadith' ? 'default' : 'muted'}
                >
                    {row.kind_label}
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
            render: (row) =>
                canManage ? (
                    <RowActions
                        onEdit={() => openEdit(row)}
                        onDelete={() => deleteMaterial(row)}
                        itemName={row.title}
                        deleteTitle="Hapus materi?"
                        deleteDescription="Materi yang sudah memiliki setoran hanya akan dinonaktifkan."
                    >
                        <Tooltip>
                            <TooltipTrigger asChild>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    aria-label={
                                        row.is_active
                                            ? `Nonaktifkan ${row.title}`
                                            : `Aktifkan ${row.title}`
                                    }
                                    onClick={() => toggleMaterial(row)}
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
                                    ? 'Nonaktifkan materi'
                                    : 'Aktifkan materi'}
                            </TooltipContent>
                        </Tooltip>
                    </RowActions>
                ) : (
                    <span className="text-xs text-muted-foreground">—</span>
                ),
        },
    ];

    return (
        <>
            <Head title="Materi hadits & doa" />
            <div className="space-y-6">
                <PageHeader
                    title="Materi hadits & doa"
                    description="Master materi hafalan hadits pilihan dan do'a harian untuk target dan setoran."
                    actions={
                        canManage ? (
                            <Button type="button" onClick={openCreate}>
                                <Plus className="mr-2 h-4 w-4" />
                                Tambah materi
                            </Button>
                        ) : null
                    }
                />

                <div className="flex flex-wrap items-center gap-3">
                    <Select
                        value={filters.kind || 'semua'}
                        onValueChange={(v) =>
                            visitFilters({ kind: v === 'semua' ? '' : v })
                        }
                    >
                        <SelectTrigger
                            className="w-[160px]"
                            aria-label="Filter jenis"
                        >
                            <SelectValue placeholder="Semua jenis" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="semua">Semua jenis</SelectItem>
                            <SelectItem value="hadith">Hadits</SelectItem>
                            <SelectItem value="prayer">Do'a</SelectItem>
                        </SelectContent>
                    </Select>

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
                </div>

                <DataTable
                    columns={columns}
                    data={materials.data}
                    rowKey={(row) => row.id}
                    pagination={{
                        currentPage: materials.meta.current_page,
                        lastPage: materials.meta.last_page,
                        perPage: materials.meta.per_page,
                        total: materials.meta.total,
                        from: materials.meta.from,
                        to: materials.meta.to,
                    }}
                    onPageChange={(page) => visitFilters({}, page)}
                    empty={{
                        title: 'Belum ada materi hadits atau doa',
                        description:
                            "Tambahkan materi hadits dan do'a agar dapat dipilih pada target hafalan dan setoran.",
                    }}
                />
            </div>

            <FormDialog
                open={formOpen}
                onOpenChange={setFormOpen}
                title={
                    editing ? 'Ubah materi hafalan' : 'Tambah materi hafalan'
                }
                onSubmit={submitForm}
                processing={form.processing}
                contentClassName="sm:max-w-xl"
            >
                <div className="space-y-4">
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div className="space-y-2">
                            <Label htmlFor="mat-kind">Jenis</Label>
                            <Select
                                value={form.data.kind}
                                onValueChange={(v) =>
                                    form.setData(
                                        'kind',
                                        v as 'hadith' | 'prayer',
                                    )
                                }
                            >
                                <SelectTrigger id="mat-kind">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="hadith">
                                        Hadits
                                    </SelectItem>
                                    <SelectItem value="prayer">Do'a</SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError message={form.errors.kind} />
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="mat-unit">Unit</Label>
                            <Select
                                value={form.data.unit_id}
                                onValueChange={(v) =>
                                    form.setData('unit_id', v)
                                }
                            >
                                <SelectTrigger id="mat-unit">
                                    <SelectValue placeholder="Semua unit" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={ALL_UNITS}>
                                        Semua unit
                                    </SelectItem>
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
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="mat-title">Judul materi</Label>
                        <Input
                            id="mat-title"
                            value={form.data.title}
                            onChange={(e) =>
                                form.setData('title', e.target.value)
                            }
                            required
                        />
                        <InputError message={form.errors.title} />
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="mat-arabic">Teks Arab (opsional)</Label>
                        <Textarea
                            id="mat-arabic"
                            dir="rtl"
                            rows={3}
                            className="text-right font-serif text-lg"
                            value={form.data.arabic_text}
                            onChange={(e) =>
                                form.setData('arabic_text', e.target.value)
                            }
                        />
                        <InputError message={form.errors.arabic_text} />
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="mat-translation">
                            Terjemahan (opsional)
                        </Label>
                        <Textarea
                            id="mat-translation"
                            rows={2}
                            value={form.data.translation}
                            onChange={(e) =>
                                form.setData('translation', e.target.value)
                            }
                        />
                        <InputError message={form.errors.translation} />
                    </div>

                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div className="space-y-2">
                            <Label htmlFor="mat-source">
                                Sumber / perawi (opsional)
                            </Label>
                            <Input
                                id="mat-source"
                                value={form.data.source}
                                onChange={(e) =>
                                    form.setData('source', e.target.value)
                                }
                                placeholder="Contoh: HR. Bukhari & Muslim"
                            />
                            <InputError message={form.errors.source} />
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="mat-order">Urutan</Label>
                            <Input
                                id="mat-order"
                                type="number"
                                min={1}
                                value={form.data.sort_order}
                                onChange={(e) =>
                                    form.setData('sort_order', e.target.value)
                                }
                            />
                            <InputError message={form.errors.sort_order} />
                        </div>
                    </div>
                </div>
            </FormDialog>
        </>
    );
}

MemorizationMaterialsIndex.layout = (page: ReactNode) => (
    <StaffLayout
        breadcrumbs={[
            { title: 'Ibadah', href: '#' },
            {
                title: 'Materi hadits & doa',
                href: MemorizationMaterialController.index.url(),
            },
        ]}
    >
        {page}
    </StaffLayout>
);
