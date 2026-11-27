import { Head, router, useForm } from '@inertiajs/react';
import { Download, Plus, Power, PowerOff, Upload } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { useState } from 'react';
import PointRuleController from '@/actions/App/Http/Controllers/Admin/PointRuleController';
import type { DataTableColumn } from '@/components/app/data-table';
import { DataTable } from '@/components/app/data-table';
import { ErrorState } from '@/components/app/error-state';
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
import type { PaginatedResource } from '@/types';

type PointRuleRow = {
    id: number;
    unit_id: number | null;
    unit_name: string;
    code: string;
    name: string;
    category: 'violation' | 'achievement' | 'good_deed';
    category_label: string;
    level: string | null;
    points: number;
    is_active: boolean;
};

type UnitOption = { id: number; code: string; name: string };
type CategoryOption = { value: string; label: string };

type Filters = {
    search: string;
    unit_id: string;
    category: string;
    status: string;
};

type Props = {
    rules: PaginatedResource<PointRuleRow>;
    units: UnitOption[];
    categories: CategoryOption[];
    filters: Filters;
    canManage: boolean;
};

type RuleForm = {
    unit_id: string;
    code: string;
    name: string;
    category: 'violation' | 'achievement' | 'good_deed';
    level: string;
    points: string;
};

const ALL_UNITS = 'semua';
const ACHIEVEMENT_LEVELS = [
    'Sekolah',
    'Kecamatan',
    'Kabupaten',
    'Provinsi',
    'Nasional',
];

function categoryVariant(
    category: PointRuleRow['category'],
): StatusBadgeVariant {
    if (category === 'violation') {
        return 'destructive';
    }
    if (category === 'achievement') {
        return 'success';
    }

    return 'muted';
}

export default function PointRulesIndex({
    rules,
    units,
    categories,
    filters,
    canManage,
}: Props) {
    const [search, setSearch] = useState(filters.search);
    const [loading, setLoading] = useState(false);
    const [loadFailed, setLoadFailed] = useState(false);
    const [formOpen, setFormOpen] = useState(false);
    const [importOpen, setImportOpen] = useState(false);
    const [editing, setEditing] = useState<PointRuleRow | null>(null);

    const form = useForm<RuleForm>({
        unit_id: ALL_UNITS,
        code: '',
        name: '',
        category: 'violation',
        level: '',
        points: '5',
    });

    const importForm = useForm<{ unit_id: string; file: File | null }>({
        unit_id: ALL_UNITS,
        file: null,
    });

    function visitFilters(next: Partial<Filters>, page?: number) {
        const merged = {
            search,
            unit_id: filters.unit_id,
            category: filters.category,
            status: filters.status,
            ...next,
        };

        router.get(
            PointRuleController.index.url({
                query: {
                    search: merged.search || undefined,
                    unit_id: merged.unit_id || undefined,
                    category: merged.category || undefined,
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
        form.setData({
            unit_id: ALL_UNITS,
            code: '',
            name: '',
            category: 'violation',
            level: '',
            points: '5',
        });
        form.clearErrors();
        setFormOpen(true);
    }

    function openEdit(row: PointRuleRow) {
        setEditing(row);
        form.setData({
            unit_id: row.unit_id !== null ? String(row.unit_id) : ALL_UNITS,
            code: row.code,
            name: row.name,
            category: row.category,
            level: row.level ?? '',
            points: String(row.points),
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
            code: form.data.code,
            name: form.data.name,
            category: form.data.category,
            level:
                form.data.category === 'achievement'
                    ? form.data.level || null
                    : null,
            points: Number(form.data.points),
        };

        if (editing) {
            router.put(PointRuleController.update.url(editing.id), payload, {
                preserveScroll: true,
                onSuccess: () => setFormOpen(false),
                onError: (errors) =>
                    form.setError(errors as Record<keyof RuleForm, string>),
            });
            return;
        }

        router.post(PointRuleController.store.url(), payload, {
            preserveScroll: true,
            onSuccess: () => setFormOpen(false),
            onError: (errors) =>
                form.setError(errors as Record<keyof RuleForm, string>),
        });
    }

    function toggleRule(row: PointRuleRow) {
        router.patch(
            PointRuleController.toggle.url(row.id),
            { is_active: !row.is_active },
            { preserveScroll: true },
        );
    }

    function submitImport(e: FormEvent<HTMLFormElement>) {
        e.preventDefault();
        if (!importForm.data.file) {
            importForm.setError(
                'file',
                'Pilih berkas Excel atau CSV terlebih dahulu.',
            );
            return;
        }

        router.post(
            PointRuleController.import.url(),
            {
                unit_id:
                    importForm.data.unit_id === ALL_UNITS
                        ? null
                        : Number(importForm.data.unit_id),
                file: importForm.data.file,
            },
            {
                forceFormData: true,
                preserveScroll: true,
                onSuccess: () => {
                    importForm.reset();
                    setImportOpen(false);
                },
                onError: (errors) =>
                    importForm.setError(
                        errors as Record<'unit_id' | 'file', string>,
                    ),
            },
        );
    }

    const columns: DataTableColumn<PointRuleRow>[] = [
        {
            key: 'code',
            label: 'Kode',
            render: (row) => (
                <span className="font-mono text-xs font-medium">
                    {row.code}
                </span>
            ),
        },
        {
            key: 'name',
            label: 'Nama aturan',
            render: (row) => (
                <div>
                    <div className="font-medium text-foreground">
                        {row.name}
                    </div>
                    {row.level ? (
                        <div className="text-xs text-muted-foreground">
                            Tingkat {row.level}
                        </div>
                    ) : null}
                </div>
            ),
        },
        {
            key: 'category',
            label: 'Kategori',
            align: 'center',
            render: (row) => (
                <StatusBadge variant={categoryVariant(row.category)}>
                    {row.category_label}
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
            key: 'points',
            label: 'Poin',
            align: 'right',
            render: (row) => (
                <span className="font-medium tabular-nums">{row.points}</span>
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
                        itemName={row.code}
                    >
                        <Tooltip>
                            <TooltipTrigger asChild>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    aria-label={
                                        row.is_active
                                            ? `Nonaktifkan ${row.code}`
                                            : `Aktifkan ${row.code}`
                                    }
                                    onClick={() => toggleRule(row)}
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
                                    ? 'Nonaktifkan aturan'
                                    : 'Aktifkan aturan'}
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
            <Head title="Aturan poin" />
            <div className="space-y-6">
                <PageHeader
                    title="Aturan poin"
                    description="Master aturan poin pelanggaran, prestasi, dan kebaikan per unit."
                    actions={
                        canManage ? (
                            <div className="flex flex-wrap items-center gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => {
                                        importForm.clearErrors();
                                        setImportOpen(true);
                                    }}
                                >
                                    <Upload className="mr-2 h-4 w-4" />
                                    Impor Excel
                                </Button>
                                <Button type="button" onClick={openCreate}>
                                    <Plus className="mr-2 h-4 w-4" />
                                    Tambah aturan
                                </Button>
                            </div>
                        ) : null
                    }
                />

                <div className="flex flex-wrap items-center gap-3">
                    <form
                        className="flex min-w-[220px] flex-1 items-center gap-2 sm:max-w-xs"
                        onSubmit={(e) => {
                            e.preventDefault();
                            visitFilters({ search });
                        }}
                    >
                        <Input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Cari kode atau nama aturan..."
                            aria-label="Cari kode atau nama aturan"
                        />
                    </form>

                    <Select
                        value={filters.unit_id || ALL_UNITS}
                        onValueChange={(value) =>
                            visitFilters({
                                unit_id: value === ALL_UNITS ? '' : value,
                            })
                        }
                    >
                        <SelectTrigger
                            className="w-[170px]"
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
                        value={filters.category || 'semua'}
                        onValueChange={(value) =>
                            visitFilters({
                                category: value === 'semua' ? '' : value,
                            })
                        }
                    >
                        <SelectTrigger
                            className="w-[160px]"
                            aria-label="Filter kategori"
                        >
                            <SelectValue placeholder="Semua kategori" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="semua">
                                Semua kategori
                            </SelectItem>
                            {categories.map((cat) => (
                                <SelectItem key={cat.value} value={cat.value}>
                                    {cat.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    <Select
                        value={filters.status || 'semua'}
                        onValueChange={(value) =>
                            visitFilters({
                                status: value === 'semua' ? '' : value,
                            })
                        }
                    >
                        <SelectTrigger
                            className="w-[140px]"
                            aria-label="Filter status"
                        >
                            <SelectValue placeholder="Semua status" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="semua">Semua status</SelectItem>
                            <SelectItem value="active">Aktif</SelectItem>
                            <SelectItem value="inactive">Nonaktif</SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                {loadFailed ? (
                    <ErrorState
                        description="Periksa koneksi lalu coba muat ulang daftar aturan poin."
                        onRetry={() => visitFilters({})}
                    />
                ) : (
                    <DataTable
                        columns={columns}
                        data={rules.data}
                        rowKey={(row) => row.id}
                        loading={loading}
                        pagination={{
                            currentPage: rules.meta.current_page,
                            lastPage: rules.meta.last_page,
                            perPage: rules.meta.per_page,
                            total: rules.meta.total,
                            from: rules.meta.from,
                            to: rules.meta.to,
                        }}
                        onPageChange={(page) => visitFilters({}, page)}
                        empty={{
                            title: 'Belum ada aturan poin',
                            description:
                                'Tambahkan aturan tata tertib atau impor dari Excel untuk mulai mencatat perilaku siswa.',
                            action: canManage ? (
                                <Button type="button" onClick={openCreate}>
                                    <Plus className="mr-2 h-4 w-4" />
                                    Tambah aturan
                                </Button>
                            ) : undefined,
                        }}
                    />
                )}
            </div>

            <FormDialog
                open={formOpen}
                onOpenChange={setFormOpen}
                title={editing ? 'Ubah aturan poin' : 'Tambah aturan poin'}
                onSubmit={submitForm}
                processing={form.processing}
            >
                <div className="space-y-4">
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div className="space-y-2">
                            <Label htmlFor="rule-unit">Unit</Label>
                            <Select
                                value={form.data.unit_id}
                                onValueChange={(v) =>
                                    form.setData('unit_id', v)
                                }
                            >
                                <SelectTrigger id="rule-unit">
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

                        <div className="space-y-2">
                            <Label htmlFor="rule-code">Kode</Label>
                            <Input
                                id="rule-code"
                                value={form.data.code}
                                onChange={(e) =>
                                    form.setData('code', e.target.value)
                                }
                                placeholder="Contoh: P-01"
                                required
                            />
                            <InputError message={form.errors.code} />
                        </div>
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="rule-name">Nama aturan</Label>
                        <Input
                            id="rule-name"
                            value={form.data.name}
                            onChange={(e) =>
                                form.setData('name', e.target.value)
                            }
                            placeholder="Contoh: Terlambat masuk madrasah"
                            required
                        />
                        <InputError message={form.errors.name} />
                    </div>

                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div className="space-y-2">
                            <Label htmlFor="rule-category">Kategori</Label>
                            <Select
                                value={form.data.category}
                                onValueChange={(v) =>
                                    form.setData(
                                        'category',
                                        v as RuleForm['category'],
                                    )
                                }
                            >
                                <SelectTrigger id="rule-category">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {categories.map((cat) => (
                                        <SelectItem
                                            key={cat.value}
                                            value={cat.value}
                                        >
                                            {cat.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={form.errors.category} />
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="rule-points">Bobot poin</Label>
                            <Input
                                id="rule-points"
                                type="number"
                                min={1}
                                max={1000}
                                value={form.data.points}
                                onChange={(e) =>
                                    form.setData('points', e.target.value)
                                }
                                required
                            />
                            <InputError message={form.errors.points} />
                        </div>
                    </div>

                    {form.data.category === 'achievement' ? (
                        <div className="space-y-2">
                            <Label htmlFor="rule-level">Tingkat prestasi</Label>
                            <Select
                                value={form.data.level || 'Sekolah'}
                                onValueChange={(v) => form.setData('level', v)}
                            >
                                <SelectTrigger id="rule-level">
                                    <SelectValue placeholder="Pilih tingkat" />
                                </SelectTrigger>
                                <SelectContent>
                                    {ACHIEVEMENT_LEVELS.map((lvl) => (
                                        <SelectItem key={lvl} value={lvl}>
                                            {lvl}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={form.errors.level} />
                        </div>
                    ) : null}
                </div>
            </FormDialog>

            <FormDialog
                open={importOpen}
                onOpenChange={setImportOpen}
                title="Impor aturan poin dari Excel"
                submitLabel="Impor"
                onSubmit={submitImport}
                processing={importForm.processing}
            >
                <div className="space-y-4">
                    <div className="flex items-center justify-between rounded-md border border-border bg-muted/40 px-3 py-2 text-sm">
                        <span className="text-muted-foreground">
                            Kolom wajib: kode, nama, kategori, poin
                        </span>
                        <a
                            href={PointRuleController.template.url()}
                            className="inline-flex items-center gap-1.5 font-medium text-primary hover:underline"
                        >
                            <Download className="h-4 w-4" />
                            Unduh templat
                        </a>
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="import-unit">Target unit</Label>
                        <Select
                            value={importForm.data.unit_id}
                            onValueChange={(v) =>
                                importForm.setData('unit_id', v)
                            }
                        >
                            <SelectTrigger id="import-unit">
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
                        <InputError message={importForm.errors.unit_id} />
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="import-file">
                            Berkas (.xlsx atau .csv)
                        </Label>
                        <Input
                            id="import-file"
                            type="file"
                            accept=".xlsx,.csv"
                            onChange={(e) =>
                                importForm.setData(
                                    'file',
                                    e.target.files?.[0] ?? null,
                                )
                            }
                        />
                        <InputError message={importForm.errors.file} />
                    </div>
                </div>
            </FormDialog>
        </>
    );
}

PointRulesIndex.layout = (page: ReactNode) => (
    <StaffLayout
        breadcrumbs={[
            { title: 'Kesiswaan', href: '#' },
            { title: 'Aturan poin', href: PointRuleController.index.url() },
        ]}
    >
        {page}
    </StaffLayout>
);
