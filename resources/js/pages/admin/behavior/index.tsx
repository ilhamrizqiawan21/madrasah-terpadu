import { Head, router, useForm } from '@inertiajs/react';
import { Ban, CheckSquare, Image as ImageIcon, Plus } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { useMemo, useState } from 'react';
import BehaviorRecordController from '@/actions/App/Http/Controllers/Admin/BehaviorRecordController';
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
import {
    Dialog,
    DialogContent,
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
import { Textarea } from '@/components/ui/textarea';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import StaffLayout from '@/layouts/staff-layout';
import type { PaginatedResource } from '@/types';

type BehaviorRecordRow = {
    id: number;
    student_id: number;
    student_name: string;
    student_nis: string;
    rule_code: string;
    rule_name: string;
    category: 'violation' | 'achievement' | 'good_deed';
    category_label: string;
    points: number;
    occurred_on: string;
    note: string | null;
    has_evidence: boolean;
    recorded_by_name: string;
    status: 'active' | 'voided';
    status_label: string;
    void_reason: string | null;
    voided_by_name: string | null;
};

type ClassroomOption = { id: number; label: string };
type StudentOption = {
    student_id: number;
    classroom_id: number;
    nis: string;
    name: string;
};
type RuleOption = {
    id: number;
    code: string;
    name: string;
    category: 'violation' | 'achievement' | 'good_deed';
    category_label: string;
    level: string | null;
    points: number;
};
type OptionItem = { value: string; label: string };

type Filters = {
    search: string;
    category: string;
    status: string;
    date: string;
};

type Props = {
    records: PaginatedResource<BehaviorRecordRow>;
    classrooms: ClassroomOption[];
    students: StudentOption[];
    rules: RuleOption[];
    categories: OptionItem[];
    statuses: OptionItem[];
    filters: Filters;
    today: string;
    canRecord: boolean;
    canVoid: boolean;
};

type RecordForm = {
    student_ids: number[];
    point_rule_id: string;
    occurred_on: string;
    note: string;
    evidence: File | null;
};

function categoryVariant(
    category: BehaviorRecordRow['category'],
): StatusBadgeVariant {
    if (category === 'violation') {
        return 'destructive';
    }
    if (category === 'achievement') {
        return 'success';
    }

    return 'muted';
}

export default function BehaviorRecordsIndex({
    records,
    classrooms,
    students,
    rules,
    categories,
    statuses,
    filters,
    today,
    canRecord,
    canVoid,
}: Props) {
    const [search, setSearch] = useState(filters.search);
    const [loading, setLoading] = useState(false);
    const [loadFailed, setLoadFailed] = useState(false);
    const [createOpen, setCreateOpen] = useState(false);
    const [voidTarget, setVoidTarget] = useState<BehaviorRecordRow | null>(
        null,
    );
    const [evidenceRecord, setEvidenceRecord] =
        useState<BehaviorRecordRow | null>(null);

    const [selectedClassroom, setSelectedClassroom] = useState<string>('semua');
    const [studentQuery, setStudentQuery] = useState('');

    const form = useForm<RecordForm>({
        student_ids: [],
        point_rule_id: rules[0] ? String(rules[0].id) : '',
        occurred_on: today,
        note: '',
        evidence: null,
    });

    const voidForm = useForm<{ void_reason: string }>({
        void_reason: '',
    });

    const visibleStudents = useMemo(() => {
        const q = studentQuery.trim().toLowerCase();

        return students.filter((s) => {
            if (
                selectedClassroom !== 'semua' &&
                String(s.classroom_id) !== selectedClassroom
            ) {
                return false;
            }
            if (q !== '') {
                return (
                    s.name.toLowerCase().includes(q) ||
                    s.nis.toLowerCase().includes(q)
                );
            }

            return true;
        });
    }, [students, selectedClassroom, studentQuery]);

    function visitFilters(next: Partial<Filters>, page?: number) {
        const merged = {
            search,
            category: filters.category,
            status: filters.status,
            date: filters.date,
            ...next,
        };

        router.get(
            BehaviorRecordController.index.url({
                query: {
                    search: merged.search || undefined,
                    category: merged.category || undefined,
                    status: merged.status || undefined,
                    date: merged.date || undefined,
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
        form.setData({
            student_ids: [],
            point_rule_id: rules[0] ? String(rules[0].id) : '',
            occurred_on: today,
            note: '',
            evidence: null,
        });
        form.clearErrors();
        setSelectedClassroom('semua');
        setStudentQuery('');
        setCreateOpen(true);
    }

    function toggleStudent(studentId: number, checked: boolean) {
        const set = new Set(form.data.student_ids);
        if (checked) {
            set.add(studentId);
        } else {
            set.delete(studentId);
        }
        form.setData('student_ids', Array.from(set));
    }

    function selectAllVisible() {
        const set = new Set(form.data.student_ids);
        for (const s of visibleStudents) {
            set.add(s.student_id);
        }
        form.setData('student_ids', Array.from(set));
    }

    function submitCreate(e: FormEvent<HTMLFormElement>) {
        e.preventDefault();
        router.post(
            BehaviorRecordController.store.url(),
            {
                student_ids: form.data.student_ids,
                point_rule_id: Number(form.data.point_rule_id),
                occurred_on: form.data.occurred_on,
                note: form.data.note || null,
                evidence: form.data.evidence,
            },
            {
                forceFormData: true,
                preserveScroll: true,
                onSuccess: () => setCreateOpen(false),
                onError: (errors) =>
                    form.setError(errors as Record<keyof RecordForm, string>),
            },
        );
    }

    function submitVoid(e: FormEvent<HTMLFormElement>) {
        e.preventDefault();
        if (!voidTarget) {
            return;
        }

        router.post(
            BehaviorRecordController.void.url(voidTarget.id),
            { void_reason: voidForm.data.void_reason },
            {
                preserveScroll: true,
                onSuccess: () => {
                    voidForm.reset();
                    setVoidTarget(null);
                },
                onError: (errors) =>
                    voidForm.setError(errors as Record<'void_reason', string>),
            },
        );
    }

    const columns: DataTableColumn<BehaviorRecordRow>[] = [
        {
            key: 'occurred_on',
            label: 'Tanggal',
            render: (row) => <DateText value={row.occurred_on} />,
        },
        {
            key: 'student',
            label: 'Siswa',
            render: (row) => (
                <div>
                    <div className="font-medium text-foreground">
                        {row.student_name}
                    </div>
                    <div className="text-xs text-muted-foreground">
                        NIS {row.student_nis}
                    </div>
                </div>
            ),
        },
        {
            key: 'rule',
            label: 'Aturan',
            render: (row) => (
                <div>
                    <div className="text-sm font-medium text-foreground">
                        <span className="mr-1.5 font-mono text-xs text-muted-foreground">
                            {row.rule_code}
                        </span>
                        {row.rule_name}
                    </div>
                    {row.note ? (
                        <div className="text-xs text-muted-foreground">
                            {row.note}
                        </div>
                    ) : null}
                    {row.status === 'voided' && row.void_reason ? (
                        <div className="text-xs text-destructive">
                            Dibatalkan: {row.void_reason}
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
            key: 'points',
            label: 'Poin',
            align: 'right',
            render: (row) => (
                <span className="font-medium tabular-nums">
                    {row.category === 'violation'
                        ? `-${row.points}`
                        : `+${row.points}`}
                </span>
            ),
        },
        {
            key: 'status',
            label: 'Status',
            align: 'center',
            render: (row) => (
                <StatusBadge
                    variant={row.status === 'active' ? 'success' : 'muted'}
                >
                    {row.status_label}
                </StatusBadge>
            ),
        },
        {
            key: 'actions',
            label: 'Aksi',
            align: 'center',
            render: (row) => (
                <RowActions itemName={row.student_name}>
                    {row.has_evidence ? (
                        <Tooltip>
                            <TooltipTrigger asChild>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    aria-label={`Lihat bukti ${row.student_name}`}
                                    onClick={() => setEvidenceRecord(row)}
                                >
                                    <ImageIcon className="h-4 w-4 text-muted-foreground" />
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent>Lihat bukti foto</TooltipContent>
                        </Tooltip>
                    ) : null}
                    {canVoid && row.status === 'active' ? (
                        <Tooltip>
                            <TooltipTrigger asChild>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    aria-label={`Batalkan catatan ${row.student_name}`}
                                    onClick={() => {
                                        voidForm.reset();
                                        voidForm.clearErrors();
                                        setVoidTarget(row);
                                    }}
                                >
                                    <Ban className="h-4 w-4 text-destructive" />
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent>Batalkan catatan</TooltipContent>
                        </Tooltip>
                    ) : null}
                </RowActions>
            ),
        },
    ];

    return (
        <>
            <Head title="Catatan perilaku" />
            <div className="space-y-6">
                <PageHeader
                    title="Catatan perilaku"
                    description="Buku saku pelanggaran, prestasi, dan kebaikan siswa per tahun ajaran."
                    actions={
                        canRecord ? (
                            <Button type="button" onClick={openCreate}>
                                <Plus className="mr-2 h-4 w-4" />
                                Catat perilaku
                            </Button>
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
                            placeholder="Cari nama atau NIS siswa..."
                            aria-label="Cari nama atau NIS siswa"
                        />
                    </form>

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
                            className="w-[150px]"
                            aria-label="Filter status"
                        >
                            <SelectValue placeholder="Semua status" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="semua">Semua status</SelectItem>
                            {statuses.map((st) => (
                                <SelectItem key={st.value} value={st.value}>
                                    {st.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    <Input
                        type="date"
                        value={filters.date}
                        onChange={(e) => visitFilters({ date: e.target.value })}
                        className="w-[160px]"
                        aria-label="Filter tanggal"
                    />
                </div>

                {loadFailed ? (
                    <ErrorState
                        description="Periksa koneksi lalu coba muat ulang catatan perilaku."
                        onRetry={() => visitFilters({})}
                    />
                ) : (
                    <DataTable
                        columns={columns}
                        data={records.data}
                        rowKey={(row) => row.id}
                        loading={loading}
                        pagination={{
                            currentPage: records.meta.current_page,
                            lastPage: records.meta.last_page,
                            perPage: records.meta.per_page,
                            total: records.meta.total,
                            from: records.meta.from,
                            to: records.meta.to,
                        }}
                        onPageChange={(page) => visitFilters({}, page)}
                        empty={{
                            title: 'Belum ada catatan perilaku',
                            description:
                                'Catat pelanggaran, prestasi, atau kebaikan siswa untuk mulai mengisi buku saku.',
                            action: canRecord ? (
                                <Button type="button" onClick={openCreate}>
                                    <Plus className="mr-2 h-4 w-4" />
                                    Catat perilaku
                                </Button>
                            ) : undefined,
                        }}
                    />
                )}
            </div>

            <FormDialog
                open={createOpen}
                onOpenChange={setCreateOpen}
                title="Catat perilaku siswa"
                description="Pilih satu atau banyak siswa sekaligus, lalu tentukan aturan poinnya."
                submitLabel="Simpan catatan"
                onSubmit={submitCreate}
                processing={form.processing}
                contentClassName="sm:max-w-2xl"
            >
                <div className="space-y-4">
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div className="space-y-2">
                            <Label htmlFor="record-rule">Aturan poin</Label>
                            <Select
                                value={form.data.point_rule_id}
                                onValueChange={(v) =>
                                    form.setData('point_rule_id', v)
                                }
                            >
                                <SelectTrigger id="record-rule">
                                    <SelectValue placeholder="Pilih aturan" />
                                </SelectTrigger>
                                <SelectContent>
                                    {rules.map((r) => (
                                        <SelectItem
                                            key={r.id}
                                            value={String(r.id)}
                                        >
                                            {r.code} · {r.name} ({r.points}{' '}
                                            poin)
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={form.errors.point_rule_id} />
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="record-date">
                                Tanggal kejadian
                            </Label>
                            <Input
                                id="record-date"
                                type="date"
                                value={form.data.occurred_on}
                                onChange={(e) =>
                                    form.setData('occurred_on', e.target.value)
                                }
                                required
                            />
                            <InputError message={form.errors.occurred_on} />
                        </div>
                    </div>

                    <div className="space-y-2">
                        <div className="flex flex-wrap items-center justify-between gap-2">
                            <Label>
                                Siswa ({form.data.student_ids.length} dipilih)
                            </Label>
                            <div className="flex items-center gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={selectAllVisible}
                                >
                                    <CheckSquare className="mr-1.5 h-3.5 w-3.5" />
                                    Pilih tampil
                                </Button>
                                {form.data.student_ids.length > 0 ? (
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        onClick={() =>
                                            form.setData('student_ids', [])
                                        }
                                    >
                                        Bersihkan
                                    </Button>
                                ) : null}
                            </div>
                        </div>

                        <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
                            <Select
                                value={selectedClassroom}
                                onValueChange={setSelectedClassroom}
                            >
                                <SelectTrigger aria-label="Filter rombel">
                                    <SelectValue placeholder="Semua rombel" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="semua">
                                        Semua rombel
                                    </SelectItem>
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

                            <Input
                                value={studentQuery}
                                onChange={(e) =>
                                    setStudentQuery(e.target.value)
                                }
                                placeholder="Saring nama / NIS..."
                                aria-label="Saring nama atau NIS siswa"
                            />
                        </div>

                        <div className="max-h-48 divide-y divide-border overflow-y-auto rounded-md border border-border">
                            {visibleStudents.length === 0 ? (
                                <div className="px-3 py-4 text-center text-sm text-muted-foreground">
                                    Tidak ada siswa yang sesuai filter.
                                </div>
                            ) : (
                                visibleStudents.map((s) => {
                                    const checked =
                                        form.data.student_ids.includes(
                                            s.student_id,
                                        );

                                    return (
                                        <label
                                            key={s.student_id}
                                            className="flex cursor-pointer items-center gap-3 px-3 py-2 text-sm hover:bg-muted/50"
                                        >
                                            <Checkbox
                                                checked={checked}
                                                onCheckedChange={(v) =>
                                                    toggleStudent(
                                                        s.student_id,
                                                        v === true,
                                                    )
                                                }
                                            />
                                            <span className="flex-1 font-medium text-foreground">
                                                {s.name}
                                            </span>
                                            <span className="font-mono text-xs text-muted-foreground">
                                                {s.nis}
                                            </span>
                                        </label>
                                    );
                                })
                            )}
                        </div>
                        <InputError message={form.errors.student_ids} />
                    </div>

                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div className="space-y-2">
                            <Label htmlFor="record-note">
                                Catatan (opsional)
                            </Label>
                            <Textarea
                                id="record-note"
                                rows={2}
                                value={form.data.note}
                                onChange={(e) =>
                                    form.setData('note', e.target.value)
                                }
                                placeholder="Keterangan tambahan..."
                            />
                            <InputError message={form.errors.note} />
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="record-evidence">
                                Bukti foto privat (opsional)
                            </Label>
                            <Input
                                id="record-evidence"
                                type="file"
                                accept="image/*"
                                onChange={(e) =>
                                    form.setData(
                                        'evidence',
                                        e.target.files?.[0] ?? null,
                                    )
                                }
                            />
                            <InputError message={form.errors.evidence} />
                        </div>
                    </div>
                </div>
            </FormDialog>

            <FormDialog
                open={voidTarget !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setVoidTarget(null);
                    }
                }}
                title="Batalkan catatan perilaku"
                description={
                    voidTarget
                        ? `Membatalkan catatan "${voidTarget.rule_name}" untuk ${voidTarget.student_name} dan memulihkan ${voidTarget.points} poin.`
                        : undefined
                }
                submitLabel="Batalkan catatan"
                onSubmit={submitVoid}
                processing={voidForm.processing}
            >
                <div className="space-y-2">
                    <Label htmlFor="void-reason">Alasan pembatalan</Label>
                    <Textarea
                        id="void-reason"
                        rows={3}
                        value={voidForm.data.void_reason}
                        onChange={(e) =>
                            voidForm.setData('void_reason', e.target.value)
                        }
                        placeholder="Jelaskan alasan pembatalan catatan..."
                        required
                    />
                    <InputError message={voidForm.errors.void_reason} />
                </div>
            </FormDialog>

            <Dialog
                open={evidenceRecord !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setEvidenceRecord(null);
                    }
                }}
            >
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>
                            Bukti foto — {evidenceRecord?.student_name}
                        </DialogTitle>
                    </DialogHeader>
                    {evidenceRecord ? (
                        <img
                            src={BehaviorRecordController.evidence.url(
                                evidenceRecord.id,
                            )}
                            alt={`Bukti catatan ${evidenceRecord.student_name}`}
                            className="max-h-[70vh] w-full rounded-md border border-border object-contain"
                        />
                    ) : null}
                </DialogContent>
            </Dialog>
        </>
    );
}

BehaviorRecordsIndex.layout = (page: ReactNode) => (
    <StaffLayout
        breadcrumbs={[
            { title: 'Kesiswaan', href: '#' },
            {
                title: 'Catatan perilaku',
                href: BehaviorRecordController.index.url(),
            },
        ]}
    >
        {page}
    </StaffLayout>
);
