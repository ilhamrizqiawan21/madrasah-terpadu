import { Head, router, useForm } from '@inertiajs/react';
import { Paperclip, Plus } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { useState } from 'react';
import AssignmentController from '@/actions/App/Http/Controllers/Admin/AssignmentController';
import type { DataTableColumn } from '@/components/app/data-table';
import { DataTable } from '@/components/app/data-table';
import { DateInput } from '@/components/app/date-input';
import { DateText } from '@/components/app/date-text';
import { FormDialog } from '@/components/app/form-dialog';
import { PageHeader } from '@/components/app/page-header';
import { RowActions } from '@/components/app/row-actions';
import { TimeInput } from '@/components/app/time-input';
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
import StaffLayout from '@/layouts/staff-layout';
import { formatTime } from '@/lib/format';
import { dashboard } from '@/routes/admin';
import type { PaginatedResource } from '@/types';

type AssignmentRow = {
    id: number;
    subject_id: number;
    subject_name: string;
    classroom_id: number;
    classroom_name: string;
    teacher_id: number;
    teacher_name: string;
    title: string;
    description: string | null;
    due_at: string;
    due_date: string;
    due_time: string;
    has_attachment: boolean;
    can_edit: boolean;
};

type Props = {
    assignments: PaginatedResource<AssignmentRow>;
    subjects: { id: number; unit_id: number; label: string }[];
    classrooms: { id: number; unit_id: number; label: string }[];
    filters: { classroom_id: string; subject_id: string; search: string };
    canCreate: boolean;
};

const ALL_VALUE = 'semua';

export default function AssignmentsIndex({
    assignments,
    subjects,
    classrooms,
    filters,
    canCreate,
}: Props) {
    const [search, setSearch] = useState(filters.search);
    const [loading, setLoading] = useState(false);
    const [formOpen, setFormOpen] = useState(false);
    const [editing, setEditing] = useState<AssignmentRow | null>(null);

    const form = useForm<{
        subject_id: string;
        classroom_id: string;
        title: string;
        description: string;
        due_date: string;
        due_time: string;
        attachment: File | null;
    }>({
        subject_id: subjects[0] ? String(subjects[0].id) : '',
        classroom_id: classrooms[0] ? String(classrooms[0].id) : '',
        title: '',
        description: '',
        due_date: '',
        due_time: '23:59',
        attachment: null,
    });

    function visitFilters(next: Partial<Props['filters']>, page?: number) {
        const merged = {
            classroom_id: filters.classroom_id,
            subject_id: filters.subject_id,
            search,
            ...next,
        };
        const params: Record<string, string | number> = {};
        if (merged.classroom_id !== '' && merged.classroom_id !== ALL_VALUE) {
            params.classroom_id = merged.classroom_id;
        }
        if (merged.subject_id !== '' && merged.subject_id !== ALL_VALUE) {
            params.subject_id = merged.subject_id;
        }
        if (merged.search.trim() !== '') {
            params.search = merged.search.trim();
        }
        if (page && page > 1) {
            params.page = page;
        }
        router.get(AssignmentController.index(), params, {
            preserveScroll: true,
            preserveState: true,
            onStart: () => setLoading(true),
            onFinish: () => setLoading(false),
        });
    }

    function openCreate() {
        setEditing(null);
        form.clearErrors();
        form.setData({
            subject_id: subjects[0] ? String(subjects[0].id) : '',
            classroom_id: classrooms[0] ? String(classrooms[0].id) : '',
            title: '',
            description: '',
            due_date: '',
            due_time: '23:59',
            attachment: null,
        });
        setFormOpen(true);
    }

    function openEdit(row: AssignmentRow) {
        setEditing(row);
        form.clearErrors();
        form.setData({
            subject_id: String(row.subject_id),
            classroom_id: String(row.classroom_id),
            title: row.title,
            description: row.description ?? '',
            due_date: row.due_date,
            due_time: row.due_time,
            attachment: null,
        });
        setFormOpen(true);
    }

    function submitForm(e: FormEvent) {
        e.preventDefault();
        if (editing) {
            form.transform((data) => ({ ...data, _method: 'put' }));
            form.post(
                AssignmentController.update({ assignment: editing.id }).url,
                {
                    forceFormData: true,
                    preserveScroll: true,
                    onSuccess: () => setFormOpen(false),
                },
            );
        } else {
            form.transform((data) => data);
            form.post(AssignmentController.store().url, {
                forceFormData: true,
                preserveScroll: true,
                onSuccess: () => setFormOpen(false),
            });
        }
    }

    const columns: DataTableColumn<AssignmentRow>[] = [
        {
            key: 'title',
            label: 'Tugas & PR',
            render: (row) => (
                <div>
                    <div className="font-medium">{row.title}</div>
                    <div className="text-xs text-muted-foreground">
                        {row.subject_name} · {row.classroom_name}
                    </div>
                </div>
            ),
        },
        {
            key: 'teacher',
            label: 'Guru',
            render: (row) => (
                <span className="text-sm">{row.teacher_name}</span>
            ),
        },
        {
            key: 'due_at',
            label: 'Tenggat',
            render: (row) => (
                <span className="text-sm">
                    <DateText value={row.due_at} /> {formatTime(row.due_at)}
                </span>
            ),
        },
        {
            key: 'attachment',
            label: 'Lampiran',
            align: 'center',
            render: (row) =>
                row.has_attachment ? (
                    <Button size="sm" variant="ghost" asChild>
                        <a
                            href={AssignmentController.attachment.url({
                                assignment: row.id,
                            })}
                        >
                            <Paperclip className="size-3.5" />
                            Unduh
                        </a>
                    </Button>
                ) : (
                    <span className="text-xs text-muted-foreground">—</span>
                ),
        },
        {
            key: 'actions',
            label: 'Aksi',
            align: 'center',
            render: (row) => (
                <RowActions
                    itemName={row.title}
                    onEdit={row.can_edit ? () => openEdit(row) : undefined}
                    onDelete={
                        row.can_edit
                            ? () =>
                                  router.delete(
                                      AssignmentController.destroy({
                                          assignment: row.id,
                                      }),
                                      { preserveScroll: true },
                                  )
                            : undefined
                    }
                    deleteTitle="Hapus tugas?"
                    deleteDescription={`Tugas "${row.title}" akan dihapus permanen.`}
                />
            ),
        },
    ];

    return (
        <StaffLayout
            breadcrumbs={[
                { title: 'Beranda', href: dashboard() },
                { title: 'Tugas & PR', href: AssignmentController.index() },
            ]}
        >
            <Head title="Tugas & PR" />

            <div className="space-y-6">
                <PageHeader
                    title="Tugas & PR"
                    description="Buat dan bagikan tugas per mata pelajaran dan rombel."
                    actions={
                        canCreate && (
                            <Button onClick={openCreate}>
                                <Plus className="size-4" />
                                Tambah tugas
                            </Button>
                        )
                    }
                />

                <div className="flex flex-wrap items-center gap-3">
                    <Select
                        value={
                            filters.classroom_id === ''
                                ? ALL_VALUE
                                : filters.classroom_id
                        }
                        onValueChange={(v) =>
                            visitFilters({
                                classroom_id: v === ALL_VALUE ? '' : v,
                            })
                        }
                    >
                        <SelectTrigger className="w-48">
                            <SelectValue placeholder="Semua rombel" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL_VALUE}>
                                Semua rombel
                            </SelectItem>
                            {classrooms.map((c) => (
                                <SelectItem key={c.id} value={String(c.id)}>
                                    {c.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    <Select
                        value={
                            filters.subject_id === ''
                                ? ALL_VALUE
                                : filters.subject_id
                        }
                        onValueChange={(v) =>
                            visitFilters({
                                subject_id: v === ALL_VALUE ? '' : v,
                            })
                        }
                    >
                        <SelectTrigger className="w-48">
                            <SelectValue placeholder="Semua mapel" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL_VALUE}>
                                Semua mapel
                            </SelectItem>
                            {subjects.map((s) => (
                                <SelectItem key={s.id} value={String(s.id)}>
                                    {s.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    <form
                        className="flex items-center gap-2"
                        onSubmit={(e) => {
                            e.preventDefault();
                            visitFilters({ search });
                        }}
                    >
                        <Input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Cari judul tugas..."
                            className="w-56"
                        />
                        <Button type="submit" variant="outline" size="sm">
                            Cari
                        </Button>
                    </form>
                </div>

                <DataTable
                    columns={columns}
                    data={assignments.data}
                    rowKey={(row) => row.id}
                    loading={loading}
                    pagination={{
                        currentPage: assignments.meta.current_page,
                        lastPage: assignments.meta.last_page,
                        total: assignments.meta.total,
                        perPage: assignments.meta.per_page,
                        from: assignments.meta.from,
                        to: assignments.meta.to,
                    }}
                    onPageChange={(page) => visitFilters({}, page)}
                    empty={{
                        title: 'Belum ada tugas',
                        description:
                            'Tambahkan tugas atau PR untuk dibagikan ke siswa dan orang tua.',
                        action: canCreate ? (
                            <Button onClick={openCreate}>Tambah tugas</Button>
                        ) : undefined,
                    }}
                />
            </div>

            <FormDialog
                open={formOpen}
                onOpenChange={setFormOpen}
                title={editing ? 'Ubah tugas' : 'Tambah tugas'}
                onSubmit={submitForm}
                processing={form.processing}
                submitLabel={editing ? 'Simpan perubahan' : 'Simpan tugas'}
            >
                <div className="space-y-4">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="space-y-1.5">
                            <Label htmlFor="asg-subject">Mata pelajaran</Label>
                            <Select
                                value={form.data.subject_id}
                                onValueChange={(v) =>
                                    form.setData('subject_id', v)
                                }
                            >
                                <SelectTrigger id="asg-subject">
                                    <SelectValue placeholder="Pilih mapel" />
                                </SelectTrigger>
                                <SelectContent>
                                    {subjects.map((s) => (
                                        <SelectItem
                                            key={s.id}
                                            value={String(s.id)}
                                        >
                                            {s.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={form.errors.subject_id} />
                        </div>

                        <div className="space-y-1.5">
                            <Label htmlFor="asg-classroom">Rombel</Label>
                            <Select
                                value={form.data.classroom_id}
                                onValueChange={(v) =>
                                    form.setData('classroom_id', v)
                                }
                            >
                                <SelectTrigger id="asg-classroom">
                                    <SelectValue placeholder="Pilih rombel" />
                                </SelectTrigger>
                                <SelectContent>
                                    {classrooms.map((c) => (
                                        <SelectItem
                                            key={c.id}
                                            value={String(c.id)}
                                        >
                                            {c.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={form.errors.classroom_id} />
                        </div>
                    </div>

                    <div className="space-y-1.5">
                        <Label htmlFor="asg-title">Judul tugas</Label>
                        <Input
                            id="asg-title"
                            value={form.data.title}
                            onChange={(e) =>
                                form.setData('title', e.target.value)
                            }
                            placeholder="Mis. Latihan Soal Bab 3"
                        />
                        <InputError message={form.errors.title} />
                    </div>

                    <div className="space-y-1.5">
                        <Label htmlFor="asg-desc">Deskripsi (opsional)</Label>
                        <Textarea
                            id="asg-desc"
                            value={form.data.description}
                            onChange={(e) =>
                                form.setData('description', e.target.value)
                            }
                            rows={3}
                            placeholder="Petunjuk pengerjaan untuk siswa..."
                        />
                        <InputError message={form.errors.description} />
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="space-y-1.5">
                            <Label htmlFor="asg-date">Tanggal tenggat</Label>
                            <DateInput
                                id="asg-date"
                                value={form.data.due_date}
                                onChange={(e) =>
                                    form.setData('due_date', e.target.value)
                                }
                            />
                            <InputError message={form.errors.due_date} />
                        </div>

                        <div className="space-y-1.5">
                            <Label htmlFor="asg-time">Jam tenggat (WITA)</Label>
                            <TimeInput
                                id="asg-time"
                                value={form.data.due_time}
                                onChange={(v) => form.setData('due_time', v)}
                            />
                            <InputError message={form.errors.due_time} />
                        </div>
                    </div>

                    <div className="space-y-1.5">
                        <Label htmlFor="asg-file">
                            Lampiran berkas (PDF/DOCX/gambar, maks 2 MB)
                        </Label>
                        <Input
                            id="asg-file"
                            type="file"
                            accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"
                            onChange={(e) =>
                                form.setData(
                                    'attachment',
                                    e.target.files?.[0] ?? null,
                                )
                            }
                        />
                        <InputError message={form.errors.attachment} />
                    </div>
                </div>
            </FormDialog>
        </StaffLayout>
    );
}

AssignmentsIndex.layout = (page: ReactNode) => page;
