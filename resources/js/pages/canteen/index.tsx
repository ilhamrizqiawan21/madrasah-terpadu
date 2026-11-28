import { Head, usePage } from '@inertiajs/react';
import { Delete, RotateCcw, ScanLine } from 'lucide-react';
import type { FormEvent } from 'react';
import { useRef, useState } from 'react';
import CanteenPosController from '@/actions/App/Http/Controllers/Canteen/CanteenPosController';
import { ConfirmDialog } from '@/components/app/confirm-dialog';
import { DateText } from '@/components/app/date-text';
import { FormDialog } from '@/components/app/form-dialog';
import { MoneyText } from '@/components/app/money-text';
import { StatusBadge } from '@/components/app/status-badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import KioskLayout from '@/layouts/kiosk-layout';
import { formatTime } from '@/lib/format';
import type { Auth } from '@/types';

type ScannedStudent = {
    student: {
        id: number;
        nis: string;
        name: string;
        unit: string;
        classroom: string;
    };
    wallet: {
        balance: number;
        daily_limit: number;
        spent_today: number;
        remaining_limit: number;
        is_frozen: boolean;
    };
    vouchers: {
        total_remaining: number;
        count: number;
    };
};

type TransactionRow = {
    id: number;
    student_name: string;
    student_nis: string;
    total: number;
    paid_from_vouchers: number;
    paid_from_wallet: number;
    status: 'completed' | 'reversed';
    status_label: string;
    created_at: string;
    can_reverse: boolean;
};

type Props = {
    recentTransactions: TransactionRow[];
};

const HIGH_AMOUNT_THRESHOLD = 50_000;
const QUICK_AMOUNTS = [2000, 5000, 10000, 20000];

export default function CanteenPosIndex({
    recentTransactions: initialTransactions,
}: Props) {
    const { auth, branding } = usePage<{
        auth: Auth;
        branding?: { appName?: string };
    }>().props;
    const scanInputRef = useRef<HTMLInputElement>(null);

    const [tokenInput, setTokenInput] = useState('');
    const [scanLoading, setScanLoading] = useState(false);
    const [scanError, setScanError] = useState<string | null>(null);
    const [scanned, setScanned] = useState<ScannedStudent | null>(null);

    const [amountText, setAmountText] = useState('');
    const [submitting, setSubmitting] = useState(false);
    const [txMessage, setTxMessage] = useState<{
        type: 'success' | 'error';
        text: string;
    } | null>(null);
    const [confirmHighOpen, setConfirmHighOpen] = useState(false);
    const [pendingUuid, setPendingUuid] = useState<string | null>(null);

    const [transactions, setTransactions] =
        useState<TransactionRow[]>(initialTransactions);
    const [reverseTarget, setReverseTarget] = useState<TransactionRow | null>(
        null,
    );
    const [reverseReason, setReverseReason] = useState('');
    const [reverseError, setReverseError] = useState<string | null>(null);
    const [reversing, setReversing] = useState(false);

    const parsedAmount = Number.parseInt(amountText || '0', 10) || 0;

    function csrfToken(): string {
        const meta = document.querySelector('meta[name="csrf-token"]');

        return meta?.getAttribute('content') ?? '';
    }

    async function handleScan(e: FormEvent) {
        e.preventDefault();
        const trimmed = tokenInput.trim();
        if (trimmed === '' || scanLoading) {
            return;
        }

        setScanLoading(true);
        setScanError(null);
        setTxMessage(null);

        try {
            const response = await fetch(CanteenPosController.lookup.url(), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                body: JSON.stringify({ token: trimmed }),
            });
            const data = (await response.json()) as ScannedStudent & {
                message?: string;
            };
            if (!response.ok) {
                setScanned(null);
                setScanError(data.message ?? 'Kartu tidak dikenali.');
            } else {
                setScanned(data);
                setAmountText('');
                setPendingUuid(crypto.randomUUID());
            }
        } catch {
            setScanError('Gagal terhubung ke server.');
        } finally {
            setScanLoading(false);
            setTokenInput('');
        }
    }

    async function executePurchase(clientUuid: string) {
        if (!scanned || parsedAmount <= 0 || submitting) {
            return;
        }

        setSubmitting(true);
        setTxMessage(null);

        try {
            const response = await fetch(CanteenPosController.purchase.url(), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                body: JSON.stringify({
                    student_id: scanned.student.id,
                    total: parsedAmount,
                    client_uuid: clientUuid,
                }),
            });

            const data = (await response.json()) as {
                transaction?: TransactionRow;
                message?: string;
            };

            if (!response.ok || !data.transaction) {
                setTxMessage({
                    type: 'error',
                    text: data.message ?? 'Transaksi gagal diproses.',
                });
                return;
            }

            setTransactions((prev) =>
                [data.transaction!, ...prev].slice(0, 15),
            );
            setTxMessage({
                type: 'success',
                text: `Transaksi berhasil untuk ${scanned.student.name}.`,
            });
            setScanned(null);
            setAmountText('');
            setPendingUuid(null);
            scanInputRef.current?.focus();
        } catch {
            setTxMessage({
                type: 'error',
                text: 'Koneksi terputus. Coba lagi.',
            });
        } finally {
            setSubmitting(false);
            setConfirmHighOpen(false);
        }
    }

    function handlePayClick() {
        if (!scanned || parsedAmount <= 0) {
            return;
        }
        const uuid = pendingUuid ?? crypto.randomUUID();
        setPendingUuid(uuid);

        if (parsedAmount > HIGH_AMOUNT_THRESHOLD) {
            setConfirmHighOpen(true);
            return;
        }
        void executePurchase(uuid);
    }

    async function handleReverse(e: FormEvent) {
        e.preventDefault();
        if (!reverseTarget || reversing) {
            return;
        }

        setReversing(true);
        setReverseError(null);

        try {
            const response = await fetch(
                CanteenPosController.reverse.url({
                    transaction: reverseTarget.id,
                }),
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': csrfToken(),
                    },
                    body: JSON.stringify({ reason: reverseReason }),
                },
            );
            const data = (await response.json()) as {
                transaction?: {
                    id: number;
                    status: 'completed' | 'reversed';
                    status_label: string;
                };
                message?: string;
            };
            if (!response.ok || !data.transaction) {
                setReverseError(data.message ?? 'Gagal membalikkan transaksi.');
                return;
            }
            setTransactions((prev) =>
                prev.map((tx) =>
                    tx.id === reverseTarget.id
                        ? {
                              ...tx,
                              status: data.transaction!.status,
                              status_label: data.transaction!.status_label,
                              can_reverse: false,
                          }
                        : tx,
                ),
            );
            setReverseTarget(null);
            setReverseReason('');
        } catch {
            setReverseError('Gagal terhubung ke server.');
        } finally {
            setReversing(false);
        }
    }

    function appendDigit(digit: string) {
        setAmountText((prev) => {
            const next = `${prev}${digit}`.replace(/^0+/, '');
            return next.length > 7 ? prev : next;
        });
    }

    return (
        <KioskLayout
            schoolName={branding?.appName ?? 'Madrasah Terpadu'}
            kioskTitle="POS Kantin"
            operatorName={auth.user.name}
        >
            <Head title="POS Kantin" />

            <div className="mx-auto max-w-6xl space-y-6 p-4 sm:p-6">
                <div className="grid gap-6 lg:grid-cols-2">
                    <section className="space-y-4 rounded-xl border border-border bg-card p-5 shadow-xs">
                        <form onSubmit={handleScan} className="space-y-2">
                            <Label htmlFor="canteen-scan">
                                Scan kartu QR / RFID siswa
                            </Label>
                            <div className="flex gap-2">
                                <Input
                                    id="canteen-scan"
                                    ref={scanInputRef}
                                    autoFocus
                                    value={tokenInput}
                                    onChange={(e) =>
                                        setTokenInput(e.target.value)
                                    }
                                    placeholder="Arahkan scanner ke kartu siswa..."
                                    className="min-h-11"
                                />
                                <Button
                                    type="submit"
                                    disabled={scanLoading}
                                    className="min-h-11 px-4"
                                >
                                    <ScanLine className="size-4" />
                                    Cek
                                </Button>
                            </div>
                        </form>

                        {scanError && (
                            <p
                                role="alert"
                                className="rounded-lg border border-destructive/40 bg-destructive/10 px-3 py-2 text-sm text-destructive"
                            >
                                {scanError}
                            </p>
                        )}

                        {txMessage && (
                            <p
                                role="status"
                                className={
                                    txMessage.type === 'success'
                                        ? 'rounded-lg border border-success/40 bg-success/10 px-3 py-2 text-sm text-foreground'
                                        : 'rounded-lg border border-destructive/40 bg-destructive/10 px-3 py-2 text-sm text-destructive'
                                }
                            >
                                {txMessage.text}
                            </p>
                        )}

                        {scanned ? (
                            <div className="space-y-3 rounded-lg border border-border bg-muted/30 p-4">
                                <div className="flex items-start justify-between gap-2">
                                    <div>
                                        <p className="text-lg font-semibold">
                                            {scanned.student.name}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {scanned.student.nis} ·{' '}
                                            {scanned.student.unit} ·{' '}
                                            {scanned.student.classroom}
                                        </p>
                                    </div>
                                    {scanned.wallet.is_frozen && (
                                        <StatusBadge variant="destructive">
                                            Dompet dibekukan
                                        </StatusBadge>
                                    )}
                                </div>

                                <dl className="grid grid-cols-2 gap-3 border-t border-border pt-3 text-sm sm:grid-cols-3">
                                    <div>
                                        <dt className="text-xs text-muted-foreground">
                                            Saldo dompet
                                        </dt>
                                        <dd className="font-semibold">
                                            <MoneyText
                                                amount={scanned.wallet.balance}
                                            />
                                        </dd>
                                    </div>
                                    <div>
                                        <dt className="text-xs text-muted-foreground">
                                            Voucher aktif (
                                            {scanned.vouchers.count})
                                        </dt>
                                        <dd className="font-semibold text-primary">
                                            <MoneyText
                                                amount={
                                                    scanned.vouchers
                                                        .total_remaining
                                                }
                                            />
                                        </dd>
                                    </div>
                                    <div>
                                        <dt className="text-xs text-muted-foreground">
                                            Sisa limit hari ini
                                        </dt>
                                        <dd className="font-semibold">
                                            <MoneyText
                                                amount={
                                                    scanned.wallet
                                                        .remaining_limit
                                                }
                                            />
                                        </dd>
                                    </div>
                                </dl>
                            </div>
                        ) : (
                            <p className="rounded-lg border border-dashed border-border px-4 py-8 text-center text-sm text-muted-foreground">
                                Scan kartu siswa untuk melihat saldo, voucher,
                                dan limit jajan harian.
                            </p>
                        )}
                    </section>

                    <section className="space-y-4 rounded-xl border border-border bg-card p-5 shadow-xs">
                        <div>
                            <Label htmlFor="canteen-amount">
                                Nominal belanja
                            </Label>
                            <div className="mt-1.5 flex items-center justify-between rounded-lg border border-border bg-muted/30 px-4 py-3">
                                <span className="text-sm text-muted-foreground">
                                    Total
                                </span>
                                <span className="text-2xl font-semibold">
                                    <MoneyText amount={parsedAmount} />
                                </span>
                            </div>
                        </div>

                        <div className="grid grid-cols-4 gap-2">
                            {QUICK_AMOUNTS.map((val) => (
                                <Button
                                    key={val}
                                    type="button"
                                    variant="outline"
                                    className="min-h-11 text-xs"
                                    onClick={() => setAmountText(String(val))}
                                >
                                    <MoneyText amount={val} />
                                </Button>
                            ))}
                        </div>

                        <div className="grid grid-cols-3 gap-2">
                            {[
                                '1',
                                '2',
                                '3',
                                '4',
                                '5',
                                '6',
                                '7',
                                '8',
                                '9',
                                '000',
                                '0',
                            ].map((key) => (
                                <Button
                                    key={key}
                                    type="button"
                                    variant="outline"
                                    className="min-h-12 text-lg font-medium tabular-nums"
                                    onClick={() => appendDigit(key)}
                                >
                                    {key}
                                </Button>
                            ))}
                            <Button
                                type="button"
                                variant="outline"
                                aria-label="Hapus digit"
                                className="min-h-12"
                                onClick={() =>
                                    setAmountText((prev) => prev.slice(0, -1))
                                }
                            >
                                <Delete className="size-5" />
                            </Button>
                        </div>

                        <Button
                            type="button"
                            className="min-h-12 w-full text-base font-semibold"
                            disabled={
                                !scanned ||
                                scanned.wallet.is_frozen ||
                                parsedAmount <= 0 ||
                                submitting
                            }
                            onClick={handlePayClick}
                        >
                            Bayar
                        </Button>
                    </section>
                </div>

                <section className="rounded-xl border border-border bg-card p-5 shadow-xs">
                    <h2 className="text-sm font-semibold">
                        Transaksi kantin hari ini
                    </h2>
                    {transactions.length === 0 ? (
                        <p className="mt-3 text-sm text-muted-foreground">
                            Belum ada transaksi tercatat hari ini.
                        </p>
                    ) : (
                        <div className="mt-3 overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead className="text-xs text-muted-foreground">
                                    <tr className="border-b border-border">
                                        <th className="py-2 text-left font-medium">
                                            Siswa
                                        </th>
                                        <th className="px-2 py-2 text-right font-medium">
                                            Total
                                        </th>
                                        <th className="px-2 py-2 text-right font-medium">
                                            Voucher
                                        </th>
                                        <th className="px-2 py-2 text-right font-medium">
                                            Dompet
                                        </th>
                                        <th className="px-2 py-2 text-center font-medium">
                                            Status
                                        </th>
                                        <th className="px-2 py-2 text-left font-medium">
                                            Waktu
                                        </th>
                                        <th className="py-2 text-right font-medium">
                                            Aksi
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border">
                                    {transactions.map((tx) => (
                                        <tr key={tx.id}>
                                            <td className="py-2">
                                                <div className="font-medium">
                                                    {tx.student_name}
                                                </div>
                                                <div className="text-xs text-muted-foreground">
                                                    {tx.student_nis}
                                                </div>
                                            </td>
                                            <td className="px-2 py-2 text-right font-medium">
                                                <MoneyText amount={tx.total} />
                                            </td>
                                            <td className="px-2 py-2 text-right text-muted-foreground">
                                                <MoneyText
                                                    amount={
                                                        tx.paid_from_vouchers
                                                    }
                                                />
                                            </td>
                                            <td className="px-2 py-2 text-right">
                                                <MoneyText
                                                    amount={tx.paid_from_wallet}
                                                />
                                            </td>
                                            <td className="px-2 py-2 text-center">
                                                <StatusBadge
                                                    variant={
                                                        tx.status === 'reversed'
                                                            ? 'destructive'
                                                            : 'success'
                                                    }
                                                >
                                                    {tx.status_label}
                                                </StatusBadge>
                                            </td>
                                            <td className="px-2 py-2 text-xs text-muted-foreground">
                                                <DateText
                                                    value={tx.created_at}
                                                />{' '}
                                                {formatTime(tx.created_at)}
                                            </td>
                                            <td className="py-2 text-right">
                                                {tx.can_reverse && (
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={() => {
                                                            setReverseTarget(
                                                                tx,
                                                            );
                                                            setReverseReason(
                                                                '',
                                                            );
                                                            setReverseError(
                                                                null,
                                                            );
                                                        }}
                                                    >
                                                        <RotateCcw className="size-3.5" />
                                                        Koreksi
                                                    </Button>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </section>
            </div>

            <ConfirmDialog
                open={confirmHighOpen}
                onOpenChange={setConfirmHighOpen}
                title="Konfirmasi nominal besar"
                description={
                    scanned
                        ? `Proses pembayaran sebesar Rp ${parsedAmount.toLocaleString('id-ID')} untuk ${scanned.student.name}?`
                        : ''
                }
                confirmLabel="Ya, proses pembayaran"
                onConfirm={() => {
                    if (pendingUuid) {
                        void executePurchase(pendingUuid);
                    }
                }}
            />

            <FormDialog
                open={reverseTarget !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setReverseTarget(null);
                    }
                }}
                title="Koreksi transaksi kantin"
                onSubmit={handleReverse}
                processing={reversing}
                submitLabel="Batalkan transaksi"
            >
                <div className="space-y-3">
                    <div className="space-y-1.5">
                        <Label htmlFor="rev-reason">Alasan koreksi</Label>
                        <Input
                            id="rev-reason"
                            value={reverseReason}
                            onChange={(e) => setReverseReason(e.target.value)}
                            placeholder="Mis. Salah ketik nominal"
                        />
                        {reverseError && (
                            <p className="text-xs text-destructive">
                                {reverseError}
                            </p>
                        )}
                    </div>
                </div>
            </FormDialog>
        </KioskLayout>
    );
}
