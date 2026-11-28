import { StudentSearch } from '@/components/app/student-search';
import { Head, router, useForm } from '@inertiajs/react';
import { CheckCircle2, Plus, Power, PowerOff } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { useState } from 'react';
import DisciplineController from '@/actions/App/Http/Controllers/Admin/DisciplineController';
import type { DataTableColumn } from '@/components/app/data-table';
import { DataTable } from '@/components/app/data-table';
import { DateText } from '@/components/app/date-text';
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
import { Textarea } from '@/components/ui/textarea';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import StaffLayout from '@/layouts/staff-layout';
import type { PaginatedResource } from '@/types';

type FollowupRow = {
    id: number;
    student_name: string;
    student_nis: string;
    threshold_points: number;
    action_name: string;
    notify_guardian: boolean;
    triggered_at: string;
    status: 'open' | 'done';
    note: string | null;
    done_by_name: string | null;
};

type ThresholdRow = {
    id: number;
    unit_id: number | null;
    unit_name: string;
    points: number;
    action_name: string;
    notify_guardian: boolean;
    is_active: boolean;
};

type UnitOption = { id: number; code: string; name: string };

type Props = {
    followups: PaginatedResource<FollowupRow>;
    thresholds: ThresholdRow[];
    units: UnitOption[];
    filters: { status: string; search?: string };
    canManageThresholds: boolean;
    canCompleteFollowups: boolean;
};

type ThresholdForm = {
    unit_id: string;
    points: string;
    action_name: string;
    notify_guardian: boolean;
};

const ALL_UNITS = 'semua';

export default function DisciplineIndex({
    followups,
    thresholds,
    units,
    filters,
    canManageThresholds,
    canCompleteFollowups,
}: Props) {
    const [loading, setLoading] = useState(false);
    const [doneTarget, setDoneTarget] = useState<FollowupRow | null>(null);
    const [thresholdDialogOpen, setThresholdDialogOpen] = useState(false);
    const [editingThreshold, setEditingThreshold] =
        useState<ThresholdRow | null>(null);

    const doneForm = useForm<{ note: string }>({ note: '' });
    const thresholdForm = useForm<ThresholdForm>({
        unit_id: ALL_UNITS,
        points: '15',
        action_name: '',
        notify_guardian: true,
    });

    function visitStatus(status: string, page?: number) {
        router.get(
            DisciplineController.index.url({
                query: {
                    status,
                    page: page && page > 1 ? page : undefined,
                },
            }),
            {},
            {
                preserveState: true,
                replace: true,
                onStart: () => setLoading(true),
                onFinish: () => setLoading(false),
            },
        );
    }

    function openCreateThreshold() {
        setEditingThreshold(null);
        thresholdForm.setData({
            unit_id: ALL_UNITS,
            points: '15',
            action_name: '',
            notify_guardian: true,
        });
        thresholdForm.clearErrors();
        setThresholdDialogOpen(true);
    }

    function openEditThreshold(row: ThresholdRow) {
        setEditingThreshold(row);
        thresholdForm.setData({
            unit_id: row.unit_id !== null ? String(row.unit_id) : ALL_UNITS,
            points: String(row.points),
            action_name: row.action_name,
            notify_guardian: row.notify_guardian,
        });
        thresholdForm.clearErrors();
        setThresholdDialogOpen(true);
    }

    function submitThreshold(e: FormEvent<HTMLFormElement>) {
        e.preventDefault();
        const payload = {
            unit_id:
                thresholdForm.data.unit_id === ALL_UNITS
                    ? null
                    : Number(thresholdForm.data.unit_id),
            points: Number(thresholdForm.data.points),
            action_name: thresholdForm.data.action_name,
            notify_guardian: thresholdForm.data.notify_guardian,
        };

        if (editingThreshold) {
            router.put(
                DisciplineController.updateThreshold.url(editingThreshold.id),
                payload,
                {
                    preserveScroll: true,
                    onSuccess: () => setThresholdDialogOpen(false),
                    onError: (errors) =>
                        thresholdForm.setError(
                            errors as Record<keyof ThresholdForm, string>,
                        ),
                },
            );
            return;
        }

        router.post(DisciplineController.storeThreshold.url(), payload, {
            preserveScroll: true,
            onSuccess: () => setThresholdDialogOpen(false),
            onError: (errors) =>
                thresholdForm.setError(
                    errors as Record<keyof ThresholdForm, string>,
                ),
        });
    }

    function toggleThreshold(row: ThresholdRow) {
        router.patch(
            DisciplineController.toggleThreshold.url(row.id),
            { is_active: !row.is_active },
            { preserveScroll: true },
        );
    }

    function submitDone(e: FormEvent<HTMLFormElement>) {
        e.preventDefault();
        if (!doneTarget) {
            return;
        }

        router.post(
            DisciplineController.completeFollowup.url(doneTarget.id),
            { note: doneForm.data.note },
            {
                preserveScroll: true,
                onSuccess: () => {
                    doneForm.reset();
                    setDoneTarget(null);
                },
                onError: (errors) =>
                    doneForm.setError(errors as Record<'note', string>),
            },
        );
    }

    const followupColumns: DataTableColumn<FollowupRow>[] = [
        {
            key: 'triggered_at',
            label: 'Dipicu',
            render: (row) => <DateText value={row.triggered_at} />,
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
            key: 'action_name',
            label: 'Tindak lanjut',
            render: (row) => (
                <div>
                    <div className="font-medium text-foreground">
                        {row.action_name}
                    </div>
                    <div className="text-xs text-muted-foreground">
                        Ambang {row.threshold_points} poin pelanggaran
                    </div>
                    {row.note ? (
                        <div className="mt-1 text-xs text-muted-foreground">
                            Catatan: {row.note}
                        </div>
                    ) : null}
                </div>
            ),
        },
        {
            key: 'status',
            label: 'Status',
            align: 'center',
            render: (row) => (
                <StatusBadge
                    variant={row.status === 'open' ? 'attention' : 'success'}
                >
                    {row.status === 'open' ? 'Terbuka' : 'Selesai'}
                </StatusBadge>
            ),
        },
        {
            key: 'actions',
            label: 'Aksi',
            align: 'center',
            render: (row) =>
                canCompleteFollowups && row.status === 'open' ? (
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() => {
                            doneForm.reset();
                            doneForm.clearErrors();
                            setDoneTarget(row);
                        }}
                    >
                        <CheckCircle2 className="mr-1.5 h-3.5 w-3.5" />
                        Selesaikan
                    </Button>
                ) : (
                    <span className="text-xs text-muted-foreground">
                        {row.done_by_name ?? '—'}
                    </span>
                ),
        },
    ];

    const thresholdColumns: DataTableColumn<ThresholdRow>[] = [
        {
            key: 'points',
            label: 'Ambang poin',
            align: 'right',
            render: (row) => (
                <span className="font-medium tabular-nums">{row.points}</span>
            ),
        },
        {
            key: 'action_name',
            label: 'Tindakan',
            render: (row) => (
                <span className="font-medium text-foreground">
                    {row.action_name}
                </span>
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
            key: 'notify_guardian',
            label: 'Notifikasi wali',
            align: 'center',
            render: (row) => (
                <StatusBadge
                    variant={row.notify_guardian ? 'default' : 'muted'}
                >
                    {row.notify_guardian ? 'Ya' : 'Tidak'}
                </StatusBadge>
            ),
        },
        {
            key: 'is_active',
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
                canManageThresholds ? (
                    <RowActions
                        onEdit={() => openEditThreshold(row)}
                        itemName={row.action_name}
                    >
                        <Tooltip>
                            <TooltipTrigger asChild>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    aria-label={
                                        row.is_active
                                            ? `Nonaktifkan ${row.action_name}`
                                            : `Aktifkan ${row.action_name}`
                                    }
                                    onClick={() => toggleThreshold(row)}
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
                                    ? 'Nonaktifkan ambang'
                                    : 'Aktifkan ambang'}
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
            <Head title="Tindak lanjut & ambang disiplin" />
            <div className="space-y-8">
                <section className="space-y-4">
                    <PageHeader
                        title="Tindak lanjut kedisiplinan"
                        description="Daftar siswa yang melewati ambang poin pelanggaran pada tahun ajaran berjalan."
                        actions={
                            <div className="flex flex-wrap items-center gap-2">
                                <StudentSearch value={filters.search ?? ''} />
                                <Select
                                    value={filters.status}
                                    onValueChange={(v) => visitStatus(v)}
                                >
                                    <SelectTrigger
                                        className="w-[160px]"
                                        aria-label="Filter status tindak lanjut"
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="open">
                                            Terbuka
                                        </SelectItem>
                                        <SelectItem value="done">
                                            Selesai
                                        </SelectItem>
                                        <SelectItem value="all">
                                            Semua status
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        }
                    />

                    <DataTable
                        columns={followupColumns}
                        data={followups.data}
                        rowKey={(row) => row.id}
                        loading={loading}
                        pagination={{
                            currentPage: followups.meta.current_page,
                            lastPage: followups.meta.last_page,
                            perPage: followups.meta.per_page,
                            total: followups.meta.total,
                            from: followups.meta.from,
                            to: followups.meta.to,
                        }}
                        onPageChange={(page) =>
                            visitStatus(filters.status, page)
                        }
                        empty={{
                            title: 'Tidak ada tindak lanjut',
                            description:
                                'Belum ada siswa yang mencapai ambang batas pelanggaran pada filter ini.',
                        }}
                    />
                </section>

                <section className="space-y-4 border-t border-border pt-6">
                    <PageHeader
                        title="Master ambang batas"
                        description="Aturan ambang poin pelanggaran yang otomatis memicu tindak lanjut BK."
                        actions={
                            canManageThresholds ? (
                                <Button
                                    type="button"
                                    onClick={openCreateThreshold}
                                >
                                    <Plus className="mr-2 h-4 w-4" />
                                    Tambah ambang
                                </Button>
                            ) : null
                        }
                    />

                    <DataTable
                        columns={thresholdColumns}
                        data={thresholds}
                        rowKey={(row) => row.id}
                        empty={{
                            title: 'Belum ada ambang batas',
                            description:
                                'Tambahkan ambang poin pelanggaran seperti Teguran Lisan atau SP1.',
                            action: canManageThresholds ? (
                                <Button
                                    type="button"
                                    onClick={openCreateThreshold}
                                >
                                    <Plus className="mr-2 h-4 w-4" />
                                    Tambah ambang
                                </Button>
                            ) : undefined,
                        }}
                    />
                </section>
            </div>

            <FormDialog
                open={doneTarget !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setDoneTarget(null);
                    }
                }}
                title="Selesaikan tindak lanjut"
                description={
                    doneTarget
                        ? `${doneTarget.student_name} — ${doneTarget.action_name}`
                        : undefined
                }
                submitLabel="Tandai selesai"
                onSubmit={submitDone}
                processing={doneForm.processing}
            >
                <div className="space-y-2">
                    <Label htmlFor="followup-note">Catatan tindak lanjut</Label>
                    <Textarea
                        id="followup-note"
                        rows={3}
                        value={doneForm.data.note}
                        onChange={(e) =>
                            doneForm.setData('note', e.target.value)
                        }
                        placeholder="Ringkasan pembinaan atau pertemuan dengan wali..."
                        required
                    />
                    <InputError message={doneForm.errors.note} />
                </div>
            </FormDialog>

            <FormDialog
                open={thresholdDialogOpen}
                onOpenChange={setThresholdDialogOpen}
                title={
                    editingThreshold
                        ? 'Ubah ambang batas'
                        : 'Tambah ambang batas'
                }
                onSubmit={submitThreshold}
                processing={thresholdForm.processing}
            >
                <div className="space-y-4">
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div className="space-y-2">
                            <Label htmlFor="th-unit">Unit</Label>
                            <Select
                                value={thresholdForm.data.unit_id}
                                onValueChange={(v) =>
                                    thresholdForm.setData('unit_id', v)
                                }
                            >
                                <SelectTrigger id="th-unit">
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
                            <InputError
                                message={thresholdForm.errors.unit_id}
                            />
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="th-points">
                                Ambang poin pelanggaran
                            </Label>
                            <Input
                                id="th-points"
                                type="number"
                                min={1}
                                max={5000}
                                value={thresholdForm.data.points}
                                onChange={(e) =>
                                    thresholdForm.setData(
                                        'points',
                                        e.target.value,
                                    )
                                }
                                required
                            />
                            <InputError message={thresholdForm.errors.points} />
                        </div>
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="th-action">Nama tindakan</Label>
                        <Input
                            id="th-action"
                            value={thresholdForm.data.action_name}
                            onChange={(e) =>
                                thresholdForm.setData(
                                    'action_name',
                                    e.target.value,
                                )
                            }
                            placeholder="Contoh: Teguran lisan / Surat Peringatan 1"
                            required
                        />
                        <InputError
                            message={thresholdForm.errors.action_name}
                        />
                    </div>

                    <label className="flex cursor-pointer items-center gap-2.5 text-sm">
                        <Checkbox
                            checked={thresholdForm.data.notify_guardian}
                            onCheckedChange={(v) =>
                                thresholdForm.setData(
                                    'notify_guardian',
                                    v === true,
                                )
                            }
                        />
                        <span>
                            Kirim notifikasi ke wali saat ambang tercapai
                        </span>
                    </label>
                </div>
            </FormDialog>
        </>
    );
}

DisciplineIndex.layout = (page: ReactNode) => (
    <StaffLayout
        breadcrumbs={[
            { title: 'Kesiswaan & Asrama', href: '#' },
            {
                title: 'Tindak lanjut & ambang',
                href: DisciplineController.index.url(),
            },
        ]}
    >
        {page}
    </StaffLayout>
);
