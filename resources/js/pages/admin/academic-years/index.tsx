import { DateInput } from '@/components/app/date-input';
import { Head, router, useForm } from '@inertiajs/react';
import { CircleCheck, Plus } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import AcademicYearController from '@/actions/App/Http/Controllers/Admin/AcademicYearController';
import { ConfirmDialog } from '@/components/app/confirm-dialog';
import type { DataTableColumn } from '@/components/app/data-table';
import { DataTable } from '@/components/app/data-table';
import { DateText } from '@/components/app/date-text';
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
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import StaffLayout from '@/layouts/staff-layout';
import type { PaginatedResource } from '@/types';

type Option = { value: string; label: string };

type AcademicYear = {
    id: number;
    name: string;
    semester: Option;
    starts_on: string;
    ends_on: string;
    is_active: boolean;
};

type YearForm = {
    name: string;
    semester: string;
    starts_on: string;
    ends_on: string;
};

type Props = {
    academicYears: PaginatedResource<AcademicYear>;
    semesterOptions: Option[];
};

const emptyForm: YearForm = {
    name: '',
    semester: 'ganjil',
    starts_on: '',
    ends_on: '',
};

export default function AcademicYearsIndex({
    academicYears,
    semesterOptions,
}: Props) {
    const [editing, setEditing] = useState<AcademicYear | null>(null);
    const [formOpen, setFormOpen] = useState(false);
    const [activateTarget, setActivateTarget] = useState<AcademicYear | null>(
        null,
    );
    const [acting, setActing] = useState(false);
    const [loading, setLoading] = useState(false);

    const form = useForm<YearForm>(emptyForm);
    const label = (year: AcademicYear): string =>
        `${year.name} ${year.semester.label}`;

    const openCreate = (): void => {
        setEditing(null);
        form.clearErrors();
        form.setData(emptyForm);
        setFormOpen(true);
    };

    const openEdit = (year: AcademicYear): void => {
        setEditing(year);
        form.clearErrors();
        form.setData({
            name: year.name,
            semester: year.semester.value,
            starts_on: year.starts_on,
            ends_on: year.ends_on,
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
            form.put(AcademicYearController.update.url(editing.id), options);
        } else {
            form.post(AcademicYearController.store.url(), options);
        }
    };

    const confirmActivate = (): void => {
        if (!activateTarget) {
            return;
        }

        router.post(
            AcademicYearController.activate.url(activateTarget.id),
            {},
            {
                preserveScroll: true,
                onStart: () => setActing(true),
                onFinish: () => {
                    setActing(false);
                    setActivateTarget(null);
                },
            },
        );
    };

    const destroy = (year: AcademicYear): void => {
        router.delete(AcademicYearController.destroy.url(year.id), {
            preserveScroll: true,
        });
    };

    const goToPage = (page: number): void => {
        router.get(
            AcademicYearController.index.url(),
            { page },
            {
                preserveState: true,
                preserveScroll: true,
                only: ['academicYears'],
                onStart: () => setLoading(true),
                onFinish: () => setLoading(false),
            },
        );
    };

    const columns: DataTableColumn<AcademicYear>[] = [
        {
            key: 'name',
            label: 'Tahun ajaran',
            render: (year) => (
                <div className="min-w-32">
                    <div className="font-medium text-foreground tabular-nums">
                        {year.name}
                    </div>
                    <div className="text-xs text-muted-foreground">
                        Semester {year.semester.label}
                    </div>
                </div>
            ),
        },
        {
            key: 'period',
            label: 'Periode',
            className: 'whitespace-nowrap',
            render: (year) => (
                <span className="tabular-nums">
                    <DateText value={year.starts_on} /> –{' '}
                    <DateText value={year.ends_on} />
                </span>
            ),
        },
        {
            key: 'status',
            label: 'Status',
            align: 'center',
            render: (year) =>
                year.is_active ? (
                    <StatusBadge variant="success">Aktif</StatusBadge>
                ) : (
                    <span className="text-muted-foreground">—</span>
                ),
        },
        {
            key: 'actions',
            label: 'Aksi',
            align: 'center',
            render: (year) => (
                <RowActions
                    itemName={label(year)}
                    onEdit={() => openEdit(year)}
                    onDelete={year.is_active ? undefined : () => destroy(year)}
                    deleteTitle={`Hapus ${label(year)}?`}
                    deleteDescription="Tahun ajaran ini dihapus permanen. Tahun ajaran yang sudah dipakai rombel tidak dapat dihapus."
                >
                    {!year.is_active && (
                        <Tooltip>
                            <TooltipTrigger asChild>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    aria-label={`Jadikan aktif ${label(year)}`}
                                    onClick={() => setActivateTarget(year)}
                                >
                                    <CircleCheck
                                        className="size-4"
                                        strokeWidth={1.75}
                                    />
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent>Jadikan aktif</TooltipContent>
                        </Tooltip>
                    )}
                </RowActions>
            ),
        },
    ];

    return (
        <StaffLayout
            breadcrumbs={[
                {
                    title: 'Data Induk',
                    href: AcademicYearController.index(),
                },
                {
                    title: 'Tahun ajaran',
                    href: AcademicYearController.index(),
                },
            ]}
        >
            <Head title="Tahun ajaran" />

            <div className="space-y-4">
                <PageHeader
                    title="Tahun ajaran"
                    description="Tepat satu tahun ajaran dan semester aktif. Dipakai sebagai bawaan rombel, presensi, dan laporan."
                    actions={
                        <Button type="button" onClick={openCreate}>
                            <Plus className="size-4" strokeWidth={1.75} />
                            Tambah tahun ajaran
                        </Button>
                    }
                />

                <DataTable
                    columns={columns}
                    data={academicYears.data}
                    rowKey={(year) => year.id}
                    loading={loading}
                    pagination={{
                        currentPage: academicYears.meta.current_page,
                        lastPage: academicYears.meta.last_page,
                        perPage: academicYears.meta.per_page,
                        total: academicYears.meta.total,
                        from: academicYears.meta.from,
                        to: academicYears.meta.to,
                    }}
                    onPageChange={goToPage}
                    empty={{
                        title: 'Belum ada tahun ajaran',
                        description:
                            'Tambahkan tahun ajaran dan semester berjalan, lalu jadikan aktif agar rombel dan presensi bisa dibuat.',
                        action: (
                            <Button type="button" onClick={openCreate}>
                                <Plus className="size-4" strokeWidth={1.75} />
                                Tambah tahun ajaran
                            </Button>
                        ),
                    }}
                />
            </div>

            <FormDialog
                open={formOpen}
                onOpenChange={setFormOpen}
                title={
                    editing ? `Ubah ${label(editing)}` : 'Tambah tahun ajaran'
                }
                description={
                    editing
                        ? undefined
                        : 'Tahun ajaran baru belum aktif sampai Anda menjadikannya aktif.'
                }
                onSubmit={submit}
                processing={form.processing}
            >
                <div className="grid items-start gap-4 sm:grid-cols-2">
                    <div className="grid gap-2">
                        <Label htmlFor="year-name">Tahun ajaran</Label>
                        <Input
                            id="year-name"
                            value={form.data.name}
                            onChange={(event) =>
                                form.setData('name', event.target.value)
                            }
                            inputMode="numeric"
                            placeholder="2026/2027"
                            maxLength={9}
                            autoComplete="off"
                            className="tabular-nums"
                            required
                            aria-invalid={Boolean(form.errors.name)}
                        />
                        <InputError message={form.errors.name} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="year-semester">Semester</Label>
                        <Select
                            value={form.data.semester}
                            onValueChange={(value) =>
                                form.setData('semester', value)
                            }
                        >
                            <SelectTrigger
                                id="year-semester"
                                aria-invalid={Boolean(form.errors.semester)}
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {semesterOptions.map((option) => (
                                    <SelectItem
                                        key={option.value}
                                        value={option.value}
                                    >
                                        {option.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={form.errors.semester} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="year-starts">Tanggal mulai</Label>
                        <DateInput
                            id="year-starts"
                            value={form.data.starts_on}
                            onChange={(event) =>
                                form.setData('starts_on', event.target.value)
                            }
                            required
                            aria-invalid={Boolean(form.errors.starts_on)}
                        />
                        <InputError message={form.errors.starts_on} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="year-ends">Tanggal selesai</Label>
                        <DateInput
                            id="year-ends"
                            value={form.data.ends_on}
                            onChange={(event) =>
                                form.setData('ends_on', event.target.value)
                            }
                            required
                            aria-invalid={Boolean(form.errors.ends_on)}
                        />
                        <InputError message={form.errors.ends_on} />
                    </div>
                </div>
            </FormDialog>

            <ConfirmDialog
                open={activateTarget !== null}
                onOpenChange={(open) => !open && setActivateTarget(null)}
                title={`Jadikan ${activateTarget ? label(activateTarget) : ''} aktif?`}
                description="Tahun ajaran yang sekarang aktif akan dinonaktifkan. Rombel, presensi, dan laporan baru memakai tahun ajaran ini."
                confirmLabel="Jadikan aktif"
                loading={acting}
                onConfirm={confirmActivate}
            />
        </StaffLayout>
    );
}
