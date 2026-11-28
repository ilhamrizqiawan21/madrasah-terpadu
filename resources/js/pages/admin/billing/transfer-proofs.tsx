import { Head, router, useForm } from '@inertiajs/react';
import { Check, Eye, X } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { useState } from 'react';
import TransferProofController from '@/actions/App/Http/Controllers/Admin/TransferProofController';
import { ConfirmDialog } from '@/components/app/confirm-dialog';
import type { DataTableColumn } from '@/components/app/data-table';
import { DataTable } from '@/components/app/data-table';
import { DateText } from '@/components/app/date-text';
import { FormDialog } from '@/components/app/form-dialog';
import { MoneyText } from '@/components/app/money-text';
import { PageHeader } from '@/components/app/page-header';
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

type ProofRow = {
    id: number;
    student_name: string;
    student_nis: string;
    unit_code: string;
    invoice_description: string;
    guardian_name: string;
    amount: number;
    notes: string | null;
    status: 'pending' | 'approved' | 'rejected';
    status_label: string;
    rejection_reason: string | null;
    reviewer_name: string | null;
    created_at: string;
};

type Props = {
    proofs: PaginatedResource<ProofRow>;
    statuses: { value: string; label: string }[];
    filters: { status: string };
};

const ALL_VALUE = 'semua';

export default function TransferProofsIndex({
    proofs,
    statuses,
    filters,
}: Props) {
    const [loading, setLoading] = useState(false);
    const [approving, setApproving] = useState<ProofRow | null>(null);
    const [rejecting, setRejecting] = useState<ProofRow | null>(null);
    const [processingApprove, setProcessingApprove] = useState(false);

    const rejectForm = useForm({ reason: '' });

    function visitStatus(status: string, page?: number) {
        router.get(
            TransferProofController.index.url({
                query: {
                    status: status || undefined,
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

    function confirmApprove() {
        if (!approving) {
            return;
        }
        setProcessingApprove(true);
        router.post(
            TransferProofController.approve.url(approving.id),
            {},
            {
                preserveScroll: true,
                onFinish: () => {
                    setProcessingApprove(false);
                    setApproving(null);
                },
            },
        );
    }

    function submitReject(e: FormEvent<HTMLFormElement>) {
        e.preventDefault();
        if (!rejecting) {
            return;
        }
        rejectForm.post(TransferProofController.reject.url(rejecting.id), {
            preserveScroll: true,
            onSuccess: () => {
                setRejecting(null);
                rejectForm.reset();
            },
        });
    }

    const columns: DataTableColumn<ProofRow>[] = [
        {
            key: 'student',
            label: 'Siswa & Wali',
            render: (row) => (
                <div>
                    <div className="font-medium">{row.student_name}</div>
                    <div className="text-xs text-muted-foreground">
                        {row.student_nis} · {row.unit_code} · Wali:{' '}
                        {row.guardian_name}
                    </div>
                </div>
            ),
        },
        {
            key: 'invoice',
            label: 'Tagihan',
            render: (row) => (
                <div>
                    <div className="font-medium">{row.invoice_description}</div>
                    {row.notes && (
                        <div className="text-xs text-muted-foreground">
                            {row.notes}
                        </div>
                    )}
                </div>
            ),
        },
        {
            key: 'amount',
            label: 'Nominal',
            align: 'right',
            render: (row) => (
                <MoneyText amount={row.amount} className="font-medium" />
            ),
        },
        {
            key: 'created_at',
            label: 'Diunggah',
            render: (row) => (
                <DateText value={row.created_at} mode="datetime" />
            ),
        },
        {
            key: 'status',
            label: 'Status',
            align: 'center',
            render: (row) => (
                <div className="space-y-0.5">
                    <StatusBadge
                        variant={
                            row.status === 'approved'
                                ? 'success'
                                : row.status === 'rejected'
                                  ? 'destructive'
                                  : 'warning'
                        }
                    >
                        {row.status_label}
                    </StatusBadge>
                    {row.rejection_reason && (
                        <div className="text-xs text-destructive">
                            {row.rejection_reason}
                        </div>
                    )}
                </div>
            ),
        },
        {
            key: 'actions',
            label: 'Aksi',
            align: 'center',
            render: (row) => (
                <div className="inline-flex items-center justify-center gap-1">
                    <Tooltip>
                        <TooltipTrigger asChild>
                            <Button
                                variant="ghost"
                                size="icon"
                                asChild
                                aria-label={`Lihat bukti ${row.student_name}`}
                            >
                                <a
                                    href={TransferProofController.file.url(
                                        row.id,
                                    )}
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    <Eye
                                        className="size-4"
                                        strokeWidth={1.75}
                                    />
                                </a>
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent>Lihat foto bukti</TooltipContent>
                    </Tooltip>

                    {row.status === 'pending' && (
                        <>
                            <Tooltip>
                                <TooltipTrigger asChild>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        aria-label={`Setujui bukti ${row.student_name}`}
                                        onClick={() => setApproving(row)}
                                    >
                                        <Check
                                            className="size-4 text-primary"
                                            strokeWidth={1.75}
                                        />
                                    </Button>
                                </TooltipTrigger>
                                <TooltipContent>
                                    Setujui &amp; catat pembayaran
                                </TooltipContent>
                            </Tooltip>

                            <Tooltip>
                                <TooltipTrigger asChild>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        aria-label={`Tolak bukti ${row.student_name}`}
                                        onClick={() => {
                                            rejectForm.clearErrors();
                                            rejectForm.reset();
                                            setRejecting(row);
                                        }}
                                    >
                                        <X
                                            className="size-4 text-destructive"
                                            strokeWidth={1.75}
                                        />
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
                {
                    title: 'Keuangan Siswa',
                    href: TransferProofController.index.url(),
                },
                {
                    title: 'Bukti transfer',
                    href: TransferProofController.index.url(),
                },
            ]}
        >
            <Head title="Bukti transfer" />

            <div className="space-y-6">
                <PageHeader
                    title="Verifikasi bukti transfer"
                    description="Periksa foto bukti transfer dari wali murid lalu setujui menjadi pembayaran atau tolak dengan alasan."
                />

                <div className="flex items-center gap-3">
                    <Select
                        value={filters.status || ALL_VALUE}
                        onValueChange={(v) =>
                            visitStatus(v === ALL_VALUE ? '' : v, 1)
                        }
                    >
                        <SelectTrigger
                            className="w-48"
                            aria-label="Filter status bukti transfer"
                        >
                            <SelectValue placeholder="Semua status" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL_VALUE}>
                                Semua status
                            </SelectItem>
                            {statuses.map((s) => (
                                <SelectItem key={s.value} value={s.value}>
                                    {s.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                <DataTable
                    columns={columns}
                    data={proofs.data}
                    rowKey={(row) => row.id}
                    loading={loading}
                    pagination={{
                        currentPage: proofs.meta.current_page,
                        lastPage: proofs.meta.last_page,
                        perPage: proofs.meta.per_page,
                        total: proofs.meta.total,
                        from: proofs.meta.from,
                        to: proofs.meta.to,
                    }}
                    onPageChange={(page) => visitStatus(filters.status, page)}
                    empty={{
                        title: 'Belum ada bukti transfer',
                        description:
                            'Bukti transfer yang diunggah wali murid akan muncul di antrean ini.',
                    }}
                />
            </div>

            <ConfirmDialog
                open={approving !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setApproving(null);
                    }
                }}
                title="Setujui bukti transfer ini?"
                description={
                    approving
                        ? `Pembayaran transfer sebesar Rp ${approving.amount.toLocaleString('id-ID')} untuk ${approving.student_name} (${approving.invoice_description}) akan dicatat dan kuitansi diterbitkan.`
                        : ''
                }
                confirmLabel="Ya, setujui"
                loading={processingApprove}
                onConfirm={confirmApprove}
            />

            <FormDialog
                open={rejecting !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setRejecting(null);
                    }
                }}
                title="Tolak bukti transfer?"
                description="Berikan alasan penolakan yang jelas agar wali dapat mengunggah ulang."
                onSubmit={submitReject}
                processing={rejectForm.processing}
                submitLabel="Tolak bukti"
            >
                <div className="space-y-1.5">
                    <Label htmlFor="reject-reason">Alasan penolakan</Label>
                    <Input
                        id="reject-reason"
                        value={rejectForm.data.reason}
                        onChange={(e) =>
                            rejectForm.setData('reason', e.target.value)
                        }
                        placeholder="Mis. Nominal tidak sesuai atau foto buram"
                        maxLength={255}
                        required
                    />
                    <InputError message={rejectForm.errors.reason} />
                </div>
            </FormDialog>
        </StaffLayout>
    );
}

TransferProofsIndex.layout = (page: ReactNode) => page;
