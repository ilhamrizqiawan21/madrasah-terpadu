import { Head, Link, router, useForm } from '@inertiajs/react';
import { CopyPlus, Plus, Power, PowerOff, Printer } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { useEffect, useRef, useState } from 'react';
import AcademicYearController from '@/actions/App/Http/Controllers/Admin/AcademicYearController';
import ClassroomController from '@/actions/App/Http/Controllers/Admin/ClassroomController';
import { ConfirmDialog } from '@/components/app/confirm-dialog';
import type { DataTableColumn } from '@/components/app/data-table';
import { DataTable } from '@/components/app/data-table';
import { ErrorState } from '@/components/app/error-state';
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

type Classroom = {
    id: number;
    grade: string;
    name: string;
    is_active: boolean;
    unit: { id: number; code: string };
    academic_year: { id: number; label: string };
    homeroom_teacher: { id: number; name: string } | null;
    major_id: number | null;
    major_name: string | null;
    capacity: number | null;
};

type UnitOption = { id: number; code: string; name: string };
type YearOption = { id: number; label: string; is_active: boolean };
type TeacherOption = { id: number; name: string };

type Filters = {
    unit_id: number | null;
    academic_year_id: number | null;
    search: string;
};

type Props = {
    classrooms: PaginatedResource<Classroom>;
    studentCounts: Record<number, number>;
    filters: Filters;
    unitOptions: UnitOption[];
    academicYearOptions: YearOption[];
    teacherOptions: TeacherOption[];
    gradeOptions: string[];
    groupOptions: string[];
    majorOptions: { id: number; name: string }[];
};

type ClassroomForm = {
    unit_id: number | null;
    academic_year_id: number | null;
    grade: string;
    name: string;
    homeroom_teacher_id: number | null;
    major_id: number | null;
    capacity: string;
};

type CopyForm = {
    unit_id: number | null;
    academic_year_id: number | null;
    source_academic_year_id: string;
};

const NO_TEACHER = 'tanpa';

export default function ClassroomsIndex({
    classrooms,
    studentCounts,
    filters,
    unitOptions,
    academicYearOptions,
    teacherOptions,
    gradeOptions,
    groupOptions,
    majorOptions,
}: Props) {
    const [search, setSearch] = useState(filters.search);
    const [loading, setLoading] = useState(false);
    const [loadFailed, setLoadFailed] = useState(false);
    const [editing, setEditing] = useState<Classroom | null>(null);
    const [formOpen, setFormOpen] = useState(false);
    const [copyOpen, setCopyOpen] = useState(false);
    const [statusTarget, setStatusTarget] = useState<Classroom | null>(null);
    const [acting, setActing] = useState(false);
    const searchTimer = useRef<number | undefined>(undefined);

    const unit = unitOptions.find((option) => option.id === filters.unit_id);
    const year = academicYearOptions.find(
        (option) => option.id === filters.academic_year_id,
    );
    const ready = unit !== undefined && year !== undefined;
    const scopeLabel = ready ? `${unit.code} · ${year.label}` : '';
    const sourceYears = academicYearOptions.filter(
        (option) => option.id !== filters.academic_year_id,
    );

    const form = useForm<ClassroomForm>({
        unit_id: null,
        academic_year_id: null,
        grade: '',
        name: '',
        homeroom_teacher_id: null,
        major_id: null,
        capacity: '',
    });
    const copyForm = useForm<CopyForm>({
        unit_id: null,
        academic_year_id: null,
        source_academic_year_id: '',
    });

    useEffect(() => () => window.clearTimeout(searchTimer.current), []);

    const visit = (
        changes: Partial<Filters> & { page?: number },
        searchValue: string = search,
    ): void => {
        const query = { ...filters, search: searchValue, ...changes };
        const params = Object.fromEntries(
            Object.entries(query).filter(
                ([, value]) =>
                    value !== '' && value !== null && value !== undefined,
            ),
        );

        router.get(ClassroomController.index.url(), params, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['classrooms', 'filters'],
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
        form.setData({
            unit_id: filters.unit_id,
            academic_year_id: filters.academic_year_id,
            grade: '',
            name: '',
            homeroom_teacher_id: null,
            major_id: null,
            capacity: '',
        });
        setFormOpen(true);
    };

    const openEdit = (classroom: Classroom): void => {
        setEditing(classroom);
        form.clearErrors();
        form.setData({
            unit_id: classroom.unit.id,
            academic_year_id: classroom.academic_year.id,
            grade: classroom.grade,
            name: classroom.name,
            homeroom_teacher_id: classroom.homeroom_teacher?.id ?? null,
            major_id: classroom.major_id,
            capacity:
                classroom.capacity === null ? '' : String(classroom.capacity),
        });
        setFormOpen(true);
    };

    const openCopy = (): void => {
        copyForm.clearErrors();
        copyForm.setData({
            unit_id: filters.unit_id,
            academic_year_id: filters.academic_year_id,
            // Bawaan: tahun ajaran tepat sebelum tujuan pada urutan terbaru-dulu.
            source_academic_year_id: String(
                academicYearOptions[
                    academicYearOptions.findIndex(
                        (option) => option.id === filters.academic_year_id,
                    ) + 1
                ]?.id ??
                    sourceYears[0]?.id ??
                    '',
            ),
        });
        setCopyOpen(true);
    };

    const submit = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();

        const options = {
            preserveScroll: true,
            onSuccess: () => setFormOpen(false),
        };

        if (editing) {
            form.put(ClassroomController.update.url(editing.id), options);
        } else {
            form.post(ClassroomController.store.url(), options);
        }
    };

    const submitCopy = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();

        copyForm.post(ClassroomController.copy.url(), {
            preserveScroll: true,
            onSuccess: () => setCopyOpen(false),
        });
    };

    const confirmStatus = (): void => {
        if (!statusTarget) {
            return;
        }

        router.patch(
            ClassroomController.updateStatus.url(statusTarget.id),
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

    const addButton = (
        <Button type="button" onClick={openCreate} disabled={!ready}>
            <Plus className="size-4" strokeWidth={1.75} />
            Tambah rombel
        </Button>
    );

    const copyButton = (
        <Button
            type="button"
            variant="outline"
            onClick={openCopy}
            disabled={!ready || sourceYears.length === 0}
        >
            <CopyPlus className="size-4" strokeWidth={1.75} />
            Salin dari tahun lain
        </Button>
    );

    const emptyState = (): {
        title: string;
        description: string;
        action?: ReactNode;
    } => {
        if (unitOptions.length === 0) {
            return {
                title: 'Belum ada unit aktif',
                description:
                    'Rombel selalu milik satu unit. Aktifkan atau tambahkan unit di Pengaturan > Unit & jam presensi.',
            };
        }

        if (academicYearOptions.length === 0) {
            return {
                title: 'Belum ada tahun ajaran',
                description:
                    'Rombel selalu milik satu tahun ajaran. Tambahkan tahun ajaran lebih dulu.',
                action: (
                    <Button asChild variant="outline">
                        <Link href={AcademicYearController.index()}>
                            Buka tahun ajaran
                        </Link>
                    </Button>
                ),
            };
        }

        if (!ready) {
            return {
                title: 'Pilih unit dan tahun ajaran',
                description:
                    'Pilih unit dan tahun ajaran di atas untuk melihat rombelnya.',
            };
        }

        if (filters.search !== '') {
            return {
                title: 'Tidak ada rombel yang cocok',
                description: `Tidak ada rombel ${scopeLabel} dengan nama "${filters.search}".`,
            };
        }

        return {
            title: `Belum ada rombel ${scopeLabel}`,
            description:
                sourceYears.length > 0
                    ? 'Tambahkan rombel satu per satu, atau salin dari tahun ajaran lain lalu sesuaikan wali kelasnya.'
                    : 'Tambahkan rombel beserta wali kelasnya.',
            action: (
                <div className="flex flex-wrap justify-center gap-2">
                    {sourceYears.length > 0 && copyButton}
                    {addButton}
                </div>
            ),
        };
    };

    const columns: DataTableColumn<Classroom>[] = [
        {
            key: 'grade',
            label: 'Tingkat kelas',
            render: (classroom) => (
                <span className="font-medium">{classroom.grade}</span>
            ),
        },
        {
            key: 'name',
            label: 'Nama rombel',
            render: (classroom) => (
                <div className="min-w-24">
                    <div className="font-medium text-foreground">
                        {classroom.name}
                    </div>
                    {classroom.major_name && (
                        <div className="text-xs text-muted-foreground @3xl:hidden">
                            {classroom.major_name}
                        </div>
                    )}
                </div>
            ),
        },
        {
            key: 'major',
            label: 'Jurusan',
            className: 'hidden @3xl:table-cell',
            render: (classroom) =>
                classroom.major_name ?? (
                    <span className="text-muted-foreground">—</span>
                ),
        },
        {
            key: 'homeroom_teacher',
            label: 'Wali kelas',
            render: (classroom) =>
                classroom.homeroom_teacher?.name ?? (
                    <span className="text-muted-foreground">
                        Belum ada wali kelas
                    </span>
                ),
        },
        {
            key: 'students',
            label: 'Siswa',
            align: 'right',
            className: 'tabular-nums',
            render: (classroom) => {
                const count = studentCounts[classroom.id] ?? 0;

                return (
                    <span
                        className={
                            classroom.capacity !== null &&
                            count > classroom.capacity
                                ? 'text-destructive'
                                : undefined
                        }
                    >
                        {count}
                        {classroom.capacity !== null
                            ? ` / ${classroom.capacity}`
                            : ''}
                    </span>
                );
            },
        },
        {
            key: 'status',
            label: 'Status',
            align: 'center',
            render: (classroom) =>
                classroom.is_active ? (
                    <StatusBadge variant="success">Aktif</StatusBadge>
                ) : (
                    <StatusBadge variant="muted">Nonaktif</StatusBadge>
                ),
        },
        {
            key: 'actions',
            label: 'Aksi',
            align: 'center',
            render: (classroom) => (
                <RowActions
                    itemName={`rombel ${classroom.name}`}
                    onEdit={() => openEdit(classroom)}
                >
                    <Tooltip>
                        <TooltipTrigger asChild>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                aria-label={`${classroom.is_active ? 'Nonaktifkan' : 'Aktifkan'} rombel ${classroom.name}`}
                                onClick={() => setStatusTarget(classroom)}
                                className={
                                    classroom.is_active
                                        ? 'text-destructive hover:bg-destructive/10 hover:text-destructive'
                                        : undefined
                                }
                            >
                                {classroom.is_active ? (
                                    <PowerOff
                                        className="size-4"
                                        strokeWidth={1.75}
                                    />
                                ) : (
                                    <Power
                                        className="size-4"
                                        strokeWidth={1.75}
                                    />
                                )}
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent>
                            {classroom.is_active ? 'Nonaktifkan' : 'Aktifkan'}
                        </TooltipContent>
                    </Tooltip>
                </RowActions>
            ),
        },
    ];

    return (
        <StaffLayout
            breadcrumbs={[
                { title: 'Akademik', href: ClassroomController.index() },
                {
                    title: 'Kelas & Wali Kelas',
                    href: ClassroomController.index(),
                },
            ]}
        >
            <Head title="Kelas & Wali Kelas" />

            <div className="@container space-y-4">
                <PageHeader
                    title="Kelas & Wali Kelas"
                    description="Rombel per unit dan tahun ajaran: tingkat, jurusan, kuota, dan wali kelasnya."
                    actions={
                        <div className="flex flex-wrap gap-2">
                            <Button
                                type="button"
                                variant="soft"
                                onClick={() => window.print()}
                            >
                                <Printer
                                    className="size-4"
                                    strokeWidth={1.75}
                                />
                                Cetak daftar rombel
                            </Button>
                            {copyButton}
                            {addButton}
                        </div>
                    }
                />

                <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
                    <Input
                        type="search"
                        value={search}
                        onChange={(event) => changeSearch(event.target.value)}
                        placeholder="Cari nama rombel"
                        aria-label="Cari rombel"
                        className="w-full sm:max-w-xs"
                    />
                    <Select
                        value={
                            filters.unit_id === null
                                ? undefined
                                : String(filters.unit_id)
                        }
                        onValueChange={(value) =>
                            visit({ unit_id: Number(value), page: 1 })
                        }
                        disabled={unitOptions.length === 0}
                    >
                        <SelectTrigger
                            className="w-full sm:w-64"
                            aria-label="Saring unit"
                        >
                            <SelectValue placeholder="Pilih unit" />
                        </SelectTrigger>
                        <SelectContent>
                            {unitOptions.map((option) => (
                                <SelectItem
                                    key={option.id}
                                    value={String(option.id)}
                                >
                                    {option.code} · {option.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <Select
                        value={
                            filters.academic_year_id === null
                                ? undefined
                                : String(filters.academic_year_id)
                        }
                        onValueChange={(value) =>
                            visit({ academic_year_id: Number(value), page: 1 })
                        }
                        disabled={academicYearOptions.length === 0}
                    >
                        <SelectTrigger
                            className="w-full sm:w-56"
                            aria-label="Saring tahun ajaran"
                        >
                            <SelectValue placeholder="Pilih tahun ajaran" />
                        </SelectTrigger>
                        <SelectContent>
                            {academicYearOptions.map((option) => (
                                <SelectItem
                                    key={option.id}
                                    value={String(option.id)}
                                >
                                    {option.label}
                                    {option.is_active ? ' (aktif)' : ''}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                {loadFailed ? (
                    <ErrorState
                        description="Daftar rombel tidak dapat dimuat. Periksa koneksi lalu coba lagi."
                        onRetry={() => visit({})}
                    />
                ) : (
                    <DataTable
                        columns={columns}
                        data={classrooms.data}
                        rowKey={(classroom) => classroom.id}
                        loading={loading}
                        pagination={{
                            currentPage: classrooms.meta.current_page,
                            lastPage: classrooms.meta.last_page,
                            perPage: classrooms.meta.per_page,
                            total: classrooms.meta.total,
                            from: classrooms.meta.from,
                            to: classrooms.meta.to,
                        }}
                        onPageChange={(page) => visit({ page })}
                        empty={emptyState()}
                    />
                )}
            </div>

            <FormDialog
                open={formOpen}
                onOpenChange={setFormOpen}
                title={
                    editing ? `Ubah rombel ${editing.name}` : 'Tambah rombel'
                }
                description={
                    editing
                        ? `${editing.unit.code} · ${editing.academic_year.label}`
                        : scopeLabel
                }
                onSubmit={submit}
                processing={form.processing}
            >
                <InputError message={form.errors.unit_id} />
                <InputError message={form.errors.academic_year_id} />
                <div className="grid items-start gap-4 sm:grid-cols-[8rem_1fr]">
                    <div className="grid gap-2">
                        <Label htmlFor="classroom-grade">Tingkat</Label>
                        <Input
                            id="classroom-grade"
                            value={form.data.grade}
                            onChange={(event) =>
                                form.setData('grade', event.target.value)
                            }
                            placeholder="1"
                            list="classroom-grade-options"
                            maxLength={10}
                            autoComplete="off"
                            required
                            aria-invalid={Boolean(form.errors.grade)}
                        />
                        <InputError message={form.errors.grade} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="classroom-name">Nama rombel</Label>
                        <Input
                            id="classroom-name"
                            value={form.data.name}
                            onChange={(event) =>
                                form.setData('name', event.target.value)
                            }
                            placeholder="1-A"
                            list="classroom-group-options"
                            maxLength={30}
                            autoComplete="off"
                            required
                            aria-invalid={Boolean(form.errors.name)}
                        />
                        <InputError message={form.errors.name} />
                    </div>
                </div>
                <datalist id="classroom-grade-options">
                    {gradeOptions.map((option) => (
                        <option key={option} value={option} />
                    ))}
                </datalist>
                <datalist id="classroom-group-options">
                    {groupOptions.map((option) => (
                        <option key={option} value={option} />
                    ))}
                </datalist>
                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="grid gap-2">
                        <Label htmlFor="classroom-major">
                            Jurusan (opsional)
                        </Label>
                        <Select
                            value={
                                form.data.major_id === null
                                    ? 'none'
                                    : String(form.data.major_id)
                            }
                            onValueChange={(value) =>
                                form.setData(
                                    'major_id',
                                    value === 'none' ? null : Number(value),
                                )
                            }
                        >
                            <SelectTrigger id="classroom-major">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="none">
                                    Tanpa jurusan
                                </SelectItem>
                                {majorOptions.map((major) => (
                                    <SelectItem
                                        key={major.id}
                                        value={String(major.id)}
                                    >
                                        {major.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={form.errors.major_id} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="classroom-capacity">
                            Kuota siswa (opsional)
                        </Label>
                        <Input
                            id="classroom-capacity"
                            type="number"
                            inputMode="numeric"
                            min={1}
                            max={200}
                            value={form.data.capacity}
                            onChange={(event) =>
                                form.setData('capacity', event.target.value)
                            }
                        />
                        <InputError message={form.errors.capacity} />
                    </div>
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="classroom-teacher">Wali kelas</Label>
                    <Select
                        value={
                            form.data.homeroom_teacher_id === null
                                ? NO_TEACHER
                                : String(form.data.homeroom_teacher_id)
                        }
                        onValueChange={(value) =>
                            form.setData(
                                'homeroom_teacher_id',
                                value === NO_TEACHER ? null : Number(value),
                            )
                        }
                    >
                        <SelectTrigger
                            id="classroom-teacher"
                            className="w-full"
                            aria-invalid={Boolean(
                                form.errors.homeroom_teacher_id,
                            )}
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={NO_TEACHER}>
                                Belum ditentukan
                            </SelectItem>
                            {teacherOptions.map((teacher) => (
                                <SelectItem
                                    key={teacher.id}
                                    value={String(teacher.id)}
                                >
                                    {teacher.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <InputError message={form.errors.homeroom_teacher_id} />
                </div>
            </FormDialog>

            <FormDialog
                open={copyOpen}
                onOpenChange={setCopyOpen}
                title="Salin rombel dari tahun lain"
                description={`Rombel aktif ${unit?.code ?? ''} disalin ke ${year?.label ?? ''} beserta wali kelasnya. Nama yang sudah ada dilewati.`}
                submitLabel="Salin rombel"
                onSubmit={submitCopy}
                processing={copyForm.processing}
            >
                <div className="grid gap-2">
                    <Label htmlFor="copy-source">Salin dari</Label>
                    <Select
                        value={copyForm.data.source_academic_year_id}
                        onValueChange={(value) =>
                            copyForm.setData('source_academic_year_id', value)
                        }
                    >
                        <SelectTrigger
                            id="copy-source"
                            className="w-full"
                            aria-invalid={Boolean(
                                copyForm.errors.source_academic_year_id,
                            )}
                        >
                            <SelectValue placeholder="Pilih tahun ajaran" />
                        </SelectTrigger>
                        <SelectContent>
                            {sourceYears.map((option) => (
                                <SelectItem
                                    key={option.id}
                                    value={String(option.id)}
                                >
                                    {option.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <InputError
                        message={copyForm.errors.source_academic_year_id}
                    />
                    <InputError message={copyForm.errors.academic_year_id} />
                </div>
            </FormDialog>

            <ConfirmDialog
                open={statusTarget !== null}
                onOpenChange={(open) => !open && setStatusTarget(null)}
                title={
                    statusTarget?.is_active
                        ? `Nonaktifkan rombel ${statusTarget.name}?`
                        : `Aktifkan rombel ${statusTarget?.name ?? ''}?`
                }
                description={
                    statusTarget?.is_active
                        ? 'Rombel nonaktif tidak ikut disalin ke tahun lain. Datanya tetap tersimpan dan bisa diaktifkan kembali.'
                        : 'Rombel kembali aktif dan ikut disalin ke tahun lain.'
                }
                confirmLabel={
                    statusTarget?.is_active ? 'Nonaktifkan' : 'Aktifkan'
                }
                variant={statusTarget?.is_active ? 'destructive' : 'default'}
                loading={acting}
                onConfirm={confirmStatus}
            />
        </StaffLayout>
    );
}
