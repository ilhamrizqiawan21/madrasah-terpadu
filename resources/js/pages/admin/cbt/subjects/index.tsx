import { Head, router, useForm } from '@inertiajs/react';
import { Plus, Power, PowerOff } from 'lucide-react';
import type { FormEvent } from 'react';
import { useEffect, useRef, useState } from 'react';
import AdminDashboardController from '@/actions/App/Http/Controllers/Admin/DashboardController';
import CbtSubjectController from '@/actions/App/Http/Controllers/Admin/CbtSubjectController';
import CbtTeachingAssignmentController from '@/actions/App/Http/Controllers/Admin/CbtTeachingAssignmentController';
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

type SubjectRow = {
    id: number;
    code: string;
    name: string;
    is_active: boolean;
    assignments_count?: number;
    unit?: { id: number; code: string; name: string };
};

type AssignmentRow = {
    id: number;
    teacher?: { id: number; name: string };
    subject?: { id: number; code: string; name: string };
    classroom?: { id: number; name: string; grade: string };
    academic_year?: { id: number; label: string };
};

type UnitOption = { id: number; code: string; name: string };
type YearOption = { id: number; label: string; is_active: boolean };
type SubjectOption = { id: number; code: string; name: string };
type ClassroomOption = { id: number; name: string; grade: string };
type TeacherOption = { id: number; name: string };

type Filters = {
    unit_id: number | null;
    academic_year_id: number | null;
    search: string;
};

type Props = {
    canManage: boolean;
    subjects: PaginatedResource<SubjectRow>;
    assignments: PaginatedResource<AssignmentRow>;
    filters: Filters;
    unitOptions: UnitOption[];
    academicYearOptions: YearOption[];
    subjectOptions: SubjectOption[];
    classroomOptions: ClassroomOption[];
    teacherOptions: TeacherOption[];
};

type SubjectForm = {
    unit_id: number | null;
    code: string;
    name: string;
};

type AssignmentForm = {
    user_id: string;
    subject_id: string;
    classroom_id: string;
    academic_year_id: number | null;
};

export default function CbtSubjectsIndex({
    canManage,
    subjects,
    assignments,
    filters,
    unitOptions,
    academicYearOptions,
    subjectOptions,
    classroomOptions,
    teacherOptions,
}: Props) {
    const [search, setSearch] = useState(filters.search);
    const [loading, setLoading] = useState(false);
    const [loadFailed, setLoadFailed] = useState(false);

    const [subjectFormOpen, setSubjectFormOpen] = useState(false);
    const [editingSubject, setEditingSubject] = useState<SubjectRow | null>(
        null,
    );
    const [statusTarget, setStatusTarget] = useState<SubjectRow | null>(null);
    const [actingStatus, setActingStatus] = useState(false);

    const [assignmentFormOpen, setAssignmentFormOpen] = useState(false);
    const [deletingAssignmentId, setDeletingAssignmentId] = useState<
        number | null
    >(null);

    const searchTimer = useRef<number | undefined>(undefined);

    const unit = unitOptions.find((option) => option.id === filters.unit_id);
    const year = academicYearOptions.find(
        (option) => option.id === filters.academic_year_id,
    );
    const unitReady = unit !== undefined;
    const assignmentScopeReady = unit !== undefined && year !== undefined;
    const scopeLabel = assignmentScopeReady
        ? `${unit.code} · ${year.label}`
        : unitReady
          ? unit.code
          : '';

    const subjectForm = useForm<SubjectForm>({
        unit_id: null,
        code: '',
        name: '',
    });

    const assignmentForm = useForm<AssignmentForm>({
        user_id: '',
        subject_id: '',
        classroom_id: '',
        academic_year_id: null,
    });

    useEffect(() => () => window.clearTimeout(searchTimer.current), []);

    const visit = (
        changes: Partial<Filters> & {
            subjects_page?: number;
            assignments_page?: number;
        },
        searchValue: string = search,
    ): void => {
        const query = { ...filters, search: searchValue, ...changes };
        const params = Object.fromEntries(
            Object.entries(query).filter(
                ([, value]) =>
                    value !== '' && value !== null && value !== undefined,
            ),
        );

        router.get(CbtSubjectController.index.url(), params, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
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
            () => visit({ subjects_page: 1, assignments_page: 1 }, value),
            300,
        );
    };

    const openCreateSubject = (): void => {
        setEditingSubject(null);
        subjectForm.clearErrors();
        subjectForm.setData({
            unit_id: filters.unit_id,
            code: '',
            name: '',
        });
        setSubjectFormOpen(true);
    };

    const openEditSubject = (subject: SubjectRow): void => {
        setEditingSubject(subject);
        subjectForm.clearErrors();
        subjectForm.setData({
            unit_id: subject.unit?.id ?? filters.unit_id,
            code: subject.code,
            name: subject.name,
        });
        setSubjectFormOpen(true);
    };

    const submitSubject = (event: FormEvent): void => {
        event.preventDefault();

        if (editingSubject === null) {
            subjectForm.post(CbtSubjectController.store.url(), {
                preserveScroll: true,
                onSuccess: () => {
                    setSubjectFormOpen(false);
                    subjectForm.reset();
                },
            });

            return;
        }

        subjectForm.transform((data) => ({
            code: data.code,
            name: data.name,
        }));
        subjectForm.put(CbtSubjectController.update.url(editingSubject.id), {
            preserveScroll: true,
            onSuccess: () => {
                setSubjectFormOpen(false);
                setEditingSubject(null);
            },
        });
    };

    const toggleSubjectStatus = (): void => {
        if (statusTarget === null) {
            return;
        }

        setActingStatus(true);
        router.patch(
            CbtSubjectController.updateStatus.url(statusTarget.id),
            { is_active: !statusTarget.is_active },
            {
                preserveScroll: true,
                onFinish: () => setActingStatus(false),
                onSuccess: () => setStatusTarget(null),
            },
        );
    };

    const openCreateAssignment = (): void => {
        assignmentForm.clearErrors();
        assignmentForm.setData({
            user_id: '',
            subject_id: '',
            classroom_id: '',
            academic_year_id: filters.academic_year_id,
        });
        setAssignmentFormOpen(true);
    };

    const submitAssignment = (event: FormEvent): void => {
        event.preventDefault();

        assignmentForm.transform((data) => ({
            user_id: Number(data.user_id),
            subject_id: Number(data.subject_id),
            classroom_id: Number(data.classroom_id),
            academic_year_id: data.academic_year_id,
        }));
        assignmentForm.post(CbtTeachingAssignmentController.store.url(), {
            preserveScroll: true,
            onSuccess: () => {
                setAssignmentFormOpen(false);
                assignmentForm.reset();
            },
        });
    };

    const deleteAssignment = (assignment: AssignmentRow): void => {
        setDeletingAssignmentId(assignment.id);
        router.delete(
            CbtTeachingAssignmentController.destroy.url(assignment.id),
            {
                preserveScroll: true,
                onFinish: () => setDeletingAssignmentId(null),
            },
        );
    };

    const subjectColumns: DataTableColumn<SubjectRow>[] = [
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
            label: 'Mata pelajaran',
            render: (row) => <span className="font-medium">{row.name}</span>,
        },
        {
            key: 'assignments_count',
            label: 'Penugasan',
            align: 'right',
            render: (row) => (
                <span className="tabular-nums">
                    {row.assignments_count ?? 0}
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
        ...(canManage
            ? [
                  {
                      key: 'actions',
                      label: 'Aksi',
                      align: 'center' as const,
                      render: (row: SubjectRow) => (
                          <RowActions
                              itemName={row.name}
                              onEdit={() => openEditSubject(row)}
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
                                          onClick={() => setStatusTarget(row)}
                                      >
                                          {row.is_active ? (
                                              <PowerOff
                                                  className="size-4 text-destructive"
                                                  strokeWidth={1.75}
                                              />
                                          ) : (
                                              <Power
                                                  className="size-4 text-success"
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

    const assignmentColumns: DataTableColumn<AssignmentRow>[] = [
        {
            key: 'teacher',
            label: 'Guru pengampu',
            render: (row) => (
                <span className="font-medium">{row.teacher?.name ?? '—'}</span>
            ),
        },
        {
            key: 'subject',
            label: 'Mata pelajaran',
            render: (row) =>
                row.subject ? (
                    <span>
                        <span className="font-mono text-xs text-muted-foreground">
                            {row.subject.code}
                        </span>{' '}
                        · {row.subject.name}
                    </span>
                ) : (
                    '—'
                ),
        },
        {
            key: 'classroom',
            label: 'Rombel',
            render: (row) =>
                row.classroom
                    ? `${row.classroom.name} (Tingkat ${row.classroom.grade})`
                    : '—',
        },
        {
            key: 'academic_year',
            label: 'Tahun ajaran',
            render: (row) => row.academic_year?.label ?? '—',
        },
        ...(canManage
            ? [
                  {
                      key: 'actions',
                      label: 'Aksi',
                      align: 'center' as const,
                      render: (row: AssignmentRow) => (
                          <RowActions
                              itemName={`${row.teacher?.name ?? 'Guru'} — ${row.subject?.name ?? 'Mapel'} (${row.classroom?.name ?? 'Rombel'})`}
                              deleteTitle="Hapus penugasan guru?"
                              deleteDescription={`Penugasan ${row.teacher?.name ?? 'guru'} untuk ${row.subject?.name ?? 'mapel'} di rombel ${row.classroom?.name ?? ''} akan dihapus.`}
                              deleting={deletingAssignmentId === row.id}
                              onDelete={() => deleteAssignment(row)}
                          />
                      ),
                  },
              ]
            : []),
    ];

    return (
        <StaffLayout
            breadcrumbs={[
                { title: 'Beranda', href: AdminDashboardController.url() },
                { title: 'Akademik', href: CbtSubjectController.index.url() },
                {
                    title: 'Mapel & penugasan',
                    href: CbtSubjectController.index.url(),
                },
            ]}
        >
            <Head title="Mapel & penugasan" />

            <div className="space-y-6">
                <PageHeader
                    title="Mapel & penugasan"
                    description={
                        scopeLabel
                            ? `Daftar mata pelajaran dan penugasan guru untuk ${scopeLabel}.`
                            : 'Kelola mata pelajaran per unit dan penugasan guru per rombel.'
                    }
                    actions={
                        canManage ? (
                            <div className="flex flex-wrap items-center gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    disabled={!unitReady}
                                    onClick={openCreateSubject}
                                >
                                    <Plus
                                        className="size-4"
                                        strokeWidth={1.75}
                                    />
                                    Tambah mapel
                                </Button>
                                <Button
                                    type="button"
                                    disabled={!assignmentScopeReady}
                                    onClick={openCreateAssignment}
                                >
                                    <Plus
                                        className="size-4"
                                        strokeWidth={1.75}
                                    />
                                    Tambah penugasan
                                </Button>
                            </div>
                        ) : undefined
                    }
                />

                <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <Select
                        value={
                            filters.unit_id !== null
                                ? String(filters.unit_id)
                                : ''
                        }
                        onValueChange={(value) =>
                            visit({
                                unit_id: Number(value),
                                subjects_page: 1,
                                assignments_page: 1,
                            })
                        }
                    >
                        <SelectTrigger
                            className="w-full sm:w-44"
                            aria-label="Pilih unit"
                        >
                            <SelectValue placeholder="Pilih unit" />
                        </SelectTrigger>
                        <SelectContent>
                            {unitOptions.map((option) => (
                                <SelectItem
                                    key={option.id}
                                    value={String(option.id)}
                                >
                                    {option.code} — {option.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    <Select
                        value={
                            filters.academic_year_id !== null
                                ? String(filters.academic_year_id)
                                : ''
                        }
                        onValueChange={(value) =>
                            visit({
                                academic_year_id: Number(value),
                                subjects_page: 1,
                                assignments_page: 1,
                            })
                        }
                    >
                        <SelectTrigger
                            className="w-full sm:w-52"
                            aria-label="Pilih tahun ajaran"
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
                                    {option.is_active ? ' (Aktif)' : ''}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    <Input
                        type="search"
                        value={search}
                        onChange={(event) => changeSearch(event.target.value)}
                        placeholder="Cari kode, mapel, guru, atau rombel..."
                        aria-label="Cari kode, mapel, guru, atau rombel"
                        className="w-full sm:max-w-xs"
                    />
                </div>

                {loadFailed ? (
                    <ErrorState
                        title="Gagal memuat data mapel dan penugasan"
                        description="Periksa koneksi Anda lalu coba muat ulang."
                        onRetry={() => visit({})}
                    />
                ) : (
                    <div className="space-y-8">
                        <section
                            className="space-y-3"
                            aria-labelledby="subjects-heading"
                        >
                            <div className="flex items-center justify-between gap-2">
                                <h2
                                    id="subjects-heading"
                                    className="text-base font-semibold tracking-tight"
                                >
                                    Mata pelajaran
                                </h2>
                            </div>

                            <DataTable
                                columns={subjectColumns}
                                data={subjects.data}
                                rowKey={(row) => row.id}
                                loading={loading}
                                empty={{
                                    title: !unitReady
                                        ? 'Unit belum tersedia'
                                        : filters.search !== ''
                                          ? 'Mata pelajaran tidak ditemukan'
                                          : 'Belum ada mata pelajaran',
                                    description: !unitReady
                                        ? 'Aktifkan unit sekolah di Pengaturan untuk mulai menambahkan mata pelajaran.'
                                        : filters.search !== ''
                                          ? `Tidak ada mata pelajaran yang cocok dengan "${filters.search}".`
                                          : canManage
                                            ? `Tambahkan mata pelajaran untuk unit ${unit?.code ?? ''} sebelum menugaskan guru.`
                                            : 'Belum ada mata pelajaran yang ditugaskan kepada Anda pada unit ini.',
                                    action:
                                        canManage &&
                                        unitReady &&
                                        filters.search === '' ? (
                                            <Button
                                                type="button"
                                                onClick={openCreateSubject}
                                            >
                                                <Plus
                                                    className="size-4"
                                                    strokeWidth={1.75}
                                                />
                                                Tambah mapel
                                            </Button>
                                        ) : undefined,
                                }}
                                pagination={{
                                    currentPage: subjects.meta.current_page,
                                    lastPage: subjects.meta.last_page,
                                    perPage: subjects.meta.per_page,
                                    total: subjects.meta.total,
                                    from: subjects.meta.from,
                                    to: subjects.meta.to,
                                }}
                                onPageChange={(page) =>
                                    visit({ subjects_page: page })
                                }
                            />
                        </section>

                        <section
                            className="space-y-3"
                            aria-labelledby="assignments-heading"
                        >
                            <div className="flex items-center justify-between gap-2">
                                <h2
                                    id="assignments-heading"
                                    className="text-base font-semibold tracking-tight"
                                >
                                    Penugasan guru
                                </h2>
                            </div>

                            <DataTable
                                columns={assignmentColumns}
                                data={assignments.data}
                                rowKey={(row) => row.id}
                                loading={loading}
                                empty={{
                                    title: !assignmentScopeReady
                                        ? 'Unit atau tahun ajaran belum tersedia'
                                        : filters.search !== ''
                                          ? 'Penugasan guru tidak ditemukan'
                                          : 'Belum ada penugasan guru',
                                    description: !assignmentScopeReady
                                        ? 'Pilih unit dan tahun ajaran terlebih dahulu.'
                                        : filters.search !== ''
                                          ? `Tidak ada penugasan yang cocok dengan "${filters.search}".`
                                          : canManage
                                            ? `Tugaskan guru ke mata pelajaran dan rombel pada ${scopeLabel}.`
                                            : 'Anda belum memiliki penugasan mengajar pada unit dan tahun ajaran ini.',
                                    action:
                                        canManage &&
                                        assignmentScopeReady &&
                                        filters.search === '' ? (
                                            <Button
                                                type="button"
                                                onClick={openCreateAssignment}
                                            >
                                                <Plus
                                                    className="size-4"
                                                    strokeWidth={1.75}
                                                />
                                                Tambah penugasan
                                            </Button>
                                        ) : undefined,
                                }}
                                pagination={{
                                    currentPage: assignments.meta.current_page,
                                    lastPage: assignments.meta.last_page,
                                    perPage: assignments.meta.per_page,
                                    total: assignments.meta.total,
                                    from: assignments.meta.from,
                                    to: assignments.meta.to,
                                }}
                                onPageChange={(page) =>
                                    visit({ assignments_page: page })
                                }
                            />
                        </section>
                    </div>
                )}
            </div>

            <FormDialog
                open={subjectFormOpen}
                onOpenChange={(open) => {
                    setSubjectFormOpen(open);
                    if (!open) {
                        setEditingSubject(null);
                    }
                }}
                title={
                    editingSubject === null
                        ? 'Tambah mata pelajaran'
                        : 'Ubah mata pelajaran'
                }
                description={
                    unit
                        ? `Mata pelajaran untuk unit ${unit.code} — ${unit.name}.`
                        : undefined
                }
                processing={subjectForm.processing}
                submitLabel={
                    editingSubject === null
                        ? 'Simpan mapel'
                        : 'Simpan perubahan'
                }
                onSubmit={submitSubject}
            >
                <div className="space-y-4">
                    <div className="space-y-1.5">
                        <Label htmlFor="subject-code">Kode mapel</Label>
                        <Input
                            id="subject-code"
                            value={subjectForm.data.code}
                            onChange={(event) =>
                                subjectForm.setData('code', event.target.value)
                            }
                            placeholder="MTK"
                            maxLength={20}
                            autoFocus
                        />
                        <InputError message={subjectForm.errors.code} />
                    </div>

                    <div className="space-y-1.5">
                        <Label htmlFor="subject-name">
                            Nama mata pelajaran
                        </Label>
                        <Input
                            id="subject-name"
                            value={subjectForm.data.name}
                            onChange={(event) =>
                                subjectForm.setData('name', event.target.value)
                            }
                            placeholder="Matematika"
                            maxLength={100}
                        />
                        <InputError message={subjectForm.errors.name} />
                    </div>
                </div>
            </FormDialog>

            <ConfirmDialog
                open={statusTarget !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setStatusTarget(null);
                    }
                }}
                title={
                    statusTarget?.is_active
                        ? 'Nonaktifkan mata pelajaran?'
                        : 'Aktifkan kembali mata pelajaran?'
                }
                description={
                    statusTarget?.is_active
                        ? `Mata pelajaran "${statusTarget.name}" tidak akan muncul di pilihan penugasan dan ujian baru, tetapi riwayatnya tetap tersimpan.`
                        : `Mata pelajaran "${statusTarget?.name ?? ''}" akan kembali aktif untuk penugasan dan ujian.`
                }
                confirmLabel={
                    statusTarget?.is_active ? 'Nonaktifkan' : 'Aktifkan'
                }
                variant={statusTarget?.is_active ? 'destructive' : 'default'}
                loading={actingStatus}
                onConfirm={toggleSubjectStatus}
            />

            <FormDialog
                open={assignmentFormOpen}
                onOpenChange={setAssignmentFormOpen}
                title="Tambah penugasan guru"
                description={
                    scopeLabel
                        ? `Tentukan guru pengampu, mata pelajaran, dan rombel pada ${scopeLabel}.`
                        : undefined
                }
                processing={assignmentForm.processing}
                submitLabel="Simpan penugasan"
                onSubmit={submitAssignment}
            >
                <div className="space-y-4">
                    <div className="space-y-1.5">
                        <Label htmlFor="assignment-teacher">
                            Guru pengampu
                        </Label>
                        <Select
                            value={assignmentForm.data.user_id}
                            onValueChange={(value) =>
                                assignmentForm.setData('user_id', value)
                            }
                        >
                            <SelectTrigger id="assignment-teacher">
                                <SelectValue placeholder="Pilih guru aktif" />
                            </SelectTrigger>
                            <SelectContent>
                                {teacherOptions.map((option) => (
                                    <SelectItem
                                        key={option.id}
                                        value={String(option.id)}
                                    >
                                        {option.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={assignmentForm.errors.user_id} />
                    </div>

                    <div className="space-y-1.5">
                        <Label htmlFor="assignment-subject">
                            Mata pelajaran
                        </Label>
                        <Select
                            value={assignmentForm.data.subject_id}
                            onValueChange={(value) =>
                                assignmentForm.setData('subject_id', value)
                            }
                        >
                            <SelectTrigger id="assignment-subject">
                                <SelectValue placeholder="Pilih mata pelajaran aktif" />
                            </SelectTrigger>
                            <SelectContent>
                                {subjectOptions.map((option) => (
                                    <SelectItem
                                        key={option.id}
                                        value={String(option.id)}
                                    >
                                        {option.code} — {option.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError
                            message={assignmentForm.errors.subject_id}
                        />
                    </div>

                    <div className="space-y-1.5">
                        <Label htmlFor="assignment-classroom">Rombel</Label>
                        <Select
                            value={assignmentForm.data.classroom_id}
                            onValueChange={(value) =>
                                assignmentForm.setData('classroom_id', value)
                            }
                        >
                            <SelectTrigger id="assignment-classroom">
                                <SelectValue placeholder="Pilih rombel aktif" />
                            </SelectTrigger>
                            <SelectContent>
                                {classroomOptions.map((option) => (
                                    <SelectItem
                                        key={option.id}
                                        value={String(option.id)}
                                    >
                                        {option.name} (Tingkat {option.grade})
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError
                            message={assignmentForm.errors.classroom_id}
                        />
                    </div>
                </div>
            </FormDialog>
        </StaffLayout>
    );
}
