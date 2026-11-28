import { StudentSearch } from '@/components/app/student-search';
import { Head, router, useForm } from '@inertiajs/react';
import { Check, Plus, Power, PowerOff, X } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { useState } from 'react';
import RewardController from '@/actions/App/Http/Controllers/Admin/RewardController';
import type { DataTableColumn } from '@/components/app/data-table';
import { DataTable } from '@/components/app/data-table';
import { DateText } from '@/components/app/date-text';
import { FormDialog } from '@/components/app/form-dialog';
import { MoneyText } from '@/components/app/money-text';
import { PageHeader } from '@/components/app/page-header';
import { RowActions } from '@/components/app/row-actions';
import type { StatusBadgeVariant } from '@/components/app/status-badge';
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
import { Textarea } from '@/components/ui/textarea';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import StaffLayout from '@/layouts/staff-layout';
import type { PaginatedResource } from '@/types';

type RedemptionRow = {
    id: number;
    student_name: string;
    student_nis: string;
    offer_name: string;
    cost_points: number;
    voucher_amount: number;
    requester_name: string;
    status: 'pending' | 'approved' | 'rejected' | 'cancelled';
    decision_note: string | null;
    decider_name: string | null;
    voucher_expires_on: string | null;
    created_at: string;
};

type OfferRow = {
    id: number;
    unit_id: number | null;
    unit_name: string;
    name: string;
    cost_points: number;
    voucher_amount: number;
    voucher_valid_days: number;
    stock: number | null;
    is_active: boolean;
};

type StudentOption = { id: number; label: string };
type UnitOption = { id: number; code: string; name: string };

type Props = {
    redemptions: PaginatedResource<RedemptionRow>;
    offers: OfferRow[];
    students: StudentOption[];
    units: UnitOption[];
    filters: { status: string; search?: string };
    canApprove: boolean;
    canRequest: boolean;
};

type OfferForm = {
    unit_id: string;
    name: string;
    cost_points: string;
    voucher_amount: string;
    voucher_valid_days: string;
    stock: string;
};

const ALL_UNITS = 'semua';

function statusMeta(status: RedemptionRow['status']): {
    label: string;
    variant: StatusBadgeVariant;
} {
    if (status === 'approved') {
        return { label: 'Disetujui', variant: 'success' };
    }
    if (status === 'rejected') {
        return { label: 'Ditolak', variant: 'destructive' };
    }
    if (status === 'cancelled') {
        return { label: 'Dibatalkan', variant: 'muted' };
    }

    return { label: 'Menunggu', variant: 'attention' };
}

export default function RewardsIndex({
    redemptions,
    offers,
    students,
    units,
    filters,
    canApprove,
    canRequest,
}: Props) {
    const [loading, setLoading] = useState(false);
    const [offerOpen, setOfferOpen] = useState(false);
    const [editingOffer, setEditingOffer] = useState<OfferRow | null>(null);
    const [requestOpen, setRequestOpen] = useState(false);
    const [decisionTarget, setDecisionTarget] = useState<{
        row: RedemptionRow;
        mode: 'approve' | 'reject';
    } | null>(null);

    const offerForm = useForm<OfferForm>({
        unit_id: ALL_UNITS,
        name: '',
        cost_points: '25',
        voucher_amount: '10000',
        voucher_valid_days: '14',
        stock: '',
    });

    const requestForm = useForm<{
        student_id: string;
        reward_offer_id: string;
    }>({
        student_id: students[0] ? String(students[0].id) : '',
        reward_offer_id: offers.find((o) => o.is_active)?.id.toString() ?? '',
    });

    const decisionForm = useForm<{ decision_note: string }>({
        decision_note: '',
    });

    function visitStatus(status: string, page?: number) {
        router.get(
            RewardController.index.url({
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

    function openCreateOffer() {
        setEditingOffer(null);
        offerForm.setData({
            unit_id: ALL_UNITS,
            name: '',
            cost_points: '25',
            voucher_amount: '10000',
            voucher_valid_days: '14',
            stock: '',
        });
        offerForm.clearErrors();
        setOfferOpen(true);
    }

    function openEditOffer(row: OfferRow) {
        setEditingOffer(row);
        offerForm.setData({
            unit_id: row.unit_id !== null ? String(row.unit_id) : ALL_UNITS,
            name: row.name,
            cost_points: String(row.cost_points),
            voucher_amount: String(row.voucher_amount),
            voucher_valid_days: String(row.voucher_valid_days),
            stock: row.stock !== null ? String(row.stock) : '',
        });
        offerForm.clearErrors();
        setOfferOpen(true);
    }

    function submitOffer(e: FormEvent<HTMLFormElement>) {
        e.preventDefault();
        const payload = {
            unit_id:
                offerForm.data.unit_id === ALL_UNITS
                    ? null
                    : Number(offerForm.data.unit_id),
            name: offerForm.data.name,
            cost_points: Number(offerForm.data.cost_points),
            voucher_amount: Number(offerForm.data.voucher_amount),
            voucher_valid_days: Number(offerForm.data.voucher_valid_days),
            stock:
                offerForm.data.stock !== ''
                    ? Number(offerForm.data.stock)
                    : null,
        };

        if (editingOffer) {
            router.put(
                RewardController.updateOffer.url(editingOffer.id),
                payload,
                {
                    preserveScroll: true,
                    onSuccess: () => setOfferOpen(false),
                    onError: (errors) =>
                        offerForm.setError(
                            errors as Record<keyof OfferForm, string>,
                        ),
                },
            );
            return;
        }

        router.post(RewardController.storeOffer.url(), payload, {
            preserveScroll: true,
            onSuccess: () => setOfferOpen(false),
            onError: (errors) =>
                offerForm.setError(errors as Record<keyof OfferForm, string>),
        });
    }

    function toggleOffer(row: OfferRow) {
        router.patch(
            RewardController.toggleOffer.url(row.id),
            { is_active: !row.is_active },
            { preserveScroll: true },
        );
    }

    function submitRequest(e: FormEvent<HTMLFormElement>) {
        e.preventDefault();
        router.post(
            RewardController.requestRedemption.url(),
            {
                student_id: Number(requestForm.data.student_id),
                reward_offer_id: Number(requestForm.data.reward_offer_id),
            },
            {
                preserveScroll: true,
                onSuccess: () => setRequestOpen(false),
                onError: (errors) =>
                    requestForm.setError(
                        errors as Record<
                            'student_id' | 'reward_offer_id',
                            string
                        >,
                    ),
            },
        );
    }

    function submitDecision(e: FormEvent<HTMLFormElement>) {
        e.preventDefault();
        if (!decisionTarget) {
            return;
        }

        const url =
            decisionTarget.mode === 'approve'
                ? RewardController.approve.url(decisionTarget.row.id)
                : RewardController.reject.url(decisionTarget.row.id);

        router.post(
            url,
            { decision_note: decisionForm.data.decision_note },
            {
                preserveScroll: true,
                onSuccess: () => {
                    decisionForm.reset();
                    setDecisionTarget(null);
                },
                onError: (errors) =>
                    decisionForm.setError(
                        errors as Record<'decision_note', string>,
                    ),
            },
        );
    }

    const redemptionColumns: DataTableColumn<RedemptionRow>[] = [
        {
            key: 'created_at',
            label: 'Diajukan',
            render: (row) => <DateText value={row.created_at} />,
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
            key: 'offer',
            label: 'Penawaran',
            render: (row) => (
                <div>
                    <div className="font-medium text-foreground">
                        {row.offer_name}
                    </div>
                    <div className="text-xs text-muted-foreground">
                        {row.cost_points} poin →{' '}
                        <MoneyText amount={row.voucher_amount} />
                    </div>
                    {row.decision_note ? (
                        <div className="mt-1 text-xs text-muted-foreground">
                            Catatan: {row.decision_note}
                        </div>
                    ) : null}
                </div>
            ),
        },
        {
            key: 'status',
            label: 'Status',
            align: 'center',
            render: (row) => {
                const meta = statusMeta(row.status);

                return (
                    <StatusBadge variant={meta.variant}>
                        {meta.label}
                    </StatusBadge>
                );
            },
        },
        {
            key: 'actions',
            label: 'Aksi',
            align: 'center',
            render: (row) =>
                canApprove && row.status === 'pending' ? (
                    <div className="flex items-center justify-center gap-1">
                        <Tooltip>
                            <TooltipTrigger asChild>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    aria-label={`Setujui penukaran ${row.student_name}`}
                                    onClick={() => {
                                        decisionForm.reset();
                                        decisionForm.clearErrors();
                                        setDecisionTarget({
                                            row,
                                            mode: 'approve',
                                        });
                                    }}
                                >
                                    <Check className="h-4 w-4 text-primary" />
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent>
                                Setujui & terbitkan voucher
                            </TooltipContent>
                        </Tooltip>
                        <Tooltip>
                            <TooltipTrigger asChild>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    aria-label={`Tolak penukaran ${row.student_name}`}
                                    onClick={() => {
                                        decisionForm.reset();
                                        decisionForm.clearErrors();
                                        setDecisionTarget({
                                            row,
                                            mode: 'reject',
                                        });
                                    }}
                                >
                                    <X className="h-4 w-4 text-destructive" />
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent>Tolak pengajuan</TooltipContent>
                        </Tooltip>
                    </div>
                ) : (
                    <span className="text-xs text-muted-foreground">
                        {row.voucher_expires_on ? (
                            <>
                                Berlaku s.d.{' '}
                                <DateText value={row.voucher_expires_on} />
                            </>
                        ) : (
                            (row.decider_name ?? '—')
                        )}
                    </span>
                ),
        },
    ];

    const offerColumns: DataTableColumn<OfferRow>[] = [
        {
            key: 'name',
            label: 'Penawaran',
            render: (row) => (
                <div>
                    <div className="font-medium text-foreground">
                        {row.name}
                    </div>
                    <div className="text-xs text-muted-foreground">
                        {row.unit_name}
                    </div>
                </div>
            ),
        },
        {
            key: 'cost_points',
            label: 'Biaya poin',
            align: 'right',
            render: (row) => (
                <span className="font-medium tabular-nums">
                    {row.cost_points}
                </span>
            ),
        },
        {
            key: 'voucher_amount',
            label: 'Nilai voucher',
            align: 'right',
            render: (row) => <MoneyText amount={row.voucher_amount} />,
        },
        {
            key: 'valid_days',
            label: 'Masa berlaku',
            align: 'right',
            render: (row) => (
                <span className="tabular-nums">
                    {row.voucher_valid_days} hari
                </span>
            ),
        },
        {
            key: 'stock',
            label: 'Stok',
            align: 'right',
            render: (row) => (
                <span className="tabular-nums">
                    {row.stock === null ? 'Tanpa batas' : row.stock}
                </span>
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
                canApprove ? (
                    <RowActions
                        onEdit={() => openEditOffer(row)}
                        itemName={row.name}
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
                                    onClick={() => toggleOffer(row)}
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
                                    ? 'Nonaktifkan penawaran'
                                    : 'Aktifkan penawaran'}
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
            <Head title="Reward & voucher kantin" />
            <div className="space-y-8">
                <section className="space-y-4">
                    <PageHeader
                        title="Penukaran reward"
                        description="Pengajuan penukaran poin kebaikan siswa menjadi voucher kantin."
                        actions={
                            <div className="flex flex-wrap items-center gap-2">
                                <StudentSearch value={filters.search ?? ''} />
                                <Select
                                    value={filters.status}
                                    onValueChange={(v) => visitStatus(v)}
                                >
                                    <SelectTrigger
                                        className="w-[160px]"
                                        aria-label="Filter status penukaran"
                                    >
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
                                        <SelectItem value="all">
                                            Semua status
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                {canRequest ? (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() => {
                                            requestForm.clearErrors();
                                            setRequestOpen(true);
                                        }}
                                    >
                                        <Plus className="mr-2 h-4 w-4" />
                                        Ajukan penukaran
                                    </Button>
                                ) : null}
                            </div>
                        }
                    />

                    <DataTable
                        columns={redemptionColumns}
                        data={redemptions.data}
                        rowKey={(row) => row.id}
                        loading={loading}
                        pagination={{
                            currentPage: redemptions.meta.current_page,
                            lastPage: redemptions.meta.last_page,
                            perPage: redemptions.meta.per_page,
                            total: redemptions.meta.total,
                            from: redemptions.meta.from,
                            to: redemptions.meta.to,
                        }}
                        onPageChange={(page) =>
                            visitStatus(filters.status, page)
                        }
                        empty={{
                            title: 'Belum ada pengajuan penukaran',
                            description:
                                'Pengajuan penukaran poin dari wali atau BK akan muncul di sini.',
                        }}
                    />
                </section>

                <section className="space-y-4 border-t border-border pt-6">
                    <PageHeader
                        title="Master penawaran reward"
                        description="Daftar voucher kantin yang dapat ditukar dengan poin kebaikan siswa."
                        actions={
                            canApprove ? (
                                <Button type="button" onClick={openCreateOffer}>
                                    <Plus className="mr-2 h-4 w-4" />
                                    Tambah penawaran
                                </Button>
                            ) : null
                        }
                    />

                    <DataTable
                        columns={offerColumns}
                        data={offers}
                        rowKey={(row) => row.id}
                        empty={{
                            title: 'Belum ada penawaran reward',
                            description:
                                'Buat penawaran voucher kantin agar siswa termotivasi mengumpulkan poin kebaikan.',
                            action: canApprove ? (
                                <Button type="button" onClick={openCreateOffer}>
                                    <Plus className="mr-2 h-4 w-4" />
                                    Tambah penawaran
                                </Button>
                            ) : undefined,
                        }}
                    />
                </section>
            </div>

            <FormDialog
                open={offerOpen}
                onOpenChange={setOfferOpen}
                title={
                    editingOffer
                        ? 'Ubah penawaran reward'
                        : 'Tambah penawaran reward'
                }
                onSubmit={submitOffer}
                processing={offerForm.processing}
            >
                <div className="space-y-4">
                    <div className="space-y-2">
                        <Label htmlFor="offer-unit">Unit</Label>
                        <Select
                            value={offerForm.data.unit_id}
                            onValueChange={(v) =>
                                offerForm.setData('unit_id', v)
                            }
                        >
                            <SelectTrigger id="offer-unit">
                                <SelectValue placeholder="Semua unit" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={ALL_UNITS}>
                                    Semua unit
                                </SelectItem>
                                {units.map((u) => (
                                    <SelectItem key={u.id} value={String(u.id)}>
                                        {u.code} — {u.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={offerForm.errors.unit_id} />
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="offer-name">Nama penawaran</Label>
                        <Input
                            id="offer-name"
                            value={offerForm.data.name}
                            onChange={(e) =>
                                offerForm.setData('name', e.target.value)
                            }
                            placeholder="Contoh: Voucher Jajan Kantin Rp 10.000"
                            required
                        />
                        <InputError message={offerForm.errors.name} />
                    </div>

                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div className="space-y-2">
                            <Label htmlFor="offer-cost">Biaya poin</Label>
                            <Input
                                id="offer-cost"
                                type="number"
                                min={1}
                                value={offerForm.data.cost_points}
                                onChange={(e) =>
                                    offerForm.setData(
                                        'cost_points',
                                        e.target.value,
                                    )
                                }
                                required
                            />
                            <InputError
                                message={offerForm.errors.cost_points}
                            />
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="offer-amount">
                                Nilai voucher (Rp)
                            </Label>
                            <Input
                                id="offer-amount"
                                type="number"
                                min={1000}
                                step={500}
                                value={offerForm.data.voucher_amount}
                                onChange={(e) =>
                                    offerForm.setData(
                                        'voucher_amount',
                                        e.target.value,
                                    )
                                }
                                required
                            />
                            <InputError
                                message={offerForm.errors.voucher_amount}
                            />
                        </div>
                    </div>

                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div className="space-y-2">
                            <Label htmlFor="offer-days">
                                Masa berlaku (hari)
                            </Label>
                            <Input
                                id="offer-days"
                                type="number"
                                min={1}
                                max={365}
                                value={offerForm.data.voucher_valid_days}
                                onChange={(e) =>
                                    offerForm.setData(
                                        'voucher_valid_days',
                                        e.target.value,
                                    )
                                }
                                required
                            />
                            <InputError
                                message={offerForm.errors.voucher_valid_days}
                            />
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="offer-stock">
                                Stok (kosongkan = tanpa batas)
                            </Label>
                            <Input
                                id="offer-stock"
                                type="number"
                                min={0}
                                value={offerForm.data.stock}
                                onChange={(e) =>
                                    offerForm.setData('stock', e.target.value)
                                }
                                placeholder="Tanpa batas"
                            />
                            <InputError message={offerForm.errors.stock} />
                        </div>
                    </div>
                </div>
            </FormDialog>

            <FormDialog
                open={requestOpen}
                onOpenChange={setRequestOpen}
                title="Ajukan penukaran reward"
                submitLabel="Ajukan"
                onSubmit={submitRequest}
                processing={requestForm.processing}
            >
                <div className="space-y-4">
                    <div className="space-y-2">
                        <Label htmlFor="req-student">Siswa</Label>
                        <Select
                            value={requestForm.data.student_id}
                            onValueChange={(v) =>
                                requestForm.setData('student_id', v)
                            }
                        >
                            <SelectTrigger id="req-student">
                                <SelectValue placeholder="Pilih siswa" />
                            </SelectTrigger>
                            <SelectContent>
                                {students.map((s) => (
                                    <SelectItem key={s.id} value={String(s.id)}>
                                        {s.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={requestForm.errors.student_id} />
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="req-offer">Penawaran reward</Label>
                        <Select
                            value={requestForm.data.reward_offer_id}
                            onValueChange={(v) =>
                                requestForm.setData('reward_offer_id', v)
                            }
                        >
                            <SelectTrigger id="req-offer">
                                <SelectValue placeholder="Pilih penawaran" />
                            </SelectTrigger>
                            <SelectContent>
                                {offers
                                    .filter((o) => o.is_active)
                                    .map((o) => (
                                        <SelectItem
                                            key={o.id}
                                            value={String(o.id)}
                                        >
                                            {o.name} ({o.cost_points} poin)
                                        </SelectItem>
                                    ))}
                            </SelectContent>
                        </Select>
                        <InputError
                            message={requestForm.errors.reward_offer_id}
                        />
                    </div>
                </div>
            </FormDialog>

            <FormDialog
                open={decisionTarget !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setDecisionTarget(null);
                    }
                }}
                title={
                    decisionTarget?.mode === 'approve'
                        ? 'Setujui penukaran reward'
                        : 'Tolak pengajuan reward'
                }
                description={
                    decisionTarget
                        ? `${decisionTarget.row.student_name} — ${decisionTarget.row.offer_name} (${decisionTarget.row.cost_points} poin)`
                        : undefined
                }
                submitLabel={
                    decisionTarget?.mode === 'approve'
                        ? 'Setujui & terbitkan'
                        : 'Tolak pengajuan'
                }
                onSubmit={submitDecision}
                processing={decisionForm.processing}
            >
                <div className="space-y-2">
                    <Label htmlFor="decision-note">
                        {decisionTarget?.mode === 'approve'
                            ? 'Catatan (opsional)'
                            : 'Alasan penolakan'}
                    </Label>
                    <Textarea
                        id="decision-note"
                        rows={3}
                        value={decisionForm.data.decision_note}
                        onChange={(e) =>
                            decisionForm.setData(
                                'decision_note',
                                e.target.value,
                            )
                        }
                        required={decisionTarget?.mode === 'reject'}
                    />
                    <InputError message={decisionForm.errors.decision_note} />
                </div>
            </FormDialog>
        </>
    );
}

RewardsIndex.layout = (page: ReactNode) => (
    <StaffLayout
        breadcrumbs={[
            { title: 'Kesiswaan & Asrama', href: '#' },
            { title: 'Reward & voucher', href: RewardController.index.url() },
        ]}
    >
        {page}
    </StaffLayout>
);
