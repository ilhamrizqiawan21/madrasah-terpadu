import { Head, usePage } from '@inertiajs/react';
import {
    CreditCard,
    Minus,
    Plus,
    Printer,
    RotateCcw,
    ScanLine,
    Search,
    ShoppingBag,
    Trash2,
} from 'lucide-react';
import type { FormEvent } from 'react';
import { useMemo, useRef, useState } from 'react';
import CanteenPosController from '@/actions/App/Http/Controllers/Canteen/CanteenPosController';
import { FormDialog } from '@/components/app/form-dialog';
import { MoneyText } from '@/components/app/money-text';
import { StatusBadge } from '@/components/app/status-badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import KioskLayout from '@/layouts/kiosk-layout';
import { formatTime } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { Auth } from '@/types';

type Product = {
    id: number;
    unit_id: number;
    category_id: number;
    category: string;
    name: string;
    price: number;
    stock: number | null;
};

type UnitOption = { id: number; code: string; name: string };

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
    vouchers: { total_remaining: number; count: number };
};

type TransactionRow = {
    id: number;
    summary: string | null;
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

type Receipt = {
    number: string;
    items: { name: string; qty: number; price: number; amount: number }[];
    balance_after: number;
    cashier: string;
};

type Props = {
    recentTransactions: TransactionRow[];
    products: Product[];
    units: UnitOption[];
};

type Stage = 'scan' | 'confirm' | 'done';

const UNIT_STORAGE_KEY = 'canteen-pos-unit';
const ALL = 0;

function csrfToken(): string {
    return (
        document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute('content') ?? ''
    );
}

async function postJson<T>(
    url: string,
    body: unknown,
): Promise<{
    ok: boolean;
    data: T & { message?: string; errors?: Record<string, string[]> };
}> {
    const response = await fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
        },
        body: JSON.stringify(body),
    });
    const data = (await response.json()) as T & {
        message?: string;
        errors?: Record<string, string[]>;
    };

    return { ok: response.ok, data };
}

function firstError(
    data: { message?: string; errors?: Record<string, string[]> },
    fallback: string,
): string {
    const first = data.errors ? Object.values(data.errors)[0]?.[0] : undefined;

    return first ?? data.message ?? fallback;
}

export default function CanteenPosIndex({
    recentTransactions: initialTransactions,
    products,
    units,
}: Props) {
    const { auth, branding } = usePage<{
        auth: Auth;
        branding?: { appName?: string };
    }>().props;
    const schoolName = branding?.appName ?? 'Madrasah Terpadu';

    // Menu & keranjang
    const [unitId, setUnitId] = useState<number>(() => {
        const saved = Number(
            typeof window === 'undefined'
                ? 0
                : window.localStorage.getItem(UNIT_STORAGE_KEY),
        );
        return units.some((u) => u.id === saved)
            ? saved
            : (units[0]?.id ?? ALL);
    });
    const [categoryId, setCategoryId] = useState<number>(ALL);
    const [search, setSearch] = useState('');
    const [cart, setCart] = useState<Record<number, number>>({});
    const [manualAmount, setManualAmount] = useState('');
    const [manualNote, setManualNote] = useState('');
    const [stockLeft, setStockLeft] = useState<Record<number, number | null>>(
        () => Object.fromEntries(products.map((p) => [p.id, p.stock])),
    );

    // Pembayaran
    const [payOpen, setPayOpen] = useState(false);
    const [stage, setStage] = useState<Stage>('scan');
    const [token, setToken] = useState('');
    const [scanning, setScanning] = useState(false);
    const [scanError, setScanError] = useState<string | null>(null);
    const [scanned, setScanned] = useState<ScannedStudent | null>(null);
    const [paying, setPaying] = useState(false);
    const [payError, setPayError] = useState<string | null>(null);
    const [clientUuid, setClientUuid] = useState<string | null>(null);
    const [receipt, setReceipt] = useState<
        | (Receipt & { tx: TransactionRow; voucher: number; wallet: number })
        | null
    >(null);
    const scanRef = useRef<HTMLInputElement>(null);

    // Riwayat & koreksi
    const [transactions, setTransactions] =
        useState<TransactionRow[]>(initialTransactions);
    const [reverseTarget, setReverseTarget] = useState<TransactionRow | null>(
        null,
    );
    const [reverseReason, setReverseReason] = useState('');
    const [reverseError, setReverseError] = useState<string | null>(null);
    const [reversing, setReversing] = useState(false);

    const unitProducts = useMemo(
        () => products.filter((p) => unitId === ALL || p.unit_id === unitId),
        [products, unitId],
    );
    const categories = useMemo(() => {
        const seen = new Map<number, string>();
        unitProducts.forEach((p) => seen.set(p.category_id, p.category));
        return [...seen.entries()].map(([id, name]) => ({ id, name }));
    }, [unitProducts]);
    const visibleProducts = unitProducts.filter(
        (p) =>
            (categoryId === ALL || p.category_id === categoryId) &&
            (search.trim() === '' ||
                p.name.toLowerCase().includes(search.trim().toLowerCase())),
    );

    const cartLines = products
        .filter((p) => (cart[p.id] ?? 0) > 0)
        .map((p) => ({
            product: p,
            qty: cart[p.id],
            amount: p.price * cart[p.id],
        }));
    const manual = Number.parseInt(manualAmount || '0', 10) || 0;
    const total = cartLines.reduce((sum, l) => sum + l.amount, 0) + manual;
    const itemCount =
        cartLines.reduce((sum, l) => sum + l.qty, 0) + (manual > 0 ? 1 : 0);

    const changeQty = (product: Product, delta: number) => {
        setCart((prev) => {
            const current = prev[product.id] ?? 0;
            const limit = stockLeft[product.id];
            const next = Math.max(
                0,
                Math.min(current + delta, limit ?? 50, 50),
            );
            const copy = { ...prev };
            if (next === 0) {
                delete copy[product.id];
            } else {
                copy[product.id] = next;
            }
            return copy;
        });
    };

    const clearCart = () => {
        setCart({});
        setManualAmount('');
        setManualNote('');
    };

    // Perkiraan pembagian bayar; server tetap penentu akhir.
    const voucherPart = scanned
        ? Math.min(total, scanned.vouchers.total_remaining)
        : 0;
    const walletPart = total - voucherPart;
    const blocker = !scanned
        ? null
        : scanned.wallet.is_frozen
          ? 'Dompet siswa ini sedang dibekukan oleh wali.'
          : walletPart > scanned.wallet.balance
            ? `Saldo kurang ${(walletPart - scanned.wallet.balance).toLocaleString('id-ID')} rupiah.`
            : walletPart > scanned.wallet.remaining_limit
              ? 'Melebihi sisa limit jajan hari ini.'
              : null;

    const openPay = () => {
        setStage('scan');
        setScanned(null);
        setScanError(null);
        setPayError(null);
        setToken('');
        setClientUuid(crypto.randomUUID());
        setPayOpen(true);
    };

    const handleScan = async (e: FormEvent) => {
        e.preventDefault();
        const value = token.trim();
        if (value === '' || scanning) {
            return;
        }
        setScanning(true);
        setScanError(null);
        try {
            const { ok, data } = await postJson<ScannedStudent>(
                CanteenPosController.lookup.url(),
                { token: value },
            );
            if (!ok) {
                setScanError(data.message ?? 'Kartu tidak dikenali.');
                return;
            }
            setScanned(data);
            setStage('confirm');
        } catch {
            setScanError('Gagal terhubung ke server.');
        } finally {
            setScanning(false);
            setToken('');
        }
    };

    const handlePay = async () => {
        if (!scanned || !clientUuid || paying || blocker) {
            return;
        }
        setPaying(true);
        setPayError(null);
        try {
            const { ok, data } = await postJson<{
                transaction?: TransactionRow;
                receipt?: Receipt;
            }>(CanteenPosController.purchase.url(), {
                student_id: scanned.student.id,
                client_uuid: clientUuid,
                lines: cartLines.map((l) => ({
                    product_id: l.product.id,
                    qty: l.qty,
                })),
                manual_amount: manual,
                manual_note: manualNote,
            });
            if (!ok || !data.transaction || !data.receipt) {
                setPayError(firstError(data, 'Transaksi gagal diproses.'));
                return;
            }
            const tx = data.transaction;
            setTransactions((prev) => [tx, ...prev].slice(0, 15));
            setStockLeft((prev) => {
                const copy = { ...prev };
                cartLines.forEach((l) => {
                    const s = copy[l.product.id];
                    if (s !== null && s !== undefined) {
                        copy[l.product.id] = Math.max(0, s - l.qty);
                    }
                });
                return copy;
            });
            setReceipt({
                ...data.receipt,
                tx,
                voucher: tx.paid_from_vouchers,
                wallet: tx.paid_from_wallet,
            });
            setStage('done');
            clearCart();
        } catch {
            setPayError(
                'Koneksi terputus. Coba lagi; transaksi tidak akan tercatat dua kali.',
            );
        } finally {
            setPaying(false);
        }
    };

    const handleReverse = async (e: FormEvent) => {
        e.preventDefault();
        if (!reverseTarget || reversing) {
            return;
        }
        setReversing(true);
        setReverseError(null);
        try {
            const { ok, data } = await postJson<{
                transaction?: {
                    id: number;
                    status: 'completed' | 'reversed';
                    status_label: string;
                };
            }>(
                CanteenPosController.reverse.url({
                    transaction: reverseTarget.id,
                }),
                { reason: reverseReason },
            );
            if (!ok || !data.transaction) {
                setReverseError(
                    firstError(data, 'Gagal membatalkan transaksi.'),
                );
                return;
            }
            const updated = data.transaction;
            setTransactions((prev) =>
                prev.map((tx) =>
                    tx.id === reverseTarget.id
                        ? {
                              ...tx,
                              status: updated.status,
                              status_label: updated.status_label,
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
    };

    return (
        <KioskLayout
            schoolName={schoolName}
            kioskTitle="Kasir Kantin"
            operatorName={auth.user.name}
        >
            <Head title="Kasir Kantin" />

            <div className="mx-auto grid max-w-7xl gap-4 p-4 sm:p-6 lg:grid-cols-[minmax(0,1fr)_22rem] print:hidden">
                {/* Menu */}
                <section className="space-y-3" aria-label="Daftar menu">
                    <div className="flex flex-wrap items-center gap-2">
                        {units.length > 1 &&
                            units.map((u) => (
                                <Button
                                    key={u.id}
                                    type="button"
                                    size="sm"
                                    variant={
                                        unitId === u.id ? 'default' : 'outline'
                                    }
                                    onClick={() => {
                                        setUnitId(u.id);
                                        setCategoryId(ALL);
                                        window.localStorage.setItem(
                                            UNIT_STORAGE_KEY,
                                            String(u.id),
                                        );
                                    }}
                                >
                                    Kantin {u.code}
                                </Button>
                            ))}
                        <div className="relative min-w-48 flex-1">
                            <Search
                                className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                strokeWidth={1.75}
                            />
                            <Input
                                type="search"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Cari makanan atau minuman"
                                aria-label="Cari menu"
                                className="pl-9"
                            />
                        </div>
                    </div>

                    {categories.length > 1 && (
                        <div
                            className="flex flex-wrap gap-2"
                            role="group"
                            aria-label="Kategori menu"
                        >
                            {[
                                { id: ALL, name: 'Semua menu' },
                                ...categories,
                            ].map((c) => (
                                <Button
                                    key={c.id}
                                    type="button"
                                    size="sm"
                                    variant={
                                        categoryId === c.id ? 'soft' : 'ghost'
                                    }
                                    aria-pressed={categoryId === c.id}
                                    onClick={() => setCategoryId(c.id)}
                                >
                                    {c.name}
                                </Button>
                            ))}
                        </div>
                    )}

                    {products.length === 0 ? (
                        <p className="rounded-xl border border-dashed border-border bg-card px-4 py-10 text-center text-sm text-muted-foreground">
                            Belum ada menu aktif. Bendahara dapat menambahkannya
                            di Kantin → Daftar Menu. Sementara itu, isi nominal
                            di panel pesanan.
                        </p>
                    ) : visibleProducts.length === 0 ? (
                        <p className="rounded-xl border border-dashed border-border bg-card px-4 py-10 text-center text-sm text-muted-foreground">
                            Tidak ada menu yang cocok.
                        </p>
                    ) : (
                        <ul className="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-4">
                            {visibleProducts.map((p) => {
                                const left = stockLeft[p.id];
                                const inCart = cart[p.id] ?? 0;
                                const soldOut =
                                    left !== null &&
                                    left !== undefined &&
                                    left <= inCart;
                                return (
                                    <li key={p.id}>
                                        <button
                                            type="button"
                                            disabled={soldOut && inCart === 0}
                                            onClick={() => changeQty(p, 1)}
                                            className={cn(
                                                'relative flex h-full min-h-24 w-full flex-col justify-between rounded-xl border bg-card p-3 text-left shadow-xs transition-colors',
                                                'hover:border-primary/40 hover:bg-primary/[0.03] focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                                                'disabled:cursor-not-allowed disabled:opacity-50',
                                                inCart > 0
                                                    ? 'border-primary/50 bg-primary/[0.04]'
                                                    : 'border-border',
                                            )}
                                            aria-label={`Tambah ${p.name}`}
                                        >
                                            <span className="pr-7 text-sm leading-snug font-medium">
                                                {p.name}
                                            </span>
                                            <span className="mt-2 flex items-end justify-between gap-2">
                                                <MoneyText
                                                    amount={p.price}
                                                    className="font-semibold"
                                                />
                                                {left !== null &&
                                                    left !== undefined && (
                                                        <span
                                                            className={cn(
                                                                'text-xs',
                                                                left - inCart <=
                                                                    3
                                                                    ? 'text-destructive'
                                                                    : 'text-muted-foreground',
                                                            )}
                                                        >
                                                            {left - inCart <= 0
                                                                ? 'Habis'
                                                                : `Sisa ${left - inCart}`}
                                                        </span>
                                                    )}
                                            </span>
                                            {inCart > 0 && (
                                                <span className="absolute top-2 right-2 grid size-6 place-items-center rounded-full bg-primary text-xs font-semibold text-primary-foreground tabular-nums">
                                                    {inCart}
                                                </span>
                                            )}
                                        </button>
                                    </li>
                                );
                            })}
                        </ul>
                    )}
                </section>

                {/* Pesanan */}
                <aside className="lg:sticky lg:top-4 lg:self-start">
                    <section
                        className="flex flex-col rounded-xl border border-border bg-card shadow-xs"
                        aria-label="Pesanan"
                    >
                        <header className="flex items-center justify-between border-b border-border px-4 py-3">
                            <h2 className="flex items-center gap-2 text-sm font-semibold">
                                <ShoppingBag
                                    className="size-4"
                                    strokeWidth={1.75}
                                />
                                Pesanan
                            </h2>
                            {itemCount > 0 && (
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="ghost"
                                    onClick={clearCart}
                                >
                                    <Trash2
                                        className="size-4"
                                        strokeWidth={1.75}
                                    />
                                    Kosongkan
                                </Button>
                            )}
                        </header>

                        <div className="max-h-[45vh] divide-y divide-border overflow-y-auto">
                            {cartLines.length === 0 && manual === 0 ? (
                                <p className="px-4 py-8 text-center text-sm text-muted-foreground">
                                    Ketuk menu untuk menambahkan.
                                </p>
                            ) : (
                                cartLines.map((l) => (
                                    <div
                                        key={l.product.id}
                                        className="flex items-center gap-2 px-4 py-2.5"
                                    >
                                        <div className="min-w-0 flex-1">
                                            <p className="truncate text-sm font-medium">
                                                {l.product.name}
                                            </p>
                                            <p className="text-xs text-muted-foreground tabular-nums">
                                                <MoneyText
                                                    amount={l.product.price}
                                                />{' '}
                                                × {l.qty}
                                            </p>
                                        </div>
                                        <div className="flex items-center gap-1">
                                            <Button
                                                type="button"
                                                size="icon"
                                                variant="outline"
                                                className="size-8"
                                                aria-label={`Kurangi ${l.product.name}`}
                                                onClick={() =>
                                                    changeQty(l.product, -1)
                                                }
                                            >
                                                <Minus className="size-3.5" />
                                            </Button>
                                            <span className="w-6 text-center text-sm tabular-nums">
                                                {l.qty}
                                            </span>
                                            <Button
                                                type="button"
                                                size="icon"
                                                variant="outline"
                                                className="size-8"
                                                aria-label={`Tambah ${l.product.name}`}
                                                onClick={() =>
                                                    changeQty(l.product, 1)
                                                }
                                            >
                                                <Plus className="size-3.5" />
                                            </Button>
                                        </div>
                                        <MoneyText
                                            amount={l.amount}
                                            className="w-20 text-right text-sm font-medium"
                                        />
                                    </div>
                                ))
                            )}
                        </div>

                        <div className="space-y-2 border-t border-border px-4 py-3">
                            <Label
                                htmlFor="pos-manual"
                                className="text-xs text-muted-foreground"
                            >
                                Nominal lain (di luar menu, opsional)
                            </Label>
                            <div className="flex gap-2">
                                <Input
                                    id="pos-manual"
                                    inputMode="numeric"
                                    value={manualAmount}
                                    onChange={(e) =>
                                        setManualAmount(
                                            e.target.value
                                                .replace(/\D/g, '')
                                                .slice(0, 7),
                                        )
                                    }
                                    placeholder="Rp"
                                    className="w-28 tabular-nums"
                                />
                                <Input
                                    value={manualNote}
                                    onChange={(e) =>
                                        setManualNote(
                                            e.target.value.slice(0, 60),
                                        )
                                    }
                                    placeholder="Keterangan, mis. Gorengan"
                                    aria-label="Keterangan nominal lain"
                                    className="flex-1"
                                />
                            </div>
                        </div>

                        <footer className="space-y-3 border-t border-border px-4 py-4">
                            <div className="flex items-baseline justify-between">
                                <span className="text-sm text-muted-foreground">
                                    Total belanja
                                </span>
                                <MoneyText
                                    amount={total}
                                    className="text-2xl font-semibold"
                                />
                            </div>
                            <Button
                                type="button"
                                className="min-h-12 w-full text-base font-semibold"
                                disabled={total < 100}
                                onClick={openPay}
                            >
                                <CreditCard
                                    className="size-5"
                                    strokeWidth={1.75}
                                />
                                Bayar dengan kartu siswa
                            </Button>
                        </footer>
                    </section>
                </aside>

                {/* Riwayat hari ini */}
                <section
                    className="rounded-xl border border-border bg-card p-4 shadow-xs lg:col-span-2"
                    aria-label="Transaksi hari ini"
                >
                    <h2 className="text-sm font-semibold">
                        Transaksi kantin hari ini
                    </h2>
                    {transactions.length === 0 ? (
                        <p className="mt-3 text-sm text-muted-foreground">
                            Belum ada transaksi hari ini.
                        </p>
                    ) : (
                        <div className="mt-3 overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead className="text-xs text-muted-foreground">
                                    <tr className="border-b border-border text-left">
                                        <th className="py-2 pr-2 font-medium">
                                            Waktu
                                        </th>
                                        <th className="px-2 py-2 font-medium">
                                            Siswa
                                        </th>
                                        <th className="hidden px-2 py-2 font-medium md:table-cell">
                                            Pesanan
                                        </th>
                                        <th className="px-2 py-2 text-right font-medium">
                                            Total
                                        </th>
                                        <th className="hidden px-2 py-2 text-right font-medium sm:table-cell">
                                            Voucher
                                        </th>
                                        <th className="px-2 py-2 text-center font-medium">
                                            Status
                                        </th>
                                        <th className="py-2 pl-2 text-right font-medium">
                                            Aksi
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border">
                                    {transactions.map((tx) => (
                                        <tr key={tx.id}>
                                            <td className="py-2 pr-2 text-xs text-muted-foreground tabular-nums">
                                                {formatTime(tx.created_at)}
                                            </td>
                                            <td className="px-2 py-2">
                                                <div className="font-medium">
                                                    {tx.student_name}
                                                </div>
                                                <div className="text-xs text-muted-foreground">
                                                    {tx.student_nis}
                                                </div>
                                            </td>
                                            <td className="hidden max-w-64 truncate px-2 py-2 text-muted-foreground md:table-cell">
                                                {tx.summary ?? '—'}
                                            </td>
                                            <td className="px-2 py-2 text-right font-medium">
                                                <MoneyText amount={tx.total} />
                                            </td>
                                            <td className="hidden px-2 py-2 text-right text-muted-foreground sm:table-cell">
                                                {tx.paid_from_vouchers > 0 ? (
                                                    <MoneyText
                                                        amount={
                                                            tx.paid_from_vouchers
                                                        }
                                                    />
                                                ) : (
                                                    '—'
                                                )}
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
                                            <td className="py-2 pl-2 text-right">
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

            {/* Dialog pembayaran: tempel kartu → konfirmasi → struk */}
            <Dialog
                open={payOpen}
                onOpenChange={(o) => !paying && setPayOpen(o)}
            >
                <DialogContent
                    className="sm:max-w-md print:hidden"
                    onOpenAutoFocus={(e) => {
                        e.preventDefault();
                        scanRef.current?.focus();
                    }}
                >
                    {stage === 'scan' && (
                        <>
                            <DialogHeader>
                                <DialogTitle>Tempel kartu siswa</DialogTitle>
                                <DialogDescription>
                                    Total{' '}
                                    <MoneyText
                                        amount={total}
                                        className="font-semibold text-foreground"
                                    />
                                    . Tempel RFID atau scan QR/barcode kartu.
                                </DialogDescription>
                            </DialogHeader>
                            <form onSubmit={handleScan} className="space-y-3">
                                <div className="flex gap-2">
                                    <Input
                                        ref={scanRef}
                                        value={token}
                                        onChange={(e) =>
                                            setToken(e.target.value)
                                        }
                                        placeholder="Menunggu kartu…"
                                        aria-label="Kode kartu siswa"
                                        autoComplete="off"
                                        className="min-h-11"
                                    />
                                    <Button
                                        type="submit"
                                        disabled={scanning}
                                        className="min-h-11"
                                    >
                                        <ScanLine className="size-4" />
                                        Cek
                                    </Button>
                                </div>
                                {scanError && (
                                    <p
                                        role="alert"
                                        className="rounded-lg border border-destructive/40 bg-destructive/10 px-3 py-2 text-sm text-destructive"
                                    >
                                        {scanError}
                                    </p>
                                )}
                            </form>
                        </>
                    )}

                    {stage === 'confirm' && scanned && (
                        <>
                            <DialogHeader>
                                <DialogTitle>
                                    {scanned.student.name}
                                </DialogTitle>
                                <DialogDescription>
                                    {scanned.student.nis} ·{' '}
                                    {scanned.student.unit} ·{' '}
                                    {scanned.student.classroom}
                                </DialogDescription>
                            </DialogHeader>
                            <dl className="grid grid-cols-3 gap-2 rounded-lg border border-border bg-muted/30 p-3 text-sm">
                                <div>
                                    <dt className="text-xs text-muted-foreground">
                                        Saldo
                                    </dt>
                                    <dd className="font-semibold">
                                        <MoneyText
                                            amount={scanned.wallet.balance}
                                        />
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-xs text-muted-foreground">
                                        Voucher ({scanned.vouchers.count})
                                    </dt>
                                    <dd className="font-semibold text-primary">
                                        <MoneyText
                                            amount={
                                                scanned.vouchers.total_remaining
                                            }
                                        />
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-xs text-muted-foreground">
                                        Sisa limit
                                    </dt>
                                    <dd className="font-semibold">
                                        <MoneyText
                                            amount={
                                                scanned.wallet.remaining_limit
                                            }
                                        />
                                    </dd>
                                </div>
                            </dl>
                            <dl className="space-y-1.5 text-sm">
                                <div className="flex justify-between">
                                    <dt className="text-muted-foreground">
                                        Total belanja
                                    </dt>
                                    <dd className="font-semibold">
                                        <MoneyText amount={total} />
                                    </dd>
                                </div>
                                {voucherPart > 0 && (
                                    <div className="flex justify-between">
                                        <dt className="text-muted-foreground">
                                            Dibayar voucher reward
                                        </dt>
                                        <dd className="text-primary">
                                            − <MoneyText amount={voucherPart} />
                                        </dd>
                                    </div>
                                )}
                                <div className="flex justify-between border-t border-border pt-1.5">
                                    <dt className="text-muted-foreground">
                                        Dipotong dari saldo
                                    </dt>
                                    <dd className="font-semibold">
                                        <MoneyText amount={walletPart} />
                                    </dd>
                                </div>
                            </dl>
                            {(blocker || payError) && (
                                <p
                                    role="alert"
                                    className="rounded-lg border border-destructive/40 bg-destructive/10 px-3 py-2 text-sm text-destructive"
                                >
                                    {payError ?? blocker}
                                </p>
                            )}
                            <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                                <Button
                                    type="button"
                                    variant="outline"
                                    disabled={paying}
                                    onClick={() => {
                                        setStage('scan');
                                        setScanned(null);
                                        setPayError(null);
                                    }}
                                >
                                    Ganti kartu
                                </Button>
                                <Button
                                    type="button"
                                    disabled={paying || blocker !== null}
                                    onClick={() => void handlePay()}
                                    className="min-h-11"
                                >
                                    {paying
                                        ? 'Memproses…'
                                        : 'Selesaikan pembayaran'}
                                </Button>
                            </div>
                        </>
                    )}

                    {stage === 'done' && receipt && (
                        <>
                            <DialogHeader>
                                <DialogTitle>Pembayaran berhasil</DialogTitle>
                                <DialogDescription>
                                    {receipt.tx.student_name} · sisa saldo{' '}
                                    <MoneyText
                                        amount={receipt.balance_after}
                                        className="font-semibold text-foreground"
                                    />
                                    . Wali sudah diberi tahu.
                                </DialogDescription>
                            </DialogHeader>
                            <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                                <Button
                                    type="button"
                                    variant="soft"
                                    onClick={() => window.print()}
                                >
                                    <Printer
                                        className="size-4"
                                        strokeWidth={1.75}
                                    />
                                    Cetak struk
                                </Button>
                                <Button
                                    type="button"
                                    onClick={() => setPayOpen(false)}
                                    autoFocus
                                >
                                    Transaksi baru
                                </Button>
                            </div>
                        </>
                    )}
                </DialogContent>
            </Dialog>

            {/* Struk 58 mm, hanya tampil saat dicetak */}
            {receipt && (
                <div className="hidden font-mono text-[11px] leading-snug text-black print:block print:w-[58mm]">
                    <p className="text-center font-bold">{schoolName}</p>
                    <p className="text-center">Struk Kantin</p>
                    <p className="mt-1">{receipt.number}</p>
                    <p>
                        {new Date(receipt.tx.created_at).toLocaleString(
                            'id-ID',
                            { timeZone: 'Asia/Makassar' },
                        )}{' '}
                        WITA
                    </p>
                    <p>Siswa: {receipt.tx.student_name}</p>
                    <p>Kasir: {receipt.cashier}</p>
                    <hr className="my-1 border-dashed border-black" />
                    {receipt.items.map((i, idx) => (
                        <div key={idx}>
                            <p>{i.name}</p>
                            <p className="flex justify-between">
                                <span>
                                    {i.qty} × {i.price.toLocaleString('id-ID')}
                                </span>
                                <span>{i.amount.toLocaleString('id-ID')}</span>
                            </p>
                        </div>
                    ))}
                    <hr className="my-1 border-dashed border-black" />
                    <p className="flex justify-between font-bold">
                        <span>Total</span>
                        <span>{receipt.tx.total.toLocaleString('id-ID')}</span>
                    </p>
                    {receipt.voucher > 0 && (
                        <p className="flex justify-between">
                            <span>Voucher</span>
                            <span>
                                {receipt.voucher.toLocaleString('id-ID')}
                            </span>
                        </p>
                    )}
                    <p className="flex justify-between">
                        <span>Saldo dipotong</span>
                        <span>{receipt.wallet.toLocaleString('id-ID')}</span>
                    </p>
                    <p className="flex justify-between">
                        <span>Sisa saldo</span>
                        <span>
                            {receipt.balance_after.toLocaleString('id-ID')}
                        </span>
                    </p>
                    <p className="mt-2 text-center">Terima kasih</p>
                </div>
            )}

            <FormDialog
                open={reverseTarget !== null}
                onOpenChange={(o) => !o && setReverseTarget(null)}
                title="Koreksi transaksi kantin"
                description={
                    reverseTarget
                        ? `Saldo, voucher, dan stok menu dikembalikan. Transaksi ${reverseTarget.student_name} tetap tercatat sebagai dibatalkan.`
                        : undefined
                }
                onSubmit={handleReverse}
                processing={reversing}
                submitLabel="Batalkan transaksi"
            >
                <div className="space-y-1.5">
                    <Label htmlFor="rev-reason">Alasan koreksi</Label>
                    <Input
                        id="rev-reason"
                        value={reverseReason}
                        onChange={(e) => setReverseReason(e.target.value)}
                        placeholder="Mis. Salah pilih menu"
                        required
                    />
                    {reverseError && (
                        <p className="text-xs text-destructive">
                            {reverseError}
                        </p>
                    )}
                </div>
            </FormDialog>
        </KioskLayout>
    );
}
