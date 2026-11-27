import { StudentSearch } from '@/components/app/student-search';
import { Head, router, useForm } from '@inertiajs/react';
import { Check, Paperclip, X } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import LeaveRequestController from '@/actions/App/Http/Controllers/Admin/LeaveRequestController';
import type { DataTableColumn } from '@/components/app/data-table';
import { DataTable } from '@/components/app/data-table';
import { FormDialog } from '@/components/app/form-dialog';
import { PageHeader } from '@/components/app/page-header';
import { StatusBadge } from '@/components/app/status-badge';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
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
import { dashboard } from '@/routes/admin';
import type { PaginatedResource } from '@/types';

type LeaveRow = {
    id: number;
    student_name: string;
    nis: string;
    unit: string;
    classroom: string;
    type_label: string;
    starts_on: string;
    ends_on: string;
    reason: string;
    requester_name: string;
    status: string;
    status_label: string;
    decision_note: string | null;
    decider_name: string | null;
    attachment_url: string | null;
};

type Props = {
    leaves: PaginatedResource<LeaveRow>;
    filters: { status: string; search?: string };
    canManage: boolean;
};

function badgeForStatus(status: string): 'warning' | 'success' | 'destructive' {
    switch (status) {
        case 'approved':
            return 'success';
        case 'rejected':
            return 'destructive';
        default:
            return 'warning';
    }
}

export default function AdminLeavesIndex({
    leaves,
    filters,
    canManage,
}: Props) {
    const [decisionTarget, setDecisionTarget] = useState<{
        leave: LeaveRow;
        approved: boolean;
    } | null>(null);

    const form = useForm<{ decision_note: string }>({
        decision_note: '',
    });

    const changeFilter = (status: string): void => {
        router.get(
            LeaveRequestController.index.url(),
            { status },
            { preserveState: true, preserveScroll: true },
        );
    };

    const submitDecision = (event: FormEvent): void => {
        event.preventDefault();

        if (!decisionTarget) {
            return;
        }

        form.transform((data) => ({
            approved: decisionTarget.approved,
            decision_note: data.decision_note,
        }));

        form.post(LeaveRequestController.decide.url(decisionTarget.leave.id), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setDecisionTarget(null);
            },
        });
    };

    const columns: DataTableColumn<LeaveRow>[] = [
        {
            key: 'student_name',
            label: 'Siswa',
            render: (row) => (
                <div>
                    <p className="font-medium">{row.student_name}</p>
                    <p className="text-xs text-muted-foreground">
                        {row.classroom} · {row.unit} · NIS {row.nis}
                    </p>
                </div>
            ),
        },
        {
            key: 'starts_on',
            label: 'Tanggal & Jenis',
            render: (row) => (
                <div>
                    <p className="font-medium">
                        {row.type_label} · {formatDate(row.starts_on)}
                        {row.ends_on !== row.starts_on
                            ? ` – ${formatDate(row.ends_on)}`
                            : ''}
                    </p>
                    <p className="line-clamp-2 text-xs text-muted-foreground">
                        {row.reason}
                    </p>
                </div>
            ),
        },
        {
            key: 'requester_name',
            label: 'Pengaju',
            render: (row) => row.requester_name,
        },
        {
            key: 'status',
            label: 'Status',
            align: 'center',
            render: (row) => (
                <StatusBadge variant={badgeForStatus(row.status)}>
                    {row.status_label}
                </StatusBadge>
            ),
        },
        {
            key: 'actions',
            label: 'Aksi',
            align: 'center',
            render: (row) => (
                <div className="inline-flex items-center gap-1">
                    {row.attachment_url && (
                        <Tooltip>
                            <TooltipTrigger asChild>
                                <Button
                                    asChild
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    className="size-8"
                                >
                                    <a
                                        href={row.attachment_url}
                                        target="_blank"
                                        rel="noreferrer"
                                        aria-label={`Lihat lampiran ${row.student_name}`}
                                    >
                                        <Paperclip className="size-4" />
                                    </a>
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent>Lihat lampiran</TooltipContent>
                        </Tooltip>
                    )}

                    {canManage && row.status === 'pending' && (
                        <>
                            <Tooltip>
                                <TooltipTrigger asChild>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        className="size-8 text-primary"
                                        aria-label={`Setujui izin ${row.student_name}`}
                                        onClick={() => {
                                            form.reset();
                                            setDecisionTarget({
                                                leave: row,
                                                approved: true,
                                            });
                                        }}
                                    >
                                        <Check className="size-4" />
                                    </Button>
                                </TooltipTrigger>
                                <TooltipContent>Setujui</TooltipContent>
                            </Tooltip>

                            <Tooltip>
                                <TooltipTrigger asChild>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        className="size-8 text-destructive"
                                        aria-label={`Tolak izin ${row.student_name}`}
                                        onClick={() => {
                                            form.reset();
                                            setDecisionTarget({
                                                leave: row,
                                                approved: false,
                                            });
                                        }}
                                    >
                                        <X className="size-4" />
                                    </Button>
                                </TooltipTrigger>
                                <TooltipContent>Tolak</TooltipContent>
                            </Tooltip>
                        </>
                    )}
                </div>
            ),
        },
    ];

    return (
        <StaffLayout
            breadcrumbs={[
                { title: 'Beranda', href: dashboard().url },
                {
                    title: 'Pengajuan izin',
                    href: LeaveRequestController.index.url(),
                },
            ]}
        >
            <Head title="Pengajuan izin & sakit" />

            <div className="space-y-6">
                <PageHeader
                    title="Pengajuan izin & sakit"
                    description="Verifikasi pengajuan izin/sakit dari wali murid."
                    actions={
                        <div className="flex flex-wrap items-center gap-2">
                            <StudentSearch value={filters.search ?? ''} />
                            <Select
                                value={filters.status}
                                onValueChange={changeFilter}
                            >
                                <SelectTrigger className="w-44">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="pending">
                                        Menunggu
                                    </SelectItem>
                                    <SelectItem value="approved">
                                        Disetujui
                                    </SelectItem>
                                    <SelectItem value="rejected">
                                        Ditolak
                                    </SelectItem>
                                    <SelectItem value="all">Semua</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    }
                />

                <DataTable
                    columns={columns}
                    data={leaves.data}
                    rowKey={(row) => row.id}
                    pagination={{
                        currentPage: leaves.meta.current_page,
                        lastPage: leaves.meta.last_page,
                        perPage: leaves.meta.per_page,
                        total: leaves.meta.total,
                        from: leaves.meta.from,
                        to: leaves.meta.to,
                    }}
                    onPageChange={(page) =>
                        router.get(
                            LeaveRequestController.index.url(),
                            { status: filters.status, page },
                            { preserveState: true, preserveScroll: true },
                        )
                    }
                    empty={{
                        title: 'Tidak ada pengajuan izin',
                        description:
                            'Pengajuan izin atau sakit dari wali murid akan tampil di daftar ini.',
                    }}
                />
            </div>

            <FormDialog
                open={decisionTarget !== null}
                onOpenChange={(open) => !open && setDecisionTarget(null)}
                title={
                    decisionTarget?.approved
                        ? 'Setujui pengajuan izin?'
                        : 'Tolak pengajuan izin?'
                }
                description={
                    decisionTarget
                        ? `${decisionTarget.leave.student_name} — ${decisionTarget.leave.type_label} (${formatDate(decisionTarget.leave.starts_on)})`
                        : ''
                }
                submitLabel={decisionTarget?.approved ? 'Setujui' : 'Tolak'}
                processing={form.processing}
                onSubmit={submitDecision}
            >
                <div className="grid gap-2">
                    <Label htmlFor="decision-note">Catatan (opsional)</Label>
                    <textarea
                        id="decision-note"
                        rows={3}
                        value={form.data.decision_note}
                        onChange={(e) =>
                            form.setData('decision_note', e.target.value)
                        }
                        placeholder="Catatan untuk wali murid..."
                        className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    />
                    <InputError message={form.errors.decision_note} />
                </div>
            </FormDialog>
        </StaffLayout>
    );
}
