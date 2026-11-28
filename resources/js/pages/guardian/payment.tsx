import { Head, Link, router, usePoll } from '@inertiajs/react';
import {
    ArrowLeft,
    CircleAlert,
    CircleCheck,
    Clock,
    FileDown,
    RefreshCw,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { useEffect, useState } from 'react';
import ReceiptController from '@/actions/App/Http/Controllers/Billing/ReceiptController';
import BillingController from '@/actions/App/Http/Controllers/Guardian/BillingController';
import PaymentController from '@/actions/App/Http/Controllers/Guardian/PaymentController';
import WalletController from '@/actions/App/Http/Controllers/Guardian/WalletController';
import { MoneyText } from '@/components/app/money-text';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import GuardianLayout from '@/layouts/guardian-layout';

type OrderItem = {
    id: number;
    description: string;
    amount: number;
};

type Order = {
    order_id: string;
    purpose: 'invoices' | 'wallet_topup';
    purpose_label: string;
    student_id: number;
    student_name: string;
    gross_amount: number;
    status: 'pending' | 'paid' | 'expired' | 'failed' | 'cancelled';
    status_label: string;
    awaiting_payment: boolean;
    expires_at: string;
    paid_at: string | null;
    payment_id: number | null;
    items: OrderItem[];
};

type Snap = {
    token: string;
    redirect_url: string | null;
    client_key: string;
    script_url: string;
};

type Props = {
    view: 'confirm' | 'status';
    order: Order;
    snap: Snap | null;
};

type SnapCallbacks = {
    onSuccess?: () => void;
    onPending?: () => void;
    onError?: () => void;
    onClose?: () => void;
};

declare global {
    interface Window {
        snap?: {
            pay: (token: string, callbacks: SnapCallbacks) => void;
        };
    }
}

const POLL_INTERVAL_MS = 5_000;
const POLL_LIMIT_MS = 120_000;
const SNAP_SCRIPT_ID = 'midtrans-snap';

export default function GuardianPayment({ view, order, snap }: Props) {
    const title =
        view === 'confirm' ? 'Konfirmasi pembayaran' : 'Status pembayaran';

    return (
        <GuardianLayout title={title} requireChild={false}>
            <Head title={title} />

            <div className="space-y-4">
                <Link
                    href={backHref(order)}
                    className="inline-flex min-h-11 items-center gap-2 rounded-md text-sm font-medium text-muted-foreground transition-colors hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                >
                    <ArrowLeft className="size-4" strokeWidth={1.75} />
                    {order.purpose === 'wallet_topup'
                        ? 'Kembali ke dompet'
                        : 'Kembali ke tagihan'}
                </Link>

                {view === 'confirm' && snap ? (
                    <ConfirmView order={order} snap={snap} />
                ) : (
                    <StatusView order={order} />
                )}
            </div>
        </GuardianLayout>
    );
}

GuardianPayment.layout = (page: ReactNode) => page;

function backHref(order: Order): string {
    return order.purpose === 'wallet_topup'
        ? WalletController.index.url(order.student_id)
        : BillingController.url();
}

function OrderSummary({ order }: { order: Order }) {
    return (
        <div className="rounded-lg border border-border bg-card shadow-xs">
            <div className="border-b border-border px-4 py-3">
                <div className="text-xs text-muted-foreground">
                    {order.purpose_label} · {order.student_name}
                </div>
            </div>
            <ul className="divide-y divide-border">
                {order.items.map((item) => (
                    <li
                        key={item.id}
                        className="flex items-start justify-between gap-3 px-4 py-3 text-sm"
                    >
                        <span className="text-foreground">
                            {item.description}
                        </span>
                        <MoneyText amount={item.amount} />
                    </li>
                ))}
            </ul>
            <div className="flex items-center justify-between gap-3 border-t border-border px-4 py-3">
                <span className="font-medium">Total</span>
                <MoneyText
                    amount={order.gross_amount}
                    className="text-xl font-semibold"
                />
            </div>
        </div>
    );
}

function ConfirmView({ order, snap }: { order: Order; snap: Snap }) {
    const [scriptState, setScriptState] = useState<
        'loading' | 'ready' | 'failed'
    >(() =>
        typeof window !== 'undefined' && window.snap ? 'ready' : 'loading',
    );
    const [opening, setOpening] = useState(false);

    // snap.js hanya dimuat di halaman ini (PAYMENTS §2); CSP mengizinkan domain Midtrans.
    useEffect(() => {
        if (window.snap) {
            setScriptState('ready');

            return;
        }

        const existing = document.getElementById(SNAP_SCRIPT_ID);
        const script =
            existing instanceof HTMLScriptElement
                ? existing
                : document.createElement('script');

        const onLoad = () => setScriptState('ready');
        const onError = () => setScriptState('failed');
        script.addEventListener('load', onLoad);
        script.addEventListener('error', onError);

        if (!existing) {
            script.id = SNAP_SCRIPT_ID;
            script.src = snap.script_url;
            script.async = true;
            script.dataset.clientKey = snap.client_key;
            document.body.appendChild(script);
        }

        return () => {
            script.removeEventListener('load', onLoad);
            script.removeEventListener('error', onError);
        };
    }, [snap.script_url, snap.client_key]);

    const goToStatus = () => {
        router.visit(PaymentController.finish.url(order.order_id));
    };

    const pay = () => {
        if (!window.snap) {
            setScriptState('failed');

            return;
        }

        setOpening(true);
        // Callback Snap hanya untuk navigasi; status lunas datang dari webhook (PAYMENTS §1.3).
        window.snap.pay(snap.token, {
            onSuccess: goToStatus,
            onPending: goToStatus,
            onError: goToStatus,
            onClose: () => setOpening(false),
        });
    };

    return (
        <div className="space-y-4">
            <OrderSummary order={order} />
            <p className="text-xs text-muted-foreground">
                Biaya layanan mengikuti metode yang dipilih di Midtrans.
            </p>

            {scriptState === 'failed' ? (
                <div
                    role="alert"
                    className="space-y-3 rounded-lg border border-border bg-card p-4 text-sm"
                >
                    <p>
                        Halaman pembayaran Midtrans tidak dapat dimuat di
                        aplikasi. Periksa koneksi internet, lalu coba lagi.
                    </p>
                    {snap.redirect_url && (
                        <Button asChild variant="outline" className="min-h-11">
                            <a href={snap.redirect_url} rel="noreferrer">
                                Buka halaman Midtrans
                            </a>
                        </Button>
                    )}
                </div>
            ) : (
                <div className="space-y-2">
                    <Button
                        type="button"
                        className="min-h-12 w-full text-base"
                        onClick={pay}
                        disabled={scriptState !== 'ready' || opening}
                    >
                        {(scriptState === 'loading' || opening) && <Spinner />}
                        Lanjut ke pembayaran
                    </Button>
                    <p className="text-center text-xs text-muted-foreground">
                        Anda akan diarahkan ke halaman Midtrans.
                    </p>
                </div>
            )}
        </div>
    );
}

function StatusView({ order }: { order: Order }) {
    const isPending = order.status === 'pending';
    const [timedOut, setTimedOut] = useState(false);

    const { stop } = usePoll(
        POLL_INTERVAL_MS,
        { only: ['order'] },
        { autoStart: isPending },
    );

    useEffect(() => {
        if (!isPending) {
            stop();

            return;
        }

        const timer = window.setTimeout(() => {
            stop();
            setTimedOut(true);
        }, POLL_LIMIT_MS);

        return () => window.clearTimeout(timer);
    }, [isPending, stop]);

    return (
        <div className="space-y-4">
            <div
                className="flex flex-col items-center gap-2 px-2 py-6 text-center"
                aria-live="polite"
            >
                <StatusIcon status={order.status} />
                <h2 className="text-xl font-semibold">
                    {statusHeading(order.status)}
                </h2>
                <p className="max-w-sm text-sm text-muted-foreground">
                    {statusDescription(order)}
                </p>
            </div>

            <dl className="space-y-2 rounded-lg border border-border bg-card p-4 text-sm shadow-xs">
                <div className="flex justify-between gap-3">
                    <dt className="text-muted-foreground">Order</dt>
                    <dd className="font-mono text-xs break-all">
                        {order.order_id}
                    </dd>
                </div>
                <div className="flex justify-between gap-3">
                    <dt className="text-muted-foreground">Siswa</dt>
                    <dd>{order.student_name}</dd>
                </div>
                <div className="flex justify-between gap-3">
                    <dt className="text-muted-foreground">Total</dt>
                    <dd>
                        <MoneyText
                            amount={order.gross_amount}
                            className="font-semibold"
                        />
                    </dd>
                </div>
            </dl>

            {isPending && timedOut && (
                <Button
                    type="button"
                    variant="outline"
                    className="min-h-11 w-full"
                    onClick={() =>
                        router.reload({
                            only: ['order'],
                            onFinish: () => setTimedOut(false),
                        })
                    }
                >
                    <RefreshCw className="size-4" strokeWidth={1.75} />
                    Periksa lagi
                </Button>
            )}

            {isPending && order.awaiting_payment && (
                <Button asChild variant="outline" className="min-h-11 w-full">
                    <Link href={PaymentController.show.url(order.order_id)}>
                        Lanjutkan pembayaran
                    </Link>
                </Button>
            )}

            {order.status === 'paid' && order.payment_id !== null && (
                <Button asChild variant="outline" className="min-h-11 w-full">
                    <a
                        href={ReceiptController.guardian.url([
                            order.student_id,
                            order.payment_id,
                        ])}
                        target="_blank"
                        rel="noreferrer"
                    >
                        <FileDown className="size-4" strokeWidth={1.75} />
                        Unduh kuitansi
                    </a>
                </Button>
            )}

            <Button
                asChild
                variant={isPending ? 'ghost' : 'default'}
                className="min-h-11 w-full"
            >
                <Link href={backHref(order)}>
                    {order.purpose === 'wallet_topup'
                        ? 'Kembali ke dompet'
                        : 'Kembali ke tagihan'}
                </Link>
            </Button>
        </div>
    );
}

function StatusIcon({ status }: { status: Order['status'] }) {
    if (status === 'paid') {
        return (
            <CircleCheck
                className="size-10 text-success"
                strokeWidth={1.75}
                aria-hidden
            />
        );
    }

    if (status === 'pending') {
        return (
            <Clock
                className="size-10 text-accent-attention"
                strokeWidth={1.75}
                aria-hidden
            />
        );
    }

    return (
        <CircleAlert
            className="size-10 text-muted-foreground"
            strokeWidth={1.75}
            aria-hidden
        />
    );
}

function statusHeading(status: Order['status']): string {
    switch (status) {
        case 'paid':
            return 'Pembayaran berhasil';
        case 'pending':
            return 'Menunggu konfirmasi';
        case 'expired':
            return 'Waktu pembayaran habis';
        default:
            return 'Pembayaran tidak selesai';
    }
}

function statusDescription(order: Order): string {
    const subject =
        order.purpose === 'wallet_topup'
            ? 'Saldo kantin bertambah'
            : 'Tagihan ditandai lunas';

    switch (order.status) {
        case 'paid':
            return order.purpose === 'wallet_topup'
                ? 'Saldo kantin sudah bertambah.'
                : 'Tagihan sudah ditandai lunas.';
        case 'pending':
            return `${subject} setelah Midtrans mengirim konfirmasi ke sekolah.`;
        case 'expired':
            return 'Order ini sudah kedaluwarsa. Buat pembayaran baru dari halaman tagihan.';
        default:
            return 'Pembayaran untuk order ini tidak berhasil. Silakan buat pembayaran baru.';
    }
}
