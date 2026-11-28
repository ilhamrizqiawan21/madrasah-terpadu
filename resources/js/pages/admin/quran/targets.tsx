import { Head, router, useForm } from '@inertiajs/react';
import { Plus, Power, PowerOff, Trash2 } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { useState } from 'react';
import MemorizationTargetController from '@/actions/App/Http/Controllers/Admin/MemorizationTargetController';
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
import type { PaginatedResource } from '@/types';

type RangeItem = { surah: number; ayah_from: number; ayah_to: number };

type TargetRow = {
    id: number;
    unit_id: number;
    unit_code: string;
    grade: string;
    academic_year_id: number;
    academic_year_label: string;
    kind: 'quran' | 'hadith' | 'prayer';
    kind_label: string;
    title: string;
    ranges: RangeItem[];
    material_ids: number[];
    is_active: boolean;
};

type UnitOption = { id: number; code: string; name: string };
type YearOption = { id: number; label: string; is_active: boolean };
type SurahOption = { number: number; name_latin: string; ayah_count: number };
type MaterialOption = { id: number; kind: string; title: string };

type Props = {
    targets: PaginatedResource<TargetRow>;
    units: UnitOption[];
    academicYears: YearOption[];
    surahs: SurahOption[];
    materials: MaterialOption[];
    filters: { unit_id: string; kind: string };
    canManage: boolean;
};

type TargetForm = {
    unit_id: string;
    grade: string;
    academic_year_id: string;
    kind: 'quran' | 'hadith' | 'prayer';
    title: string;
    ranges: RangeItem[];
    material_ids: number[];
};

const ALL_UNITS = 'semua';

export default function MemorizationTargetsIndex({
    targets,
    units,
    academicYears,
    surahs,
    materials,
    filters,
    canManage,
}: Props) {
    const [formOpen, setFormOpen] = useState(false);
    const [editing, setEditing] = useState<TargetRow | null>(null);

    const activeYear =
        academicYears.find((y) => y.is_active) ?? academicYears[0];

    const form = useForm<TargetForm>({
        unit_id: units[0] ? String(units[0].id) : '',
        grade: '1',
        academic_year_id: activeYear ? String(activeYear.id) : '',
        kind: 'quran',
        title: '',
        ranges: [{ surah: 78, ayah_from: 1, ayah_to: 40 }],
        material_ids: [],
    });

    function visitFilters(
        next: Partial<{ unit_id: string; kind: string }>,
        page?: number,
    ) {
        const merged = { ...filters, ...next };
        router.get(
            MemorizationTargetController.index.url({
                query: {
                    unit_id: merged.unit_id || undefined,
                    kind: merged.kind || undefined,
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
            unit_id: units[0] ? String(units[0].id) : '',
            grade: '1',
            academic_year_id: activeYear ? String(activeYear.id) : '',
            kind: 'quran',
            title: '',
            ranges: [{ surah: 114, ayah_from: 1, ayah_to: 6 }],
            material_ids: [],
        });
        form.clearErrors();
        setFormOpen(true);
    }

    function openEdit(row: TargetRow) {
        setEditing(row);
        form.setData({
            unit_id: String(row.unit_id),
            grade: row.grade,
            academic_year_id: String(row.academic_year_id),
            kind: row.kind,
            title: row.title,
            ranges:
                row.ranges.length > 0
                    ? row.ranges
                    : [{ surah: 114, ayah_from: 1, ayah_to: 6 }],
            material_ids: row.material_ids,
        });
        form.clearErrors();
        setFormOpen(true);
    }

    function submitForm(e: FormEvent<HTMLFormElement>) {
        e.preventDefault();
        const payload = {
            unit_id: Number(form.data.unit_id),
            grade: form.data.grade,
            academic_year_id: Number(form.data.academic_year_id),
            kind: form.data.kind,
            title: form.data.title,
            ranges: form.data.kind === 'quran' ? form.data.ranges : null,
            material_ids:
                form.data.kind !== 'quran' ? form.data.material_ids : null,
        };

        if (editing) {
            router.put(
                MemorizationTargetController.update.url(editing.id),
                payload,
                {
                    preserveScroll: true,
                    onSuccess: () => setFormOpen(false),
                    onError: (errors) =>
                        form.setError(
                            errors as Record<keyof TargetForm, string>,
                        ),
                },
            );
            return;
        }

        router.post(MemorizationTargetController.store.url(), payload, {
            preserveScroll: true,
            onSuccess: () => setFormOpen(false),
            onError: (errors) =>
                form.setError(errors as Record<keyof TargetForm, string>),
        });
    }

    function toggleTarget(row: TargetRow) {
        router.patch(
            MemorizationTargetController.toggle.url(row.id),
            { is_active: !row.is_active },
            { preserveScroll: true },
        );
    }

    function toggleMaterial(id: number, checked: boolean) {
        const set = new Set(form.data.material_ids);
        if (checked) {
            set.add(id);
        } else {
            set.delete(id);
        }
        form.setData('material_ids', Array.from(set));
    }

    const filteredMaterials = materials.filter(
        (m) => m.kind === form.data.kind,
    );

    const columns: DataTableColumn<TargetRow>[] = [
        {
            key: 'title',
            label: 'Target hafalan',
            render: (row) => (
                <div>
                    <div className="font-medium text-foreground">
                        {row.title}
                    </div>
                    <div className="text-xs text-muted-foreground">
                        {row.unit_code} · Tingkat {row.grade} ·{' '}
                        {row.academic_year_label}
                    </div>
                </div>
            ),
        },
        {
            key: 'kind',
            label: 'Jenis',
            align: 'center',
            render: (row) => (
                <StatusBadge variant="default">{row.kind_label}</StatusBadge>
            ),
        },
        {
            key: 'scope',
            label: 'Cakupan',
            render: (row) => (
                <span className="text-sm text-muted-foreground">
                    {row.kind === 'quran'
                        ? `${row.ranges.length} rentang surah`
                        : `${row.material_ids.length} materi`}
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
                        itemName={row.title}
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
                                    onClick={() => toggleTarget(row)}
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
                                    ? 'Nonaktifkan target'
                                    : 'Aktifkan target'}
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
            <Head title="Target hafalan" />
            <div className="space-y-6">
                <PageHeader
                    title="Target hafalan"
                    description="Target hafalan Al-Qur'an, Hadits, dan Do'a per unit, tingkat, dan tahun ajaran."
                    actions={
                        canManage ? (
                            <Button type="button" onClick={openCreate}>
                                <Plus className="mr-2 h-4 w-4" />
                                Tambah target
                            </Button>
                        ) : null
                    }
                />

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
                            <SelectItem value="quran">Al-Qur'an</SelectItem>
                            <SelectItem value="hadith">Hadits</SelectItem>
                            <SelectItem value="prayer">Do'a</SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <DataTable
                    columns={columns}
                    data={targets.data}
                    rowKey={(row) => row.id}
                    pagination={{
                        currentPage: targets.meta.current_page,
                        lastPage: targets.meta.last_page,
                        perPage: targets.meta.per_page,
                        total: targets.meta.total,
                        from: targets.meta.from,
                        to: targets.meta.to,
                    }}
                    onPageChange={(page) => visitFilters({}, page)}
                    empty={{
                        title: 'Belum ada target hafalan',
                        description:
                            'Tambahkan target hafalan per unit dan tingkat untuk memantau progres siswa.',
                    }}
                />
            </div>

            <FormDialog
                open={formOpen}
                onOpenChange={setFormOpen}
                title={
                    editing ? 'Ubah target hafalan' : 'Tambah target hafalan'
                }
                onSubmit={submitForm}
                processing={form.processing}
                contentClassName="sm:max-w-2xl"
            >
                <div className="space-y-4">
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div className="space-y-2">
                            <Label htmlFor="tgt-unit">Unit</Label>
                            <Select
                                value={form.data.unit_id}
                                onValueChange={(v) =>
                                    form.setData('unit_id', v)
                                }
                            >
                                <SelectTrigger id="tgt-unit">
                                    <SelectValue />
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

                        <div className="space-y-2">
                            <Label htmlFor="tgt-grade">Tingkat</Label>
                            <Input
                                id="tgt-grade"
                                value={form.data.grade}
                                onChange={(e) =>
                                    form.setData('grade', e.target.value)
                                }
                                placeholder="Contoh: 1 / A"
                                required
                            />
                            <InputError message={form.errors.grade} />
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="tgt-year">Tahun ajaran</Label>
                            <Select
                                value={form.data.academic_year_id}
                                onValueChange={(v) =>
                                    form.setData('academic_year_id', v)
                                }
                            >
                                <SelectTrigger id="tgt-year">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {academicYears.map((y) => (
                                        <SelectItem
                                            key={y.id}
                                            value={String(y.id)}
                                        >
                                            {y.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError
                                message={form.errors.academic_year_id}
                            />
                        </div>
                    </div>

                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div className="space-y-2">
                            <Label htmlFor="tgt-kind">Jenis hafalan</Label>
                            <Select
                                value={form.data.kind}
                                onValueChange={(v) =>
                                    form.setData(
                                        'kind',
                                        v as 'quran' | 'hadith' | 'prayer',
                                    )
                                }
                            >
                                <SelectTrigger id="tgt-kind">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="quran">
                                        Al-Qur'an
                                    </SelectItem>
                                    <SelectItem value="hadith">
                                        Hadits
                                    </SelectItem>
                                    <SelectItem value="prayer">Do'a</SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError message={form.errors.kind} />
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="tgt-title">Judul target</Label>
                            <Input
                                id="tgt-title"
                                value={form.data.title}
                                onChange={(e) =>
                                    form.setData('title', e.target.value)
                                }
                                placeholder="Contoh: Juz 30 Semester Ganjil"
                                required
                            />
                            <InputError message={form.errors.title} />
                        </div>
                    </div>

                    {form.data.kind === 'quran' ? (
                        <div className="space-y-2">
                            <div className="flex items-center justify-between">
                                <Label>Rentang surah & ayat</Label>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={() =>
                                        form.setData('ranges', [
                                            ...form.data.ranges,
                                            {
                                                surah: 114,
                                                ayah_from: 1,
                                                ayah_to: 6,
                                            },
                                        ])
                                    }
                                >
                                    <Plus className="mr-1 h-3.5 w-3.5" />
                                    Tambah rentang
                                </Button>
                            </div>
                            <div className="max-h-52 space-y-2 overflow-y-auto rounded-md border border-border p-3">
                                {form.data.ranges.map((r, idx) => (
                                    <div
                                        key={idx}
                                        className="grid grid-cols-12 items-center gap-2"
                                    >
                                        <div className="col-span-6">
                                            <Select
                                                value={String(r.surah)}
                                                onValueChange={(v) => {
                                                    const next = [
                                                        ...form.data.ranges,
                                                    ];
                                                    const found = surahs.find(
                                                        (s) =>
                                                            s.number ===
                                                            Number(v),
                                                    );
                                                    next[idx] = {
                                                        surah: Number(v),
                                                        ayah_from: 1,
                                                        ayah_to:
                                                            found?.ayah_count ??
                                                            1,
                                                    };
                                                    form.setData(
                                                        'ranges',
                                                        next,
                                                    );
                                                }}
                                            >
                                                <SelectTrigger>
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {surahs.map((s) => (
                                                        <SelectItem
                                                            key={s.number}
                                                            value={String(
                                                                s.number,
                                                            )}
                                                        >
                                                            {s.number}.{' '}
                                                            {s.name_latin} (
                                                            {s.ayah_count})
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        </div>
                                        <div className="col-span-2">
                                            <Input
                                                type="number"
                                                min={1}
                                                value={r.ayah_from}
                                                aria-label="Ayat awal"
                                                onChange={(e) => {
                                                    const next = [
                                                        ...form.data.ranges,
                                                    ];
                                                    next[idx] = {
                                                        ...r,
                                                        ayah_from: Number(
                                                            e.target.value,
                                                        ),
                                                    };
                                                    form.setData(
                                                        'ranges',
                                                        next,
                                                    );
                                                }}
                                            />
                                        </div>
                                        <div className="col-span-2">
                                            <Input
                                                type="number"
                                                min={1}
                                                value={r.ayah_to}
                                                aria-label="Ayat akhir"
                                                onChange={(e) => {
                                                    const next = [
                                                        ...form.data.ranges,
                                                    ];
                                                    next[idx] = {
                                                        ...r,
                                                        ayah_to: Number(
                                                            e.target.value,
                                                        ),
                                                    };
                                                    form.setData(
                                                        'ranges',
                                                        next,
                                                    );
                                                }}
                                            />
                                        </div>
                                        <div className="col-span-2 text-right">
                                            {form.data.ranges.length > 1 ? (
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="icon"
                                                    aria-label="Hapus rentang"
                                                    onClick={() =>
                                                        form.setData(
                                                            'ranges',
                                                            form.data.ranges.filter(
                                                                (_, i) =>
                                                                    i !== idx,
                                                            ),
                                                        )
                                                    }
                                                >
                                                    <Trash2 className="h-4 w-4 text-destructive" />
                                                </Button>
                                            ) : null}
                                        </div>
                                    </div>
                                ))}
                            </div>
                            <InputError message={form.errors.ranges} />
                        </div>
                    ) : (
                        <div className="space-y-2">
                            <Label>
                                Daftar materi ({form.data.material_ids.length}{' '}
                                dipilih)
                            </Label>
                            <div className="max-h-48 divide-y divide-border overflow-y-auto rounded-md border border-border">
                                {filteredMaterials.length === 0 ? (
                                    <div className="p-3 text-sm text-muted-foreground">
                                        Belum ada materi aktif untuk jenis ini.
                                    </div>
                                ) : (
                                    filteredMaterials.map((m) => (
                                        <label
                                            key={m.id}
                                            className="flex cursor-pointer items-center gap-3 px-3 py-2 text-sm hover:bg-muted/40"
                                        >
                                            <Checkbox
                                                checked={form.data.material_ids.includes(
                                                    m.id,
                                                )}
                                                onCheckedChange={(v) =>
                                                    toggleMaterial(
                                                        m.id,
                                                        v === true,
                                                    )
                                                }
                                            />
                                            <span>{m.title}</span>
                                        </label>
                                    ))
                                )}
                            </div>
                            <InputError message={form.errors.material_ids} />
                        </div>
                    )}
                </div>
            </FormDialog>
        </>
    );
}

MemorizationTargetsIndex.layout = (page: ReactNode) => (
    <StaffLayout
        breadcrumbs={[
            { title: 'Kesiswaan & Asrama', href: '#' },
            {
                title: 'Target hafalan',
                href: MemorizationTargetController.index.url(),
            },
        ]}
    >
        {page}
    </StaffLayout>
);
