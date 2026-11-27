import { Head, Link, router, useForm } from '@inertiajs/react';
import { CalendarCheck, FileCheck2, Monitor, Plus } from 'lucide-react';
import type { FormEvent } from 'react';
import { useEffect, useRef, useState } from 'react';
import CbtEssayScoreController from '@/actions/App/Http/Controllers/Admin/CbtEssayScoreController';
import CbtExamController from '@/actions/App/Http/Controllers/Admin/CbtExamController';
import CbtMonitorController from '@/actions/App/Http/Controllers/Admin/CbtMonitorController';
import CbtQuestionController from '@/actions/App/Http/Controllers/Admin/CbtQuestionController';
import AdminDashboardController from '@/actions/App/Http/Controllers/Admin/DashboardController';
import type { DataTableColumn } from '@/components/app/data-table';
import { DataTable } from '@/components/app/data-table';
import { DateText } from '@/components/app/date-text';
import { ErrorState } from '@/components/app/error-state';
import { FormDialog } from '@/components/app/form-dialog';
import { PageHeader } from '@/components/app/page-header';
import { RowActions } from '@/components/app/row-actions';
import type { StatusBadgeVariant } from '@/components/app/status-badge';
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

type ExamRow = {
    id: number;
    subject_id: number;
    title: string;
    grade: string | null;
    classroom_ids: number[];
    opens_at: string;
    closes_at: string;
    duration_minutes: number;
    shuffle_questions: boolean;
    shuffle_options: boolean;
    weights: { mcq: number; matching: number; essay_paper: number };
    access_token: string | null;
    violation_limit: number;
    device_modes: string[];
    show_result_to_guardian: boolean;
    status: 'draft' | 'scheduled' | 'running' | 'finished';
    status_label: string;
    subject?: { id: number; code: string; name: string };
};

type UnitOption = { id: number; code: string; name: string };
type SubjectOption = { id: number; code: string; name: string };
type ClassroomOption = { id: number; name: string; grade: string };
type DeviceModeOption = { value: string; label: string };

type Props = {
    canCreate: boolean;
    exams: PaginatedResource<ExamRow>;
    filters: { unit_id: number | null; search: string };
    unitOptions: UnitOption[];
    subjectOptions: SubjectOption[];
    classroomOptions: ClassroomOption[];
    deviceModeOptions: DeviceModeOption[];
};

type ExamForm = {
    subject_id: string;
    title: string;
    grade: string;
    classroom_ids: number[];
    opens_at: string;
    closes_at: string;
    duration_minutes: string;
    shuffle_questions: boolean;
    shuffle_options: boolean;
    weight_mcq: string;
    weight_matching: string;
    weight_essay: string;
    access_token: string;
    violation_limit: string;
    device_modes: string[];
    show_result_to_guardian: boolean;
};

const statusVariant: Record<ExamRow['status'], StatusBadgeVariant> = {
    draft: 'muted',
    scheduled: 'default',
    running: 'success',
    finished: 'muted',
};

function toLocalDateTimeInput(iso: string): string {
    const date = new Date(iso);
    const pad = (n: number): string => String(n).padStart(2, '0');

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

export default function CbtExamsIndex({
    canCreate,
    exams,
    filters,
    unitOptions,
    subjectOptions,
    classroomOptions,
    deviceModeOptions,
}: Props) {
    const [search, setSearch] = useState(filters.search);
    const [loading, setLoading] = useState(false);
    const [loadFailed, setLoadFailed] = useState(false);
    const [formOpen, setFormOpen] = useState(false);
    const [editing, setEditing] = useState<ExamRow | null>(null);
    const searchTimer = useRef<number | undefined>(undefined);

    const form = useForm<ExamForm>({
        subject_id: '',
        title: '',
        grade: '',
        classroom_ids: [],
        opens_at: '',
        closes_at: '',
        duration_minutes: '90',
        shuffle_questions: true,
        shuffle_options: true,
        weight_mcq: '2',
        weight_matching: '2',
        weight_essay: '4',
        access_token: '',
        violation_limit: '3',
        device_modes: ['lab', 'tablet'],
        show_result_to_guardian: false,
    });

    useEffect(() => () => window.clearTimeout(searchTimer.current), []);

    const visit = (
        changes: Partial<{
            unit_id: number | null;
            search: string;
            page: number;
        }>,
        searchValue: string = search,
    ): void => {
        const query = { ...filters, search: searchValue, ...changes };
        const params = Object.fromEntries(
            Object.entries(query).filter(
                ([, value]) =>
                    value !== '' && value !== null && value !== undefined,
            ),
        );

        router.get(CbtExamController.index.url(), params, {
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
            () => visit({ page: 1 }, value),
            300,
        );
    };

    const openCreate = (): void => {
        setEditing(null);
        form.clearErrors();
        form.setData({
            subject_id: subjectOptions[0] ? String(subjectOptions[0].id) : '',
            title: '',
            grade: '',
            classroom_ids: [],
            opens_at: '',
            closes_at: '',
            duration_minutes: '90',
            shuffle_questions: true,
            shuffle_options: true,
            weight_mcq: '2',
            weight_matching: '2',
            weight_essay: '4',
            access_token: '',
            violation_limit: '3',
            device_modes: ['lab', 'tablet'],
            show_result_to_guardian: false,
        });
        setFormOpen(true);
    };

    const openEdit = (exam: ExamRow): void => {
        setEditing(exam);
        form.clearErrors();
        form.setData({
            subject_id: String(exam.subject_id),
            title: exam.title,
            grade: exam.grade ?? '',
            classroom_ids: exam.classroom_ids,
            opens_at: toLocalDateTimeInput(exam.opens_at),
            closes_at: toLocalDateTimeInput(exam.closes_at),
            duration_minutes: String(exam.duration_minutes),
            shuffle_questions: exam.shuffle_questions,
            shuffle_options: exam.shuffle_options,
            weight_mcq: String(exam.weights.mcq ?? 2),
            weight_matching: String(exam.weights.matching ?? 2),
            weight_essay: String(exam.weights.essay_paper ?? 4),
            access_token: exam.access_token ?? '',
            violation_limit: String(exam.violation_limit),
            device_modes: exam.device_modes,
            show_result_to_guardian: exam.show_result_to_guardian,
        });
        setFormOpen(true);
    };

    const toggleClassroom = (id: number): void => {
        form.setData(
            'classroom_ids',
            form.data.classroom_ids.includes(id)
                ? form.data.classroom_ids.filter((item) => item !== id)
                : [...form.data.classroom_ids, id],
        );
    };

    const toggleDeviceMode = (mode: string): void => {
        form.setData(
            'device_modes',
            form.data.device_modes.includes(mode)
                ? form.data.device_modes.filter((item) => item !== mode)
                : [...form.data.device_modes, mode],
        );
    };

    const submit = (event: FormEvent): void => {
        event.preventDefault();

        form.transform((data) => ({
            subject_id: Number(data.subject_id),
            title: data.title,
            grade: data.grade || null,
            classroom_ids: data.classroom_ids,
            opens_at: data.opens_at.replace('T', ' '),
            closes_at: data.closes_at.replace('T', ' '),
            duration_minutes: Number(data.duration_minutes),
            shuffle_questions: data.shuffle_questions,
            shuffle_options: data.shuffle_options,
            weights: {
                mcq: Number(data.weight_mcq),
                matching: Number(data.weight_matching),
                essay_paper: Number(data.weight_essay),
            },
            access_token: data.access_token || null,
            violation_limit: Number(data.violation_limit),
            device_modes: data.device_modes,
            show_result_to_guardian: data.show_result_to_guardian,
        }));

        if (editing === null) {
            form.post(CbtExamController.store.url(), {
                preserveScroll: true,
                onSuccess: () => setFormOpen(false),
            });

            return;
        }

        form.put(CbtExamController.update.url(editing.id), {
            preserveScroll: true,
            onSuccess: () => {
                setFormOpen(false);
                setEditing(null);
            },
        });
    };

    const scheduleExam = (exam: ExamRow): void => {
        router.patch(
            CbtExamController.updateStatus.url(exam.id),
            { status: exam.status === 'draft' ? 'scheduled' : 'running' },
            { preserveScroll: true },
        );
    };

    const columns: DataTableColumn<ExamRow>[] = [
        {
            key: 'title',
            label: 'Ujian',
            render: (row) => (
                <div>
                    <p className="font-medium">{row.title}</p>
                    <p className="text-xs text-muted-foreground">
                        {row.subject
                            ? `${row.subject.code} — ${row.subject.name}`
                            : '—'}
                    </p>
                </div>
            ),
        },
        {
            key: 'window',
            label: 'Jendela waktu',
            render: (row) => (
                <div className="text-xs">
                    <DateText value={row.opens_at} mode="datetime" />
                    <span className="text-muted-foreground"> s.d. </span>
                    <DateText value={row.closes_at} mode="datetime" />
                </div>
            ),
        },
        {
            key: 'duration',
            label: 'Durasi',
            align: 'right',
            render: (row) => (
                <span className="tabular-nums">
                    {row.duration_minutes} menit
                </span>
            ),
        },
        {
            key: 'status',
            label: 'Status',
            align: 'center',
            render: (row) => (
                <StatusBadge variant={statusVariant[row.status]}>
                    {row.status_label}
                </StatusBadge>
            ),
        },
        ...(canCreate
            ? [
                  {
                      key: 'actions',
                      label: 'Aksi',
                      align: 'center' as const,
                      render: (row: ExamRow) => (
                          <RowActions
                              itemName={row.title}
                              viewHref={CbtQuestionController.index.url(row.id)}
                              onEdit={() => openEdit(row)}
                          >
                              <Tooltip>
                                  <TooltipTrigger asChild>
                                      <Button
                                          variant="ghost"
                                          size="icon"
                                          asChild
                                          aria-label={`Nilai essay ${row.title}`}
                                      >
                                          <Link
                                              href={CbtEssayScoreController.index.url(
                                                  row.id,
                                              )}
                                          >
                                              <FileCheck2
                                                  className="size-4"
                                                  strokeWidth={1.75}
                                              />
                                          </Link>
                                      </Button>
                                  </TooltipTrigger>
                                  <TooltipContent>Nilai essay</TooltipContent>
                              </Tooltip>
                              <Tooltip>
                                  <TooltipTrigger asChild>
                                      <Button
                                          variant="ghost"
                                          size="icon"
                                          asChild
                                          aria-label={`Monitor pengawas ${row.title}`}
                                      >
                                          <Link
                                              href={CbtMonitorController.index.url(
                                                  row.id,
                                              )}
                                          >
                                              <Monitor
                                                  className="size-4"
                                                  strokeWidth={1.75}
                                              />
                                          </Link>
                                      </Button>
                                  </TooltipTrigger>
                                  <TooltipContent>
                                      Monitor pengawas
                                  </TooltipContent>
                              </Tooltip>
                              {(row.status === 'draft' ||
                                  row.status === 'scheduled') && (
                                  <Tooltip>
                                      <TooltipTrigger asChild>
                                          <Button
                                              type="button"
                                              variant="ghost"
                                              size="icon"
                                              aria-label={
                                                  row.status === 'draft'
                                                      ? `Jadwalkan ${row.title}`
                                                      : `Mulai ${row.title}`
                                              }
                                              onClick={() => scheduleExam(row)}
                                          >
                                              <CalendarCheck
                                                  className="size-4 text-primary"
                                                  strokeWidth={1.75}
                                              />
                                          </Button>
                                      </TooltipTrigger>
                                      <TooltipContent>
                                          {row.status === 'draft'
                                              ? 'Jadwalkan ujian'
                                              : 'Aktifkan ujian'}
                                      </TooltipContent>
                                  </Tooltip>
                              )}
                          </RowActions>
                      ),
                  },
              ]
            : []),
    ];

    return (
        <StaffLayout
            breadcrumbs={[
                { title: 'Beranda', href: AdminDashboardController.url() },
                { title: 'Ujian', href: CbtExamController.index.url() },
                { title: 'Daftar ujian', href: CbtExamController.index.url() },
            ]}
        >
            <Head title="Daftar ujian" />

            <div className="space-y-6">
                <PageHeader
                    title="Daftar ujian CBT"
                    description="Atur jadwal ujian, rombel peserta, bobot soal, dan mode perangkat yang diizinkan."
                    actions={
                        canCreate ? (
                            <Button
                                type="button"
                                disabled={
                                    subjectOptions.length === 0 ||
                                    classroomOptions.length === 0
                                }
                                onClick={openCreate}
                            >
                                <Plus className="size-4" strokeWidth={1.75} />
                                Buat ujian
                            </Button>
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
                            visit({ unit_id: Number(value), page: 1 })
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

                    <Input
                        type="search"
                        value={search}
                        onChange={(event) => changeSearch(event.target.value)}
                        placeholder="Cari judul ujian..."
                        aria-label="Cari judul ujian"
                        className="w-full sm:max-w-xs"
                    />
                </div>

                {loadFailed ? (
                    <ErrorState
                        title="Gagal memuat daftar ujian"
                        description="Periksa koneksi Anda lalu coba muat ulang."
                        onRetry={() => visit({})}
                    />
                ) : (
                    <DataTable
                        columns={columns}
                        data={exams.data}
                        rowKey={(row) => row.id}
                        loading={loading}
                        empty={{
                            title: 'Belum ada ujian',
                            description: canCreate
                                ? 'Buat ujian baru setelah mata pelajaran dan penugasan guru tersedia.'
                                : 'Belum ada ujian yang dijadwalkan untuk unit ini.',
                            action:
                                canCreate &&
                                subjectOptions.length > 0 &&
                                classroomOptions.length > 0 ? (
                                    <Button type="button" onClick={openCreate}>
                                        <Plus
                                            className="size-4"
                                            strokeWidth={1.75}
                                        />
                                        Buat ujian
                                    </Button>
                                ) : undefined,
                        }}
                        pagination={{
                            currentPage: exams.meta.current_page,
                            lastPage: exams.meta.last_page,
                            perPage: exams.meta.per_page,
                            total: exams.meta.total,
                            from: exams.meta.from,
                            to: exams.meta.to,
                        }}
                        onPageChange={(page) => visit({ page })}
                    />
                )}
            </div>

            <FormDialog
                open={formOpen}
                onOpenChange={(open) => {
                    setFormOpen(open);
                    if (!open) {
                        setEditing(null);
                    }
                }}
                title={editing === null ? 'Buat ujian CBT' : 'Ubah ujian CBT'}
                contentClassName="sm:max-w-2xl max-h-[88vh] overflow-y-auto"
                processing={form.processing}
                submitLabel={
                    editing === null ? 'Simpan ujian' : 'Simpan perubahan'
                }
                onSubmit={submit}
            >
                <div className="space-y-4">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="space-y-1.5">
                            <Label htmlFor="exam-subject">Mata pelajaran</Label>
                            <Select
                                value={form.data.subject_id}
                                onValueChange={(value) =>
                                    form.setData('subject_id', value)
                                }
                            >
                                <SelectTrigger id="exam-subject">
                                    <SelectValue placeholder="Pilih mapel" />
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
                            <InputError message={form.errors.subject_id} />
                        </div>

                        <div className="space-y-1.5">
                            <Label htmlFor="exam-title">Judul ujian</Label>
                            <Input
                                id="exam-title"
                                value={form.data.title}
                                onChange={(event) =>
                                    form.setData('title', event.target.value)
                                }
                                placeholder="PAS Ganjil Matematika"
                                maxLength={150}
                            />
                            <InputError message={form.errors.title} />
                        </div>
                    </div>

                    <div className="space-y-1.5">
                        <Label>Rombel peserta</Label>
                        <div className="grid grid-cols-2 gap-2 rounded-md border border-border p-3 sm:grid-cols-3">
                            {classroomOptions.map((option) => (
                                <label
                                    key={option.id}
                                    className="flex cursor-pointer items-center gap-2 text-sm"
                                >
                                    <Checkbox
                                        checked={form.data.classroom_ids.includes(
                                            option.id,
                                        )}
                                        onCheckedChange={() =>
                                            toggleClassroom(option.id)
                                        }
                                    />
                                    <span>{option.name}</span>
                                </label>
                            ))}
                        </div>
                        <InputError message={form.errors.classroom_ids} />
                    </div>

                    <div className="grid gap-4 sm:grid-cols-3">
                        <div className="space-y-1.5">
                            <Label htmlFor="exam-opens">Waktu buka</Label>
                            <Input
                                id="exam-opens"
                                type="datetime-local"
                                value={form.data.opens_at}
                                onChange={(event) =>
                                    form.setData('opens_at', event.target.value)
                                }
                            />
                            <InputError message={form.errors.opens_at} />
                        </div>

                        <div className="space-y-1.5">
                            <Label htmlFor="exam-closes">Waktu tutup</Label>
                            <Input
                                id="exam-closes"
                                type="datetime-local"
                                value={form.data.closes_at}
                                onChange={(event) =>
                                    form.setData(
                                        'closes_at',
                                        event.target.value,
                                    )
                                }
                            />
                            <InputError message={form.errors.closes_at} />
                        </div>

                        <div className="space-y-1.5">
                            <Label htmlFor="exam-duration">
                                Durasi (menit)
                            </Label>
                            <Input
                                id="exam-duration"
                                type="number"
                                min={5}
                                max={300}
                                value={form.data.duration_minutes}
                                onChange={(event) =>
                                    form.setData(
                                        'duration_minutes',
                                        event.target.value,
                                    )
                                }
                            />
                            <InputError
                                message={form.errors.duration_minutes}
                            />
                        </div>
                    </div>

                    <div className="grid gap-4 sm:grid-cols-3">
                        <div className="space-y-1.5">
                            <Label htmlFor="weight-mcq">Bobot PG</Label>
                            <Input
                                id="weight-mcq"
                                type="number"
                                min={0}
                                max={100}
                                value={form.data.weight_mcq}
                                onChange={(event) =>
                                    form.setData(
                                        'weight_mcq',
                                        event.target.value,
                                    )
                                }
                            />
                        </div>
                        <div className="space-y-1.5">
                            <Label htmlFor="weight-matching">
                                Bobot Menjodohkan
                            </Label>
                            <Input
                                id="weight-matching"
                                type="number"
                                min={0}
                                max={100}
                                value={form.data.weight_matching}
                                onChange={(event) =>
                                    form.setData(
                                        'weight_matching',
                                        event.target.value,
                                    )
                                }
                            />
                        </div>
                        <div className="space-y-1.5">
                            <Label htmlFor="weight-essay">Bobot Essay</Label>
                            <Input
                                id="weight-essay"
                                type="number"
                                min={0}
                                max={100}
                                value={form.data.weight_essay}
                                onChange={(event) =>
                                    form.setData(
                                        'weight_essay',
                                        event.target.value,
                                    )
                                }
                            />
                        </div>
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="space-y-1.5">
                            <Label htmlFor="exam-token">
                                Token ujian (opsional)
                            </Label>
                            <Input
                                id="exam-token"
                                value={form.data.access_token}
                                onChange={(event) =>
                                    form.setData(
                                        'access_token',
                                        event.target.value,
                                    )
                                }
                                placeholder="Kosongkan jika tanpa token"
                                maxLength={20}
                            />
                        </div>

                        <div className="space-y-1.5">
                            <Label htmlFor="exam-violation-limit">
                                Batas pelanggaran
                            </Label>
                            <Input
                                id="exam-violation-limit"
                                type="number"
                                min={1}
                                max={20}
                                value={form.data.violation_limit}
                                onChange={(event) =>
                                    form.setData(
                                        'violation_limit',
                                        event.target.value,
                                    )
                                }
                            />
                        </div>
                    </div>

                    <div className="space-y-1.5">
                        <Label>Mode perangkat yang diizinkan</Label>
                        <div className="flex flex-wrap gap-4 rounded-md border border-border p-3">
                            {deviceModeOptions.map((mode) => (
                                <label
                                    key={mode.value}
                                    className="flex cursor-pointer items-center gap-2 text-sm"
                                >
                                    <Checkbox
                                        checked={form.data.device_modes.includes(
                                            mode.value,
                                        )}
                                        onCheckedChange={() =>
                                            toggleDeviceMode(mode.value)
                                        }
                                    />
                                    <span>{mode.label}</span>
                                </label>
                            ))}
                        </div>
                        <InputError message={form.errors.device_modes} />
                    </div>

                    <div className="flex flex-wrap gap-4">
                        <label className="flex cursor-pointer items-center gap-2 text-sm">
                            <Checkbox
                                checked={form.data.shuffle_questions}
                                onCheckedChange={(checked) =>
                                    form.setData(
                                        'shuffle_questions',
                                        Boolean(checked),
                                    )
                                }
                            />
                            <span>Acak urutan soal</span>
                        </label>
                        <label className="flex cursor-pointer items-center gap-2 text-sm">
                            <Checkbox
                                checked={form.data.shuffle_options}
                                onCheckedChange={(checked) =>
                                    form.setData(
                                        'shuffle_options',
                                        Boolean(checked),
                                    )
                                }
                            />
                            <span>Acak pilihan jawaban</span>
                        </label>
                        <label className="flex cursor-pointer items-center gap-2 text-sm">
                            <Checkbox
                                checked={form.data.show_result_to_guardian}
                                onCheckedChange={(checked) =>
                                    form.setData(
                                        'show_result_to_guardian',
                                        Boolean(checked),
                                    )
                                }
                            />
                            <span>Tampilkan hasil ke wali</span>
                        </label>
                    </div>
                </div>
            </FormDialog>
        </StaffLayout>
    );
}
