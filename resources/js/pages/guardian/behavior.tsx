import { Head, router } from '@inertiajs/react';
import { BookOpenCheck, Gift, ShieldAlert, Ticket } from 'lucide-react';
import { useState } from 'react';
import BehaviorController from '@/actions/App/Http/Controllers/Guardian/BehaviorController';
import { DateText } from '@/components/app/date-text';
import { EmptyState } from '@/components/app/empty-state';
import { MoneyText } from '@/components/app/money-text';
import type { StatusBadgeVariant } from '@/components/app/status-badge';
import { StatusBadge } from '@/components/app/status-badge';
import { Button } from '@/components/ui/button';
import GuardianLayout from '@/layouts/guardian-layout';

type RecordItem = {
    id: number;
    rule_name: string;
    category: 'violation' | 'achievement' | 'good_deed';
    category_label: string;
    points: number;
    occurred_on: string;
    note: string | null;
};

type FollowupItem = {
    id: number;
    action_name: string;
    threshold_points: number;
    status: 'open' | 'done';
    triggered_at: string;
};

type OfferItem = {
    id: number;
    name: string;
    cost_points: number;
    voucher_amount: number;
    voucher_valid_days: number;
    eligible: boolean;
};

type VoucherItem = {
    id: number;
    offer_name: string;
    amount: number;
    remaining: number;
    expires_on: string;
};

type RedemptionItem = {
    id: number;
    offer_name: string;
    cost_points: number;
    status: 'pending' | 'approved' | 'rejected' | 'cancelled';
    decision_note: string | null;
};

type Props = {
    student: { id: number; name: string; nis: string };
    summary: {
        violation_points: number;
        merit_points: number;
        academic_year: string | null;
    };
    records: RecordItem[];
    followups: FollowupItem[];
    offers: OfferItem[];
    vouchers: VoucherItem[];
    redemptions: RedemptionItem[];
};

function categoryVariant(category: RecordItem['category']): StatusBadgeVariant {
    if (category === 'violation') {
        return 'destructive';
    }
    if (category === 'achievement') {
        return 'success';
    }

    return 'muted';
}

function redemptionMeta(status: RedemptionItem['status']): {
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

    return { label: 'Menunggu persetujuan BK', variant: 'attention' };
}

export default function GuardianBehavior({
    student,
    summary,
    records,
    followups,
    offers,
    vouchers,
    redemptions,
}: Props) {
    const [submittingOfferId, setSubmittingOfferId] = useState<number | null>(
        null,
    );

    function redeem(offerId: number) {
        setSubmittingOfferId(offerId);
        router.post(
            BehaviorController.requestRedemption.url(student.id),
            { reward_offer_id: offerId },
            {
                preserveScroll: true,
                onFinish: () => setSubmittingOfferId(null),
            },
        );
    }

    return (
        <GuardianLayout title="Buku Saku">
            <Head title={`Buku Saku — ${student.name}`} />

            <div className="space-y-6">
                <div>
                    <h1 className="text-lg font-semibold tracking-tight">
                        Buku saku {student.name}
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        {summary.academic_year
                            ? `Tahun ajaran ${summary.academic_year}`
                            : 'Ringkasan poin dan catatan perilaku ananda.'}
                    </p>
                </div>

                <div className="grid grid-cols-2 gap-3">
                    <div className="rounded-lg border border-border bg-card p-4 shadow-xs">
                        <p className="text-xs font-medium text-muted-foreground">
                            Poin kebaikan & prestasi
                        </p>
                        <p className="mt-1 text-2xl font-semibold text-primary tabular-nums">
                            {summary.merit_points}
                        </p>
                    </div>

                    <div className="rounded-lg border border-border bg-card p-4 shadow-xs">
                        <p className="text-xs font-medium text-muted-foreground">
                            Poin pelanggaran
                        </p>
                        <p className="mt-1 text-2xl font-semibold text-destructive tabular-nums">
                            {summary.violation_points}
                        </p>
                    </div>
                </div>

                {followups.length > 0 ? (
                    <section className="space-y-3">
                        <div className="flex items-center gap-2">
                            <ShieldAlert className="h-4 w-4 text-accent-attention" />
                            <h2 className="text-sm font-semibold text-foreground">
                                Tindak lanjut kedisiplinan
                            </h2>
                        </div>
                        <div className="space-y-2">
                            {followups.map((f) => (
                                <div
                                    key={f.id}
                                    className="flex items-center justify-between gap-3 rounded-lg border border-border bg-card p-3.5 shadow-xs"
                                >
                                    <div className="min-w-0">
                                        <p className="font-medium text-foreground">
                                            {f.action_name}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            Ambang {f.threshold_points} poin ·{' '}
                                            <DateText value={f.triggered_at} />
                                        </p>
                                    </div>
                                    <StatusBadge
                                        variant={
                                            f.status === 'open'
                                                ? 'attention'
                                                : 'success'
                                        }
                                    >
                                        {f.status === 'open'
                                            ? 'Proses'
                                            : 'Selesai'}
                                    </StatusBadge>
                                </div>
                            ))}
                        </div>
                    </section>
                ) : null}

                {vouchers.length > 0 ? (
                    <section className="space-y-3">
                        <div className="flex items-center gap-2">
                            <Ticket className="h-4 w-4 text-primary" />
                            <h2 className="text-sm font-semibold text-foreground">
                                Voucher kantin aktif
                            </h2>
                        </div>
                        <div className="space-y-2">
                            {vouchers.map((v) => (
                                <div
                                    key={v.id}
                                    className="flex items-center justify-between gap-3 rounded-lg border border-border bg-card p-3.5 shadow-xs"
                                >
                                    <div className="min-w-0">
                                        <p className="font-medium text-foreground">
                                            {v.offer_name}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            Berlaku s.d.{' '}
                                            <DateText value={v.expires_on} />
                                        </p>
                                    </div>
                                    <div className="text-right">
                                        <p className="font-semibold text-primary">
                                            <MoneyText amount={v.remaining} />
                                        </p>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </section>
                ) : null}

                {offers.length > 0 ? (
                    <section className="space-y-3">
                        <div className="flex items-center gap-2">
                            <Gift className="h-4 w-4 text-primary" />
                            <h2 className="text-sm font-semibold text-foreground">
                                Penawaran reward
                            </h2>
                        </div>
                        <div className="space-y-2">
                            {offers.map((offer) => (
                                <div
                                    key={offer.id}
                                    className="flex flex-col gap-3 rounded-lg border border-border bg-card p-4 shadow-xs sm:flex-row sm:items-center sm:justify-between"
                                >
                                    <div className="min-w-0">
                                        <p className="font-medium text-foreground">
                                            {offer.name}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {offer.cost_points} poin →{' '}
                                            <MoneyText
                                                amount={offer.voucher_amount}
                                            />{' '}
                                            · berlaku {offer.voucher_valid_days}{' '}
                                            hari
                                        </p>
                                    </div>
                                    <Button
                                        type="button"
                                        size="lg"
                                        className="min-h-11"
                                        disabled={
                                            !offer.eligible ||
                                            submittingOfferId === offer.id
                                        }
                                        onClick={() => redeem(offer.id)}
                                    >
                                        {offer.eligible
                                            ? 'Ajukan tukar'
                                            : 'Poin belum cukup'}
                                    </Button>
                                </div>
                            ))}
                        </div>
                    </section>
                ) : null}

                {redemptions.length > 0 ? (
                    <section className="space-y-3">
                        <h2 className="text-sm font-semibold text-foreground">
                            Pengajuan penukaran reward
                        </h2>
                        <div className="space-y-2">
                            {redemptions.map((r) => {
                                const meta = redemptionMeta(r.status);

                                return (
                                    <div
                                        key={r.id}
                                        className="flex items-center justify-between gap-3 rounded-lg border border-border bg-card p-3.5 shadow-xs"
                                    >
                                        <div className="min-w-0">
                                            <p className="font-medium text-foreground">
                                                {r.offer_name}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {r.cost_points} poin
                                                {r.decision_note
                                                    ? ` · ${r.decision_note}`
                                                    : ''}
                                            </p>
                                        </div>
                                        <StatusBadge variant={meta.variant}>
                                            {meta.label}
                                        </StatusBadge>
                                    </div>
                                );
                            })}
                        </div>
                    </section>
                ) : null}

                <section className="space-y-3">
                    <h2 className="text-sm font-semibold text-foreground">
                        Riwayat catatan perilaku
                    </h2>

                    {records.length === 0 ? (
                        <EmptyState
                            icon={BookOpenCheck}
                            title="Belum ada catatan perilaku"
                            description="Catatan kebaikan, prestasi, atau pelanggaran ananda akan tampil di sini."
                        />
                    ) : (
                        <div className="space-y-2">
                            {records.map((rec) => (
                                <div
                                    key={rec.id}
                                    className="flex items-start justify-between gap-3 rounded-lg border border-border bg-card p-3.5 shadow-xs"
                                >
                                    <div className="min-w-0 space-y-1">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <StatusBadge
                                                variant={categoryVariant(
                                                    rec.category,
                                                )}
                                            >
                                                {rec.category_label}
                                            </StatusBadge>
                                            <span className="text-xs text-muted-foreground">
                                                <DateText
                                                    value={rec.occurred_on}
                                                />
                                            </span>
                                        </div>
                                        <p className="font-medium text-foreground">
                                            {rec.rule_name}
                                        </p>
                                        {rec.note ? (
                                            <p className="text-xs text-muted-foreground">
                                                {rec.note}
                                            </p>
                                        ) : null}
                                    </div>
                                    <span className="shrink-0 font-semibold tabular-nums">
                                        {rec.category === 'violation'
                                            ? `-${rec.points}`
                                            : `+${rec.points}`}
                                    </span>
                                </div>
                            ))}
                        </div>
                    )}
                </section>
            </div>
        </GuardianLayout>
    );
}
