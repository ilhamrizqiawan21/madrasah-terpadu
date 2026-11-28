import { Head, useForm } from '@inertiajs/react';
import { CreditCard, FileDown, Upload } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { useState } from 'react';
import ReceiptController from '@/actions/App/Http/Controllers/Billing/ReceiptController';
import TransferProofController from '@/actions/App/Http/Controllers/Guardian/TransferProofController';
import { DateText } from '@/components/app/date-text';
import { EmptyState } from '@/components/app/empty-state';
import { FormDialog } from '@/components/app/form-dialog';
import { MoneyText } from '@/components/app/money-text';
import { StatusBadge } from '@/components/app/status-badge';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import GuardianLayout from '@/layouts/guardian-layout';

type OpenInvoice = {
    id: number;
    fee_type_name: string;
    description: string;
    due_date: string;
    amount: number;
    adjustment_total: number;
    paid_total: number;
    remaining: number;
    status: string;
    status_label: string;
    is_overdue: boolean;
};

type PaymentItem = {
    id: number;
    receipt_number: string;
    method_label: string;
    amount: number;
    paid_at: string;
};

type ChildBilling = {
    id: number;
    name: string;
    nis: string;
    unit_name: string;
    total_remaining: number;
    open_invoices: OpenInvoice[];
    payments: PaymentItem[];
};

type Props = {
    children: ChildBilling[];
    familyTotalRemaining: number;
    midtransEnabled: boolean;
};

export default function GuardianBilling({
    children,
    familyTotalRemaining,
    midtransEnabled,
}: Props) {
    const [uploading, setUploading] = useState<{
        studentId: number;
        invoice: OpenInvoice;
    } | null>(null);

    const proofForm = useForm<{
        amount: string;
        notes: string;
        photo: File | null;
    }>({
        amount: '',
        notes: '',
        photo: null,
    });

    function openUpload(studentId: number, invoice: OpenInvoice) {
        proofForm.clearErrors();
        proofForm.setData({
            amount: String(invoice.remaining),
            notes: '',
            photo: null,
        });
        setUploading({ studentId, invoice });
    }

    function submitProof(e: FormEvent<HTMLFormElement>) {
        e.preventDefault();
        if (!uploading) {
            return;
        }
        proofForm.transform((data) => ({
            ...data,
            amount: Number(data.amount || 0),
        }));
        proofForm.post(
            TransferProofController.store.url([
                uploading.studentId,
                uploading.invoice.id,
            ]),
            {
                forceFormData: true,
                preserveScroll: true,
                onSuccess: () => {
                    setUploading(null);
                    proofForm.reset();
                },
            },
        );
    }

    return (
        <GuardianLayout>
            <Head title="Tagihan" />

            <div className="space-y-5">
                <div className="rounded-lg border border-border bg-card p-4 shadow-xs">
                    <div className="text-xs text-muted-foreground">
                        Total tagihan keluarga
                    </div>
                    <div className="mt-1 text-2xl font-semibold text-foreground">
                        <MoneyText amount={familyTotalRemaining} />
                    </div>
                    <p className="mt-1 text-xs text-muted-foreground">
                        {midtransEnabled
                            ? 'Pilih tagihan anak di bawah untuk membayar secara online.'
                            : 'Pembayaran online akan aktif setelah integrasi pembayaran dibuka; saat ini pembayaran dilakukan melalui kasir sekolah.'}
                    </p>
                </div>

                {children.length === 0 ? (
                    <EmptyState
                        title="Belum ada data anak terhubung"
                        description="Hubungi admin sekolah untuk menautkan akun wali Anda dengan data siswa."
                    />
                ) : (
                    children.map((child) => (
                        <section
                            key={child.id}
                            className="space-y-3 rounded-lg border border-border bg-card p-4 shadow-xs"
                        >
                            <div className="flex flex-wrap items-center justify-between gap-2 border-b border-border pb-3">
                                <div>
                                    <h2 className="text-base font-semibold text-foreground">
                                        {child.name}
                                    </h2>
                                    <p className="text-xs text-muted-foreground tabular-nums">
                                        NIS {child.nis} · {child.unit_name}
                                    </p>
                                </div>
                                <div className="text-right">
                                    <div className="text-xs text-muted-foreground">
                                        Sisa tagihan
                                    </div>
                                    <div className="text-base font-semibold">
                                        <MoneyText
                                            amount={child.total_remaining}
                                        />
                                    </div>
                                </div>
                            </div>

                            {child.open_invoices.length === 0 ? (
                                <p className="py-2 text-sm text-muted-foreground">
                                    Tidak ada tagihan terbuka untuk anak ini.
                                </p>
                            ) : (
                                <div className="space-y-2.5">
                                    {child.open_invoices.map((inv) => (
                                        <div
                                            key={inv.id}
                                            className={`rounded-md border p-3 ${
                                                inv.is_overdue
                                                    ? 'border-accent-attention bg-accent-attention/10'
                                                    : 'border-border bg-background'
                                            }`}
                                        >
                                            <div className="flex items-start justify-between gap-3">
                                                <div className="space-y-1">
                                                    <div className="font-medium text-foreground">
                                                        {inv.description}
                                                    </div>
                                                    <div className="text-xs text-muted-foreground">
                                                        Jatuh tempo:{' '}
                                                        <DateText
                                                            value={inv.due_date}
                                                        />
                                                    </div>
                                                </div>
                                                <div className="text-right">
                                                    <div className="font-semibold">
                                                        <MoneyText
                                                            amount={
                                                                inv.remaining
                                                            }
                                                        />
                                                    </div>
                                                    {inv.is_overdue ? (
                                                        <StatusBadge variant="warning">
                                                            Menunggak
                                                        </StatusBadge>
                                                    ) : (
                                                        <StatusBadge variant="muted">
                                                            {inv.status_label}
                                                        </StatusBadge>
                                                    )}
                                                </div>
                                            </div>

                                            <div className="mt-3 flex flex-wrap justify-end gap-2">
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="sm"
                                                    className="min-h-11"
                                                    onClick={() =>
                                                        openUpload(
                                                            child.id,
                                                            inv,
                                                        )
                                                    }
                                                >
                                                    <Upload
                                                        className="size-4"
                                                        strokeWidth={1.75}
                                                    />
                                                    Unggah bukti transfer
                                                </Button>

                                                {midtransEnabled &&
                                                    inv.status === 'unpaid' && (
                                                        <Button
                                                            type="button"
                                                            size="sm"
                                                            className="min-h-11"
                                                            disabled
                                                        >
                                                            <CreditCard
                                                                className="size-4"
                                                                strokeWidth={
                                                                    1.75
                                                                }
                                                            />
                                                            Bayar online
                                                        </Button>
                                                    )}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}

                            {child.payments.length > 0 && (
                                <div className="space-y-2 pt-2">
                                    <h3 className="text-xs font-semibold tracking-wide text-muted-foreground">
                                        Riwayat pembayaran
                                    </h3>
                                    <div className="divide-y divide-border rounded-md border border-border">
                                        {child.payments.map((pay) => (
                                            <div
                                                key={pay.id}
                                                className="flex items-center justify-between gap-3 px-3 py-2 text-sm"
                                            >
                                                <div>
                                                    <div className="font-medium tabular-nums">
                                                        {pay.receipt_number}
                                                    </div>
                                                    <div className="text-xs text-muted-foreground">
                                                        {pay.method_label} ·{' '}
                                                        <DateText
                                                            value={pay.paid_at}
                                                        />
                                                    </div>
                                                </div>
                                                <div className="flex items-center gap-2">
                                                    <MoneyText
                                                        amount={pay.amount}
                                                        className="font-medium"
                                                    />
                                                    <Button
                                                        variant="outline"
                                                        size="icon"
                                                        className="min-h-11 min-w-11"
                                                        asChild
                                                        aria-label={`Unduh kuitansi ${pay.receipt_number}`}
                                                    >
                                                        <a
                                                            href={ReceiptController.guardian.url(
                                                                [
                                                                    child.id,
                                                                    pay.id,
                                                                ],
                                                            )}
                                                            target="_blank"
                                                            rel="noreferrer"
                                                        >
                                                            <FileDown
                                                                className="size-4"
                                                                strokeWidth={
                                                                    1.75
                                                                }
                                                            />
                                                        </a>
                                                    </Button>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            )}
                        </section>
                    ))
                )}
            </div>

            <FormDialog
                open={uploading !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setUploading(null);
                    }
                }}
                title="Unggah bukti transfer"
                description={
                    uploading
                        ? `${uploading.invoice.description} (sisa Rp ${uploading.invoice.remaining.toLocaleString('id-ID')})`
                        : ''
                }
                onSubmit={submitProof}
                processing={proofForm.processing}
                submitLabel="Kirim bukti"
            >
                <div className="space-y-4">
                    <div className="space-y-1.5">
                        <Label htmlFor="proof-amount">
                            Nominal transfer (Rp)
                        </Label>
                        <Input
                            id="proof-amount"
                            type="number"
                            min={1}
                            max={uploading?.invoice.remaining}
                            value={proofForm.data.amount}
                            onChange={(e) =>
                                proofForm.setData('amount', e.target.value)
                            }
                            className="text-right tabular-nums"
                            required
                        />
                        <InputError message={proofForm.errors.amount} />
                    </div>

                    <div className="space-y-1.5">
                        <Label htmlFor="proof-photo">
                            Foto bukti transfer (JPG/PNG, maks 2 MB)
                        </Label>
                        <Input
                            id="proof-photo"
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            onChange={(e) =>
                                proofForm.setData(
                                    'photo',
                                    e.target.files?.[0] ?? null,
                                )
                            }
                            required
                        />
                        <InputError message={proofForm.errors.photo} />
                    </div>

                    <div className="space-y-1.5">
                        <Label htmlFor="proof-notes">Catatan (opsional)</Label>
                        <Input
                            id="proof-notes"
                            value={proofForm.data.notes}
                            onChange={(e) =>
                                proofForm.setData('notes', e.target.value)
                            }
                            placeholder="Mis. Transfer dari rekening BRI"
                            maxLength={255}
                        />
                        <InputError message={proofForm.errors.notes} />
                    </div>
                </div>
            </FormDialog>
        </GuardianLayout>
    );
}

GuardianBilling.layout = (page: ReactNode) => page;
