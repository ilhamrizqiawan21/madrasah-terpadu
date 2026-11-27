import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Ban, ReceiptText, SlidersHorizontal } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { useState } from 'react';
import InvoiceAdjustmentController from '@/actions/App/Http/Controllers/Admin/InvoiceAdjustmentController';
import InvoiceController from '@/actions/App/Http/Controllers/Admin/InvoiceController';
import { DateText } from '@/components/app/date-text';
import { EmptyState } from '@/components/app/empty-state';
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
import StaffLayout from '@/layouts/staff-layout';

type AdjustmentRow = {
    id: number;
    type: string;
    amount: number;
    reason: string;
    actor_name: string;
    created_at: string;
};

type PaymentRow = {
    id: number;
    receipt_number: string;
    method: string;
    allocated_amount: number;
    paid_at: string;
    reversed_at: string | null;
    cashier_name: string;
};

type Props = {
    invoice: {
        id: number;
        student_id: number;
        student_name: string;
        student_nis: string;
        unit_name: string;
        fee_type_name: string;
        category: string;
        description: string;
        period: string | null;
        due_date: string;
        amount: number;
        adjustment_total: number;
        paid_total: number;
        remaining: number;
        status: string;
        status_label: string;
        cancelled_reason: string | null;
        is_overdue: boolean;
        created_at: string;
    };
    adjustments: AdjustmentRow[];
    payments: PaymentRow[];
    adjustmentTypes: { value: string; label: string }[];
    canAdjust: boolean;
};

export default function InvoiceShowPage({
    invoice,
    adjustments,
    payments,
    adjustmentTypes,
    canAdjust,
}: Props) {
    const [adjustOpen, setAdjustOpen] = useState(false);
    const [cancelOpen, setCancelOpen] = useState(false);

    const adjustForm = useForm({
        type: adjustmentTypes[0]?.value ?? 'discount',
        amount: '',
        reason: '',
    });

    const cancelForm = useForm({
        reason: '',
    });

    function submitAdjust(e: FormEvent<HTMLFormElement>) {
        e.preventDefault();
        adjustForm.transform((data) => ({
            ...data,
            amount: Number(data.amount || 0),
        }));
        adjustForm.post(InvoiceAdjustmentController.store.url(invoice.id), {
            preserveScroll: true,
            onSuccess: () => {
                setAdjustOpen(false);
                adjustForm.reset();
            },
        });
    }

    function submitCancel(e: FormEvent<HTMLFormElement>) {
        e.preventDefault();
        cancelForm.post(InvoiceAdjustmentController.cancel.url(invoice.id), {
            preserveScroll: true,
            onSuccess: () => {
                setCancelOpen(false);
                cancelForm.reset();
            },
        });
    }

    const canBeAdjusted =
        canAdjust && invoice.status !== 'cancelled' && invoice.remaining > 0;

    const canBeCancelled =
        canAdjust && invoice.status !== 'cancelled' && invoice.paid_total === 0;

    return (
        <StaffLayout
            breadcrumbs={[
                { title: 'Keuangan', href: InvoiceController.index.url() },
                { title: 'Tagihan', href: InvoiceController.index.url() },
                {
                    title: invoice.description,
                    href: InvoiceController.show.url(invoice.id),
                },
            ]}
        >
            <Head title={invoice.description} />

            <div className="space-y-6">
                <PageHeader
                    title={invoice.description}
                    description={`${invoice.student_name} (${invoice.student_nis}) · ${invoice.unit_name}`}
                    actions={
                        <div className="flex flex-wrap items-center gap-2">
                            <Button variant="outline" asChild>
                                <Link href={InvoiceController.index.url()}>
                                    <ArrowLeft
                                        className="size-4"
                                        strokeWidth={1.75}
                                    />
                                    Kembali
                                </Link>
                            </Button>
                            <Button variant="outline" asChild>
                                <Link
                                    href={InvoiceController.studentCard.url(
                                        invoice.student_id,
                                    )}
                                >
                                    <ReceiptText
                                        className="size-4"
                                        strokeWidth={1.75}
                                    />
                                    Kartu tagihan siswa
                                </Link>
                            </Button>
                            {canBeAdjusted && (
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => {
                                        adjustForm.clearErrors();
                                        setAdjustOpen(true);
                                    }}
                                >
                                    <SlidersHorizontal
                                        className="size-4"
                                        strokeWidth={1.75}
                                    />
                                    Sesuaikan
                                </Button>
                            )}
                            {canBeCancelled && (
                                <Button
                                    type="button"
                                    variant="destructive"
                                    onClick={() => {
                                        cancelForm.clearErrors();
                                        setCancelOpen(true);
                                    }}
                                >
                                    <Ban
                                        className="size-4"
                                        strokeWidth={1.75}
                                    />
                                    Batalkan tagihan
                                </Button>
                            )}
                        </div>
                    }
                />

                <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <div className="rounded-lg border border-border bg-card p-4">
                        <div className="text-xs text-muted-foreground">
                            Nominal awal
                        </div>
                        <div className="mt-1 text-lg font-semibold">
                            <MoneyText amount={invoice.amount} />
                        </div>
                    </div>
                    <div className="rounded-lg border border-border bg-card p-4">
                        <div className="text-xs text-muted-foreground">
                            Penyesuaian
                        </div>
                        <div className="mt-1 text-lg font-semibold">
                            <MoneyText amount={invoice.adjustment_total} />
                        </div>
                    </div>
                    <div className="rounded-lg border border-border bg-card p-4">
                        <div className="text-xs text-muted-foreground">
                            Terbayar
                        </div>
                        <div className="mt-1 text-lg font-semibold text-primary">
                            <MoneyText amount={invoice.paid_total} />
                        </div>
                    </div>
                    <div className="rounded-lg border border-border bg-card p-4">
                        <div className="text-xs text-muted-foreground">
                            Sisa tagihan
                        </div>
                        <div className="mt-1 text-lg font-semibold">
                            <MoneyText amount={invoice.remaining} />
                        </div>
                    </div>
                </div>

                <div className="rounded-lg border border-border bg-card p-5 text-sm">
                    <dl className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div>
                            <dt className="text-xs text-muted-foreground">
                                Jenis &amp; kategori
                            </dt>
                            <dd className="mt-1 font-medium">
                                {invoice.fee_type_name} ({invoice.category})
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs text-muted-foreground">
                                Jatuh tempo
                            </dt>
                            <dd className="mt-1 font-medium">
                                <DateText value={invoice.due_date} />
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs text-muted-foreground">
                                Status
                            </dt>
                            <dd className="mt-1">
                                <StatusBadge
                                    variant={
                                        invoice.is_overdue
                                            ? 'warning'
                                            : invoice.status === 'paid'
                                              ? 'success'
                                              : 'muted'
                                    }
                                >
                                    {invoice.is_overdue
                                        ? 'Menunggak'
                                        : invoice.status_label}
                                </StatusBadge>
                            </dd>
                        </div>
                        {invoice.cancelled_reason && (
                            <div className="sm:col-span-3">
                                <dt className="text-xs text-muted-foreground">
                                    Alasan pembatalan
                                </dt>
                                <dd className="mt-1 text-destructive">
                                    {invoice.cancelled_reason}
                                </dd>
                            </div>
                        )}
                    </dl>
                </div>

                <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    <div className="space-y-3">
                        <h2 className="text-sm font-semibold text-foreground">
                            Riwayat penyesuaian
                        </h2>
                        {adjustments.length === 0 ? (
                            <EmptyState
                                title="Belum ada penyesuaian"
                                description="Diskon, pembebasan, atau koreksi nominal akan tampil di sini."
                            />
                        ) : (
                            <div className="overflow-x-auto rounded-lg border border-border bg-card">
                                <table className="w-full text-sm">
                                    <thead className="border-b border-border bg-muted/50 text-xs text-muted-foreground">
                                        <tr>
                                            <th className="px-3 py-2 text-left font-medium">
                                                Alasan
                                            </th>
                                            <th className="px-3 py-2 text-right font-medium">
                                                Nominal
                                            </th>
                                            <th className="px-3 py-2 text-left font-medium">
                                                Petugas
                                            </th>
                                            <th className="px-3 py-2 text-left font-medium">
                                                Tanggal
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-border">
                                        {adjustments.map((adj) => (
                                            <tr key={adj.id}>
                                                <td className="px-3 py-2">
                                                    {adj.reason}
                                                </td>
                                                <td className="px-3 py-2 text-right">
                                                    <MoneyText
                                                        amount={adj.amount}
                                                    />
                                                </td>
                                                <td className="px-3 py-2 text-muted-foreground">
                                                    {adj.actor_name}
                                                </td>
                                                <td className="px-3 py-2 text-muted-foreground">
                                                    <DateText
                                                        value={adj.created_at}
                                                    />
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </div>

                    <div className="space-y-3">
                        <h2 className="text-sm font-semibold text-foreground">
                            Riwayat pembayaran
                        </h2>
                        {payments.length === 0 ? (
                            <EmptyState
                                title="Belum ada pembayaran"
                                description="Pembayaran kasir maupun online yang dialokasikan ke tagihan ini akan tampil di sini."
                            />
                        ) : (
                            <div className="overflow-x-auto rounded-lg border border-border bg-card">
                                <table className="w-full text-sm">
                                    <thead className="border-b border-border bg-muted/50 text-xs text-muted-foreground">
                                        <tr>
                                            <th className="px-3 py-2 text-left font-medium">
                                                No. kuitansi
                                            </th>
                                            <th className="px-3 py-2 text-left font-medium">
                                                Metode
                                            </th>
                                            <th className="px-3 py-2 text-right font-medium">
                                                Alokasi
                                            </th>
                                            <th className="px-3 py-2 text-left font-medium">
                                                Tanggal
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-border">
                                        {payments.map((pay) => (
                                            <tr key={pay.id}>
                                                <td className="px-3 py-2 font-medium tabular-nums">
                                                    {pay.receipt_number}
                                                    {pay.reversed_at && (
                                                        <span className="ml-1.5 text-xs text-destructive">
                                                            (Dibalik)
                                                        </span>
                                                    )}
                                                </td>
                                                <td className="px-3 py-2 text-muted-foreground">
                                                    {pay.method}
                                                </td>
                                                <td className="px-3 py-2 text-right">
                                                    <MoneyText
                                                        amount={
                                                            pay.allocated_amount
                                                        }
                                                    />
                                                </td>
                                                <td className="px-3 py-2 text-muted-foreground">
                                                    <DateText
                                                        value={pay.paid_at}
                                                        mode="datetime"
                                                    />
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </div>
                </div>
            </div>

            <FormDialog
                open={adjustOpen}
                onOpenChange={setAdjustOpen}
                title="Sesuaikan tagihan"
                description="Penyesuaian tercatat permanen dan mengurangi sisa tagihan."
                onSubmit={submitAdjust}
                processing={adjustForm.processing}
                submitLabel="Simpan penyesuaian"
            >
                <div className="space-y-4">
                    <div className="space-y-1.5">
                        <Label htmlFor="adj-type">Jenis penyesuaian</Label>
                        <Select
                            value={adjustForm.data.type}
                            onValueChange={(v) => adjustForm.setData('type', v)}
                        >
                            <SelectTrigger id="adj-type">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {adjustmentTypes.map((t) => (
                                    <SelectItem key={t.value} value={t.value}>
                                        {t.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={adjustForm.errors.type} />
                    </div>

                    <div className="space-y-1.5">
                        <Label htmlFor="adj-amount">
                            Nominal pengurangan (maks Rp{' '}
                            {invoice.remaining.toLocaleString('id-ID')})
                        </Label>
                        <Input
                            id="adj-amount"
                            type="number"
                            min={1}
                            max={invoice.remaining}
                            value={adjustForm.data.amount}
                            onChange={(e) =>
                                adjustForm.setData('amount', e.target.value)
                            }
                            className="text-right tabular-nums"
                            required
                        />
                        <InputError message={adjustForm.errors.amount} />
                    </div>

                    <div className="space-y-1.5">
                        <Label htmlFor="adj-reason">Alasan</Label>
                        <Input
                            id="adj-reason"
                            value={adjustForm.data.reason}
                            onChange={(e) =>
                                adjustForm.setData('reason', e.target.value)
                            }
                            placeholder="Mis. Keringanan siswa berprestasi"
                            maxLength={255}
                            required
                        />
                        <InputError message={adjustForm.errors.reason} />
                    </div>
                </div>
            </FormDialog>

            <FormDialog
                open={cancelOpen}
                onOpenChange={setCancelOpen}
                title="Batalkan tagihan?"
                description="Tagihan yang dibatalkan tidak lagi dihitung sebagai piutang. Hanya berlaku untuk tagihan yang belum memiliki pembayaran."
                onSubmit={submitCancel}
                processing={cancelForm.processing}
                submitLabel="Ya, batalkan tagihan"
            >
                <div className="space-y-1.5">
                    <Label htmlFor="cancel-reason">Alasan pembatalan</Label>
                    <Input
                        id="cancel-reason"
                        value={cancelForm.data.reason}
                        onChange={(e) =>
                            cancelForm.setData('reason', e.target.value)
                        }
                        placeholder="Mis. Tagihan ganda atau siswa mutasi"
                        maxLength={255}
                        required
                    />
                    <InputError message={cancelForm.errors.reason} />
                </div>
            </FormDialog>
        </StaffLayout>
    );
}

InvoiceShowPage.layout = (page: ReactNode) => page;
