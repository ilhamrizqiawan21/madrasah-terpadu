import { Head, router, useForm } from '@inertiajs/react';
import { Lock, Unlock } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import WalletController from '@/actions/App/Http/Controllers/Guardian/WalletController';
import { ConfirmDialog } from '@/components/app/confirm-dialog';
import { DateText } from '@/components/app/date-text';
import { MoneyText } from '@/components/app/money-text';
import { StatusBadge } from '@/components/app/status-badge';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import GuardianLayout from '@/layouts/guardian-layout';
import { formatTime } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { PaginatedResource } from '@/types';

type EntryRow = {
    id: number;
    type: string;
    type_label: string;
    amount: number;
    balance_after: number;
    description: string | null;
    created_at: string;
};

type Props = {
    student: { id: number; name: string; nis: string };
    wallet: {
        balance: number;
        daily_limit: number;
        spent_today: number;
        is_frozen: boolean;
        max_daily_limit: number;
    };
    vouchers: {
        id: number;
        amount: number;
        remaining: number;
        expires_on: string;
    }[];
    entries: PaginatedResource<EntryRow>;
};

export default function GuardianWallet({
    student,
    wallet,
    vouchers,
    entries,
}: Props) {
    const [freezeOpen, setFreezeOpen] = useState(false);

    const limitForm = useForm({
        daily_limit: String(wallet.daily_limit),
    });

    function submitLimit(e: FormEvent) {
        e.preventDefault();
        limitForm.patch(
            WalletController.updateLimit.url({ student: student.id }),
            { preserveScroll: true },
        );
    }

    function confirmToggleFreeze() {
        router.patch(
            WalletController.toggleFreeze({ student: student.id }),
            { is_frozen: !wallet.is_frozen },
            {
                preserveScroll: true,
                onSuccess: () => setFreezeOpen(false),
            },
        );
    }

    return (
        <GuardianLayout>
            <Head title={`Dompet — ${student.name}`} />

            <div className="space-y-4">
                <section className="rounded-xl border border-border bg-card p-4 shadow-xs">
                    <div className="flex items-start justify-between gap-2">
                        <div>
                            <p className="text-xs text-muted-foreground">
                                Dompet kantin · {student.name}
                            </p>
                            <p className="mt-1 text-2xl font-semibold">
                                <MoneyText amount={wallet.balance} />
                            </p>
                        </div>
                        <StatusBadge
                            variant={
                                wallet.is_frozen ? 'destructive' : 'success'
                            }
                        >
                            {wallet.is_frozen ? 'Dibekukan' : 'Aktif'}
                        </StatusBadge>
                    </div>

                    <div className="mt-4 grid grid-cols-2 gap-3 border-t border-border pt-3 text-sm">
                        <div>
                            <p className="text-xs text-muted-foreground">
                                Jajan hari ini
                            </p>
                            <p className="font-semibold">
                                <MoneyText amount={wallet.spent_today} />
                            </p>
                        </div>
                        <div>
                            <p className="text-xs text-muted-foreground">
                                Limit harian
                            </p>
                            <p className="font-semibold">
                                <MoneyText amount={wallet.daily_limit} />
                            </p>
                        </div>
                    </div>

                    <div className="mt-4">
                        <Button
                            type="button"
                            variant={wallet.is_frozen ? 'default' : 'outline'}
                            className="min-h-11 w-full"
                            onClick={() => setFreezeOpen(true)}
                        >
                            {wallet.is_frozen ? (
                                <>
                                    <Unlock className="size-4" />
                                    Aktifkan kembali dompet
                                </>
                            ) : (
                                <>
                                    <Lock className="size-4" />
                                    Bekukan dompet sementara
                                </>
                            )}
                        </Button>
                    </div>
                </section>

                <section className="rounded-xl border border-border bg-card p-4 shadow-xs">
                    <h2 className="text-sm font-semibold">
                        Ubah limit jajan harian
                    </h2>
                    <form onSubmit={submitLimit} className="mt-3 space-y-3">
                        <div className="space-y-1.5">
                            <Label htmlFor="daily-limit">
                                Batas maksimal per hari (maks{' '}
                                <MoneyText amount={wallet.max_daily_limit} />)
                            </Label>
                            <Input
                                id="daily-limit"
                                type="number"
                                min={0}
                                max={wallet.max_daily_limit}
                                value={limitForm.data.daily_limit}
                                onChange={(e) =>
                                    limitForm.setData(
                                        'daily_limit',
                                        e.target.value,
                                    )
                                }
                                className="min-h-11"
                            />
                            <InputError
                                message={limitForm.errors.daily_limit}
                            />
                        </div>
                        <Button
                            type="submit"
                            className="min-h-11 w-full"
                            disabled={limitForm.processing}
                        >
                            Simpan limit
                        </Button>
                    </form>
                </section>

                {vouchers.length > 0 && (
                    <section className="rounded-xl border border-border bg-card p-4 shadow-xs">
                        <h2 className="text-sm font-semibold">
                            Voucher kantin aktif
                        </h2>
                        <ul className="mt-2 divide-y divide-border text-sm">
                            {vouchers.map((v) => (
                                <li
                                    key={v.id}
                                    className="flex items-center justify-between py-2"
                                >
                                    <span className="text-xs text-muted-foreground">
                                        Berlaku s.d.{' '}
                                        <DateText value={v.expires_on} />
                                    </span>
                                    <span className="font-semibold text-primary">
                                        <MoneyText amount={v.remaining} />
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </section>
                )}

                <section className="rounded-xl border border-border bg-card p-4 shadow-xs">
                    <h2 className="text-sm font-semibold">Riwayat dompet</h2>
                    {entries.data.length === 0 ? (
                        <p className="mt-2 text-sm text-muted-foreground">
                            Belum ada transaksi dompet.
                        </p>
                    ) : (
                        <ul className="mt-2 divide-y divide-border text-sm">
                            {entries.data.map((entry) => (
                                <li
                                    key={entry.id}
                                    className="flex items-start justify-between gap-3 py-2.5"
                                >
                                    <div className="min-w-0">
                                        <p className="font-medium">
                                            {entry.type_label}
                                        </p>
                                        {entry.description && (
                                            <p className="truncate text-xs text-muted-foreground">
                                                {entry.description}
                                            </p>
                                        )}
                                        <p className="text-xs text-muted-foreground">
                                            <DateText
                                                value={entry.created_at}
                                            />{' '}
                                            {formatTime(entry.created_at)}
                                        </p>
                                    </div>
                                    <div className="shrink-0 text-right">
                                        <p
                                            className={cn(
                                                'font-semibold',
                                                entry.amount > 0
                                                    ? 'text-primary'
                                                    : 'text-foreground',
                                            )}
                                        >
                                            {entry.amount > 0 ? '+' : ''}
                                            <MoneyText amount={entry.amount} />
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            Saldo{' '}
                                            <MoneyText
                                                amount={entry.balance_after}
                                            />
                                        </p>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            </div>

            <ConfirmDialog
                open={freezeOpen}
                onOpenChange={setFreezeOpen}
                title={
                    wallet.is_frozen
                        ? 'Aktifkan kembali dompet?'
                        : 'Bekukan dompet sementara?'
                }
                description={
                    wallet.is_frozen
                        ? `Kartu ${student.name} akan dapat digunakan kembali di kantin.`
                        : `Kartu ${student.name} tidak akan dapat digunakan untuk jajan di kantin sampai diaktifkan kembali.`
                }
                confirmLabel={
                    wallet.is_frozen ? 'Aktifkan kembali' : 'Bekukan dompet'
                }
                variant={wallet.is_frozen ? 'default' : 'destructive'}
                onConfirm={confirmToggleFreeze}
            />
        </GuardianLayout>
    );
}
