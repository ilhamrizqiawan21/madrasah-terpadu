import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    ImageUp,
    Plus,
    Power,
    PowerOff,
    Trash2,
    UserRound,
} from 'lucide-react';
import type { ChangeEvent, FormEvent, ReactNode } from 'react';
import { useEffect, useRef, useState } from 'react';
import ClassroomController from '@/actions/App/Http/Controllers/Admin/ClassroomController';
import StudentController from '@/actions/App/Http/Controllers/Admin/StudentController';
import { ConfirmDialog } from '@/components/app/confirm-dialog';
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
import {
    Dialog,
    DialogContent,
    DialogDescription,
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
import { formatDate } from '@/lib/format';
import type { PaginatedResource } from '@/types';

type StudentStatus = 'active' | 'graduated' | 'moved' | 'inactive';
type Gender = 'L' | 'P';

type Student = {
    id: number;
    nis: string;
    nisn: string | null;
    name: string;
    nickname: string | null;
    gender: { value: Gender; label: string };
    birth_place: string | null;
    birth_date: string | null;
    address: string | null;
    status: { value: StudentStatus; label: string };
    classroom: { id: number; name: string } | null;
    active_classroom_id: number | null;
    enrollments: { id: number; academic_year: string; classroom: string }[];
    photo_url: string | null;
};

type Option = { id: number; name: string };
type UnitOption = { id: number; code: string; name: string };
type StatusOption = { value: StudentStatus; label: string };

type Filters = {
    unit_id: number | null;
    academic_year_id: number | null;
    classroom_id: string | null;
    status: string;
    search: string;
};

type Props = {
    students: PaginatedResource<Student>;
    filters: Filters;
    unitHasStudents: boolean;
    academicYearLabel: string | null;
    activeAcademicYear: { id: number; label: string } | null;
    unitOptions: UnitOption[];
    classroomOptions: Option[];
    enrollmentClassroomOptions: Option[];
    statusOptions: StatusOption[];
};

type StudentForm = {
    unit_id: number | null;
    nis: string;
    nisn: string;
    name: string;
    nickname: string;
    gender: Gender | '';
    birth_place: string;
    birth_date: string;
    address: string;
    status: StudentStatus;
    classroom_id: number | null;
    photo: File | null;
    remove_photo: boolean;
};

const ALL = 'all';
const ALL_CLASSROOMS = 'semua';
const WITHOUT_CLASSROOM = 'none';
const NO_CLASSROOM = 'tanpa';

const statusVariant: Record<StudentStatus, StatusBadgeVariant> = {
    active: 'success',
    graduated: 'default',
    moved: 'muted',
    inactive: 'muted',
};

const emptyForm = (unitId: number | null): StudentForm => ({
    unit_id: unitId,
    nis: '',
    nisn: '',
    name: '',
    nickname: '',
    gender: '',
    birth_place: '',
    birth_date: '',
    address: '',
    status: 'active',
    classroom_id: null,
    photo: null,
    remove_photo: false,
});

function StudentPhoto({
    url,
    name,
    className,
}: {
    url: string | null;
    name: string;
    className: string;
}) {
    if (url) {
        return (
            <img
                src={url}
                alt={`Foto ${name}`}
                className={`${className} rounded-md border border-border object-cover`}
            />
        );
    }

    return (
        <div
            role="img"
            aria-label={`${name} belum punya foto`}
            className={`${className} flex items-center justify-center rounded-md border border-border bg-muted text-muted-foreground`}
        >
            <UserRound className="size-1/2" strokeWidth={1.25} />
        </div>
    );
}

export default function StudentsIndex({
    students,
    filters,
    unitHasStudents,
    academicYearLabel,
    activeAcademicYear,
    unitOptions,
    classroomOptions,
    enrollmentClassroomOptions,
    statusOptions,
}: Props) {
    const [search, setSearch] = useState(filters.search);
    const [loading, setLoading] = useState(false);
    const [loadFailed, setLoadFailed] = useState(false);
    const [editing, setEditing] = useState<Student | null>(null);
    const [formOpen, setFormOpen] = useState(false);
    const [viewing, setViewing] = useState<Student | null>(null);
    const [statusTarget, setStatusTarget] = useState<Student | null>(null);
    const [acting, setActing] = useState(false);
    const [preview, setPreview] = useState<string | null>(null);
    const searchTimer = useRef<number | undefined>(undefined);
    const fileInput = useRef<HTMLInputElement>(null);

    const unit = unitOptions.find((option) => option.id === filters.unit_id);
    const ready = unit !== undefined;

    const form = useForm<StudentForm>(emptyForm(null));

    useEffect(() => () => window.clearTimeout(searchTimer.current), []);

    // Pratinjau foto lokal dibuang saat diganti atau dialog ditutup agar memori tidak bocor.
    useEffect(
        () => () => {
            if (preview) {
                URL.revokeObjectURL(preview);
            }
        },
        [preview],
    );

    const visit = (
        changes: Partial<Filters> & { page?: number },
        searchValue: string = search,
    ): void => {
        const query = { ...filters, search: searchValue, ...changes };
        const params = Object.fromEntries(
            Object.entries(query).filter(
                ([key, value]) =>
                    key !== 'academic_year_id' &&
                    value !== '' &&
                    value !== null &&
                    value !== undefined,
            ),
        );

        router.get(StudentController.index.url(), params, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: [
                'students',
                'filters',
                'classroomOptions',
                'unitHasStudents',
            ],
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

    const resetPhoto = (): void => {
        setPreview(null);

        if (fileInput.current) {
            fileInput.current.value = '';
        }
    };

    const openCreate = (): void => {
        setEditing(null);
        resetPhoto();
        form.clearErrors();
        form.setData(emptyForm(filters.unit_id));
        setFormOpen(true);
    };

    const openEdit = (student: Student): void => {
        setEditing(student);
        resetPhoto();
        form.clearErrors();
        form.setData({
            unit_id: filters.unit_id,
            nis: student.nis,
            nisn: student.nisn ?? '',
            name: student.name,
            nickname: student.nickname ?? '',
            gender: student.gender.value,
            birth_place: student.birth_place ?? '',
            birth_date: student.birth_date ?? '',
            address: student.address ?? '',
            status: student.status.value,
            classroom_id: student.active_classroom_id,
            photo: null,
            remove_photo: false,
        });
        setFormOpen(true);
    };

    const setField = <K extends keyof StudentForm>(
        key: K,
        value: StudentForm[K],
    ): void => {
        form.setData((data) => ({ ...data, [key]: value }));
        form.clearErrors(key);
    };

    const choosePhoto = (event: ChangeEvent<HTMLInputElement>): void => {
        const file = event.target.files?.[0] ?? null;

        form.setData((data) => ({ ...data, photo: file, remove_photo: false }));
        form.clearErrors('photo');
        setPreview(file ? URL.createObjectURL(file) : null);
    };

    const removePhoto = (): void => {
        resetPhoto();
        form.setData((data) => ({ ...data, photo: null, remove_photo: true }));
    };

    const submit = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();

        const options = {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => {
                setFormOpen(false);
                resetPhoto();
            },
        };

        if (editing) {
            // Unggahan berkas butuh multipart; PUT dikirim lewat POST + _method.
            form.transform((data) => ({ ...data, _method: 'put' }));
            form.post(StudentController.update.url(editing.id), options);
        } else {
            form.transform((data) => data);
            form.post(StudentController.store.url(), options);
        }
    };

    const confirmStatus = (): void => {
        if (!statusTarget) {
            return;
        }

        router.patch(
            StudentController.updateStatus.url(statusTarget.id),
            {
                status:
                    statusTarget.status.value === 'active'
                        ? 'inactive'
                        : 'active',
            },
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
            Tambah siswa
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
                    'Siswa selalu milik satu unit. Aktifkan atau tambahkan unit di Pengaturan > Unit & jam presensi.',
            };
        }

        if (!ready) {
            return {
                title: 'Pilih unit',
                description: 'Pilih unit di atas untuk melihat siswanya.',
            };
        }

        if (unitHasStudents) {
            return {
                title: 'Tidak ada siswa yang cocok',
                description: `Tidak ada siswa ${unit.code} dengan saringan ini. Ubah kata kunci, rombel, atau status.`,
            };
        }

        return {
            title: `Belum ada siswa ${unit.code}`,
            description:
                enrollmentClassroomOptions.length > 0
                    ? 'Tambahkan siswa lalu pilih rombelnya di tahun ajaran aktif.'
                    : 'Tambahkan siswa. Rombel bisa dipilih setelah rombel tahun ajaran aktif dibuat.',
            action: (
                <div className="flex flex-wrap justify-center gap-2">
                    {enrollmentClassroomOptions.length === 0 && (
                        <Button asChild variant="outline">
                            <Link href={ClassroomController.index()}>
                                Buka rombel
                            </Link>
                        </Button>
                    )}
                    {addButton}
                </div>
            ),
        };
    };

    const columns: DataTableColumn<Student>[] = [
        {
            key: 'name',
            label: 'Siswa',
            render: (student) => (
                <div className="min-w-40">
                    <div className="font-medium text-foreground">
                        {student.name}
                    </div>
                    <div className="text-xs text-muted-foreground tabular-nums">
                        {student.gender.value}
                        {student.birth_date
                            ? ` · ${formatDate(student.birth_date)}`
                            : ''}
                    </div>
                </div>
            ),
        },
        {
            key: 'nis',
            label: 'NIS',
            render: (student) => (
                <span className="tabular-nums">{student.nis}</span>
            ),
        },
        {
            key: 'classroom',
            label: 'Rombel',
            render: (student) =>
                student.classroom?.name ?? (
                    <span className="text-muted-foreground">
                        Belum ada rombel
                    </span>
                ),
        },
        {
            key: 'guardian',
            label: 'Wali',
            render: () => (
                <span className="text-muted-foreground">Belum ada wali</span>
            ),
        },
        {
            key: 'status',
            label: 'Status',
            align: 'center',
            render: (student) => (
                <StatusBadge variant={statusVariant[student.status.value]}>
                    {student.status.label}
                </StatusBadge>
            ),
        },
        {
            key: 'actions',
            label: 'Aksi',
            align: 'center',
            render: (student) => {
                const active = student.status.value === 'active';

                return (
                    <RowActions
                        itemName={student.name}
                        onView={() => setViewing(student)}
                        onEdit={() => openEdit(student)}
                    >
                        <Tooltip>
                            <TooltipTrigger asChild>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    aria-label={`${active ? 'Nonaktifkan' : 'Aktifkan'} ${student.name}`}
                                    onClick={() => setStatusTarget(student)}
                                    className={
                                        active
                                            ? 'text-destructive hover:bg-destructive/10 hover:text-destructive'
                                            : undefined
                                    }
                                >
                                    {active ? (
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
                                {active ? 'Nonaktifkan' : 'Aktifkan'}
                            </TooltipContent>
                        </Tooltip>
                    </RowActions>
                );
            },
        },
    ];

    const currentPhoto =
        preview ??
        (form.data.remove_photo ? null : (editing?.photo_url ?? null));

    return (
        <StaffLayout
            breadcrumbs={[
                { title: 'Data Induk', href: StudentController.index() },
                { title: 'Siswa', href: StudentController.index() },
            ]}
        >
            <Head title="Siswa" />

            <div className="space-y-4">
                <PageHeader
                    title="Siswa"
                    description={
                        academicYearLabel
                            ? `Rombel ditampilkan untuk ${academicYearLabel}. Pindah rombel tercatat sebagai riwayat.`
                            : 'Pindah rombel tercatat sebagai riwayat.'
                    }
                    actions={addButton}
                />

                <div className="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center">
                    <Input
                        type="search"
                        value={search}
                        onChange={(event) => changeSearch(event.target.value)}
                        placeholder="Cari nama atau NIS"
                        aria-label="Cari siswa"
                        className="w-full sm:max-w-xs"
                    />
                    <Select
                        value={
                            filters.unit_id === null
                                ? undefined
                                : String(filters.unit_id)
                        }
                        onValueChange={(value) =>
                            visit({
                                unit_id: Number(value),
                                classroom_id: null,
                                page: 1,
                            })
                        }
                        disabled={unitOptions.length === 0}
                    >
                        <SelectTrigger
                            className="w-full sm:w-60"
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
                        value={filters.classroom_id ?? ALL_CLASSROOMS}
                        onValueChange={(value) =>
                            visit({
                                classroom_id:
                                    value === ALL_CLASSROOMS ? null : value,
                                page: 1,
                            })
                        }
                        disabled={!ready}
                    >
                        <SelectTrigger
                            className="w-full sm:w-44"
                            aria-label="Saring rombel"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL_CLASSROOMS}>
                                Semua rombel
                            </SelectItem>
                            <SelectItem value={WITHOUT_CLASSROOM}>
                                Belum ada rombel
                            </SelectItem>
                            {classroomOptions.map((option) => (
                                <SelectItem
                                    key={option.id}
                                    value={String(option.id)}
                                >
                                    {option.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <Select
                        value={filters.status}
                        onValueChange={(value) =>
                            visit({ status: value, page: 1 })
                        }
                        disabled={!ready}
                    >
                        <SelectTrigger
                            className="w-full sm:w-40"
                            aria-label="Saring status"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL}>Semua status</SelectItem>
                            {statusOptions.map((option) => (
                                <SelectItem
                                    key={option.value}
                                    value={option.value}
                                >
                                    {option.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                {loadFailed ? (
                    <ErrorState
                        description="Daftar siswa tidak dapat dimuat. Periksa koneksi lalu coba lagi."
                        onRetry={() => visit({})}
                    />
                ) : (
                    <DataTable
                        columns={columns}
                        data={students.data}
                        rowKey={(student) => student.id}
                        loading={loading}
                        pagination={{
                            currentPage: students.meta.current_page,
                            lastPage: students.meta.last_page,
                            perPage: students.meta.per_page,
                            total: students.meta.total,
                            from: students.meta.from,
                            to: students.meta.to,
                        }}
                        onPageChange={(page) => visit({ page })}
                        empty={emptyState()}
                    />
                )}
            </div>

            <FormDialog
                open={formOpen}
                onOpenChange={setFormOpen}
                title={editing ? 'Ubah siswa' : 'Tambah siswa'}
                description={
                    activeAcademicYear
                        ? `${unit?.code ?? ''} · rombel ${activeAcademicYear.label}. Pindah rombel tercatat sebagai riwayat.`
                        : `${unit?.code ?? ''} · belum ada tahun ajaran aktif, rombel belum bisa dipilih.`
                }
                onSubmit={submit}
                processing={form.processing}
                contentClassName="max-h-[calc(100dvh-2rem)] overflow-y-auto sm:max-w-xl"
            >
                <InputError message={form.errors.unit_id} />
                <div className="grid gap-2">
                    <Label htmlFor="student-name">Nama lengkap</Label>
                    <Input
                        id="student-name"
                        value={form.data.name}
                        onChange={(event) =>
                            setField('name', event.target.value)
                        }
                        maxLength={100}
                        autoComplete="off"
                        required
                        aria-invalid={Boolean(form.errors.name)}
                    />
                    <InputError message={form.errors.name} />
                </div>
                <div className="grid items-start gap-4 sm:grid-cols-2">
                    <div className="grid gap-2">
                        <Label htmlFor="student-nis">NIS</Label>
                        <Input
                            id="student-nis"
                            value={form.data.nis}
                            onChange={(event) =>
                                setField('nis', event.target.value)
                            }
                            maxLength={20}
                            autoComplete="off"
                            required
                            aria-invalid={Boolean(form.errors.nis)}
                        />
                        <InputError message={form.errors.nis} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="student-gender">Jenis kelamin</Label>
                        <Select
                            value={form.data.gender || undefined}
                            onValueChange={(value) =>
                                setField('gender', value as Gender)
                            }
                        >
                            <SelectTrigger
                                id="student-gender"
                                className="w-full"
                                aria-invalid={Boolean(form.errors.gender)}
                            >
                                <SelectValue placeholder="Pilih" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="L">Laki-laki</SelectItem>
                                <SelectItem value="P">Perempuan</SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError message={form.errors.gender} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="student-classroom">Rombel</Label>
                        <Select
                            value={
                                form.data.classroom_id === null
                                    ? NO_CLASSROOM
                                    : String(form.data.classroom_id)
                            }
                            onValueChange={(value) =>
                                form.setData(
                                    'classroom_id',
                                    value === NO_CLASSROOM
                                        ? null
                                        : Number(value),
                                )
                            }
                            disabled={activeAcademicYear === null}
                        >
                            <SelectTrigger
                                id="student-classroom"
                                className="w-full"
                                aria-invalid={Boolean(form.errors.classroom_id)}
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={NO_CLASSROOM}>
                                    Belum ditentukan
                                </SelectItem>
                                {enrollmentClassroomOptions.map((option) => (
                                    <SelectItem
                                        key={option.id}
                                        value={String(option.id)}
                                    >
                                        {option.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={form.errors.classroom_id} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="student-status">Status</Label>
                        <Select
                            value={form.data.status}
                            onValueChange={(value) =>
                                setField('status', value as StudentStatus)
                            }
                        >
                            <SelectTrigger
                                id="student-status"
                                className="w-full"
                                aria-invalid={Boolean(form.errors.status)}
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {statusOptions.map((option) => (
                                    <SelectItem
                                        key={option.value}
                                        value={option.value}
                                    >
                                        {option.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={form.errors.status} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="student-nisn">NISN</Label>
                        <Input
                            id="student-nisn"
                            value={form.data.nisn}
                            onChange={(event) =>
                                setField('nisn', event.target.value)
                            }
                            inputMode="numeric"
                            maxLength={10}
                            autoComplete="off"
                            aria-invalid={Boolean(form.errors.nisn)}
                        />
                        <InputError message={form.errors.nisn} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="student-nickname">Nama panggilan</Label>
                        <Input
                            id="student-nickname"
                            value={form.data.nickname}
                            onChange={(event) =>
                                setField('nickname', event.target.value)
                            }
                            maxLength={30}
                            autoComplete="off"
                            aria-invalid={Boolean(form.errors.nickname)}
                        />
                        <InputError message={form.errors.nickname} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="student-birth-place">
                            Tempat lahir
                        </Label>
                        <Input
                            id="student-birth-place"
                            value={form.data.birth_place}
                            onChange={(event) =>
                                setField('birth_place', event.target.value)
                            }
                            maxLength={60}
                            autoComplete="off"
                            aria-invalid={Boolean(form.errors.birth_place)}
                        />
                        <InputError message={form.errors.birth_place} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="student-birth-date">
                            Tanggal lahir
                        </Label>
                        <Input
                            id="student-birth-date"
                            type="date"
                            value={form.data.birth_date}
                            onChange={(event) =>
                                setField('birth_date', event.target.value)
                            }
                            aria-invalid={Boolean(form.errors.birth_date)}
                        />
                        <InputError message={form.errors.birth_date} />
                    </div>
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="student-address">Alamat</Label>
                    <Input
                        id="student-address"
                        value={form.data.address}
                        onChange={(event) =>
                            setField('address', event.target.value)
                        }
                        maxLength={500}
                        autoComplete="off"
                        aria-invalid={Boolean(form.errors.address)}
                    />
                    <InputError message={form.errors.address} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="student-photo">Foto</Label>
                    <div className="flex items-center gap-3">
                        <StudentPhoto
                            url={currentPhoto}
                            name={form.data.name || 'siswa'}
                            className="size-16 shrink-0"
                        />
                        <div className="flex flex-wrap items-center gap-2">
                            <input
                                ref={fileInput}
                                id="student-photo"
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                                tabIndex={-1}
                                className="sr-only"
                                onChange={choosePhoto}
                            />
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={() => fileInput.current?.click()}
                            >
                                <ImageUp
                                    className="size-4"
                                    strokeWidth={1.75}
                                />
                                {currentPhoto ? 'Ganti foto' : 'Pilih foto'}
                            </Button>
                            {currentPhoto && (
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    onClick={removePhoto}
                                    className="text-destructive hover:bg-destructive/10 hover:text-destructive"
                                >
                                    <Trash2
                                        className="size-4"
                                        strokeWidth={1.75}
                                    />
                                    Hapus foto
                                </Button>
                            )}
                            <span className="text-xs text-muted-foreground">
                                JPG/PNG/WebP maks. 2 MB, dikompres ≤ 300 KB
                            </span>
                        </div>
                    </div>
                    <InputError message={form.errors.photo} />
                </div>
            </FormDialog>

            <Dialog
                open={viewing !== null}
                onOpenChange={(open) => !open && setViewing(null)}
            >
                <DialogContent className="max-h-[calc(100dvh-2rem)] overflow-y-auto sm:max-w-lg">
                    {viewing && (
                        <>
                            <DialogHeader>
                                <DialogTitle>{viewing.name}</DialogTitle>
                                <DialogDescription>
                                    NIS {viewing.nis} · {viewing.status.label}
                                </DialogDescription>
                            </DialogHeader>
                            <div className="flex flex-col gap-4 sm:flex-row">
                                <StudentPhoto
                                    url={viewing.photo_url}
                                    name={viewing.name}
                                    className="size-28 shrink-0"
                                />
                                <dl className="grid flex-1 grid-cols-[auto_1fr] gap-x-4 gap-y-1.5 text-sm">
                                    <dt className="text-muted-foreground">
                                        Panggilan
                                    </dt>
                                    <dd>{viewing.nickname ?? '—'}</dd>
                                    <dt className="text-muted-foreground">
                                        NISN
                                    </dt>
                                    <dd className="tabular-nums">
                                        {viewing.nisn ?? '—'}
                                    </dd>
                                    <dt className="text-muted-foreground">
                                        Jenis kelamin
                                    </dt>
                                    <dd>{viewing.gender.label}</dd>
                                    <dt className="text-muted-foreground">
                                        Lahir
                                    </dt>
                                    <dd>
                                        {[
                                            viewing.birth_place,
                                            viewing.birth_date
                                                ? formatDate(viewing.birth_date)
                                                : null,
                                        ]
                                            .filter(Boolean)
                                            .join(', ') || '—'}
                                    </dd>
                                    <dt className="text-muted-foreground">
                                        Alamat
                                    </dt>
                                    <dd className="break-words">
                                        {viewing.address ?? '—'}
                                    </dd>
                                </dl>
                            </div>
                            <div className="space-y-2">
                                <h3 className="text-sm font-medium">
                                    Riwayat rombel
                                </h3>
                                {viewing.enrollments.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        Belum pernah terdaftar di rombel.
                                    </p>
                                ) : (
                                    <ul className="divide-y divide-border rounded-md border border-border text-sm">
                                        {viewing.enrollments.map(
                                            (enrollment) => (
                                                <li
                                                    key={enrollment.id}
                                                    className="flex justify-between gap-4 px-3 py-2"
                                                >
                                                    <span className="text-muted-foreground">
                                                        {
                                                            enrollment.academic_year
                                                        }
                                                    </span>
                                                    <span>
                                                        {enrollment.classroom}
                                                    </span>
                                                </li>
                                            ),
                                        )}
                                    </ul>
                                )}
                            </div>
                        </>
                    )}
                </DialogContent>
            </Dialog>

            <ConfirmDialog
                open={statusTarget !== null}
                onOpenChange={(open) => !open && setStatusTarget(null)}
                title={
                    statusTarget?.status.value === 'active'
                        ? `Nonaktifkan ${statusTarget.name}?`
                        : `Aktifkan ${statusTarget?.name ?? ''}?`
                }
                description={
                    statusTarget?.status.value === 'active'
                        ? 'Siswa nonaktif tidak muncul di daftar bawaan. Data dan riwayatnya tetap tersimpan dan bisa diaktifkan kembali.'
                        : `Status siswa berubah dari ${statusTarget?.status.label ?? ''} menjadi Aktif.`
                }
                confirmLabel={
                    statusTarget?.status.value === 'active'
                        ? 'Nonaktifkan'
                        : 'Aktifkan'
                }
                variant={
                    statusTarget?.status.value === 'active'
                        ? 'destructive'
                        : 'default'
                }
                loading={acting}
                onConfirm={confirmStatus}
            />
        </StaffLayout>
    );
}
