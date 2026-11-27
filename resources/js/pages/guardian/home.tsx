import { Head, Link } from '@inertiajs/react';
import {
    CalendarOff,
    ChevronRight,
    Download,
    FilePlus2,
    X,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { useEffect, useState } from 'react';
import LeaveRequestController from '@/actions/App/Http/Controllers/Guardian/LeaveRequestController';
import { DateText } from '@/components/app/date-text';
import { EmptyState } from '@/components/app/empty-state';
import type { StatusBadgeVariant } from '@/components/app/status-badge';
import { StatusBadge } from '@/components/app/status-badge';
import { Button } from '@/components/ui/button';
import GuardianLayout from '@/layouts/guardian-layout';
import { formatDate, formatLongDate, formatTime } from '@/lib/format';
import type { BeforeInstallPromptEvent } from '@/lib/pwa';
import {
    dismissInstallBanner,
    isInstallBannerDismissed,
    isIosSafari,
    isStandaloneMode,
} from '@/lib/pwa';
import { cn } from '@/lib/utils';

type AttendanceStatus = 'present' | 'late' | 'excused' | 'sick' | 'absent';

type Home = {
    student: {
        id: number;
        nis: string;
        name: string;
        nickname: string | null;
        unit: string;
        classroom: string | null;
        status_label: string;
        photo_url: string | null;
    };
    today: {
        date: string;
        off_day: string | null;
        status: AttendanceStatus | null;
        status_label: string | null;
        checked_in_at: string | null;
        checked_out_at: string | null;
        late_after: string | null;
        href: string;
    } | null;
    week: {
        date: string;
        status: AttendanceStatus | null;
        status_label: string;
    }[];
    leave: {
        id: number;
        status: 'pending' | 'approved' | 'rejected';
        status_label: string;
        type_label: string;
        starts_on: string;
        ends_on: string;
        decision_note: string | null;
    } | null;
    behavior: {
        merit: number;
        violation: number;
        latest: {
            title: string;
            category: 'violation' | 'achievement' | 'good_deed';
            category_label: string;
            points: number;
            occurred_on: string;
        } | null;
        href: string | null;
    } | null;
    exams: {
        upcoming: {
            id: number;
            title: string;
            subject: string;
            status: 'scheduled' | 'running';
            opens_at: string;
            closes_at: string;
        }[];
        latest_result: {
            title: string;
            subject: string;
            score: number;
            computed_at: string;
        } | null;
        href: string;
    } | null;
};

type Props = { home: Home | null };

/** Label status di kartu "Hari ini" mengikuti mockup 19 ("Tepat waktu"). */
const todayBadge: Record<
    AttendanceStatus,
    { label: string; variant: StatusBadgeVariant }
> = {
    present: { label: 'Tepat waktu', variant: 'success' },
    late: { label: 'Terlambat', variant: 'warning' },
    excused: { label: 'Izin', variant: 'default' },
    sick: { label: 'Sakit', variant: 'default' },
    absent: { label: 'Alpa', variant: 'destructive' },
};

/** Tiap status punya huruf + bentuk, tidak hanya warna (DESIGN §7). */
const weekMark: Record<
    AttendanceStatus | 'none',
    { letter: string; className: string }
> = {
    present: {
        letter: 'H',
        className: 'bg-success text-success-foreground border-success',
    },
    late: {
        letter: 'T',
        className: 'bg-warning text-warning-foreground border-warning',
    },
    excused: {
        letter: 'I',
        className: 'bg-primary/10 text-primary border-primary/40',
    },
    sick: {
        letter: 'S',
        className: 'bg-primary/10 text-primary border-primary/40',
    },
    absent: {
        letter: 'A',
        className:
            'bg-destructive/10 text-destructive border-destructive border-dashed',
    },
    none: {
        letter: '–',
        className: 'bg-muted text-muted-foreground border-border border-dashed',
    },
};

const leaveVariant: Record<
    NonNullable<Home['leave']>['status'],
    StatusBadgeVariant
> = {
    pending: 'attention',
    approved: 'success',
    rejected: 'destructive',
};

const weekdayFormatter = new Intl.DateTimeFormat('id-ID', {
    weekday: 'short',
    timeZone: 'Asia/Makassar',
});

function dateAt(day: string): Date {
    return new Date(`${day}T12:00:00+08:00`);
}

export default function GuardianHome({ home }: Props) {
    const [installPrompt, setInstallPrompt] =
        useState<BeforeInstallPromptEvent | null>(null);
    const [showIosHint, setShowIosHint] = useState(false);

    useEffect(() => {
        if (isStandaloneMode() || isInstallBannerDismissed()) {
            return;
        }

        if (isIosSafari()) {
            setShowIosHint(true);

            return;
        }

        const handler = (event: Event): void => {
            event.preventDefault();
            setInstallPrompt(event as BeforeInstallPromptEvent);
        };

        window.addEventListener('beforeinstallprompt', handler);

        return () => window.removeEventListener('beforeinstallprompt', handler);
    }, []);

    const handleInstall = async (): Promise<void> => {
        if (!installPrompt) {
            return;
        }

        await installPrompt.prompt();
        await installPrompt.userChoice;
        dismissInstallBanner();
        setInstallPrompt(null);
    };

    const handleDismiss = (): void => {
        dismissInstallBanner();
        setInstallPrompt(null);
        setShowIosHint(false);
    };

    if (!home) {
        return (
            <GuardianLayout>
                <Head title="Beranda" />
            </GuardianLayout>
        );
    }

    const { student, today, week, leave, behavior, exams } = home;
    const hasAnything =
        today !== null || leave !== null || behavior !== null || exams !== null;

    return (
        <GuardianLayout>
            <Head title="Beranda" />

            <div className="space-y-4">
                <ChildHeader student={student} />

                {(installPrompt !== null || showIosHint) && (
                    <section className="flex items-start justify-between gap-3 rounded-xl border border-border bg-card p-4 shadow-xs">
                        <div className="space-y-1 text-sm">
                            <h2 className="font-semibold text-foreground">
                                Pasang aplikasi di layar utama
                            </h2>
                            {installPrompt ? (
                                <>
                                    <p className="text-muted-foreground">
                                        Buka portal wali lebih cepat seperti
                                        aplikasi di HP Anda.
                                    </p>
                                    <div className="pt-1">
                                        <Button
                                            type="button"
                                            className="min-h-11"
                                            onClick={() => {
                                                void handleInstall();
                                            }}
                                        >
                                            <Download className="size-4" />
                                            Pasang aplikasi
                                        </Button>
                                    </div>
                                </>
                            ) : (
                                <p className="text-muted-foreground">
                                    Ketuk tombol Bagikan di Safari lalu pilih
                                    Tambahkan ke Layar Utama.
                                </p>
                            )}
                        </div>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            className="size-11"
                            aria-label="Tutup ajakan pasang aplikasi"
                            onClick={handleDismiss}
                        >
                            <X className="size-4" />
                        </Button>
                    </section>
                )}

                {leave?.status === 'pending' && <LeaveCard leave={leave} />}

                {today && (
                    <TodayCard
                        today={today}
                        week={week}
                        studentId={student.id}
                        hasPendingLeave={leave?.status === 'pending'}
                    />
                )}

                {leave && leave.status !== 'pending' && (
                    <LeaveCard leave={leave} />
                )}

                {exams && <ExamsCard exams={exams} />}

                {behavior && <BehaviorCard behavior={behavior} />}

                {!hasAnything && (
                    <EmptyState
                        title="Belum ada pembaruan hari ini"
                        description="Presensi, perilaku, dan ujian anak akan muncul di sini setelah dicatat sekolah."
                    />
                )}
            </div>
        </GuardianLayout>
    );
}

function ChildHeader({ student }: { student: Home['student'] }) {
    return (
        <section className="bg-islamic-pattern overflow-hidden rounded-xl bg-primary p-5 text-primary-foreground shadow-sm">
            <div className="flex items-center gap-4">
                {student.photo_url ? (
                    <img
                        src={student.photo_url}
                        alt={`Foto ${student.name}`}
                        className="size-14 shrink-0 rounded-full object-cover ring-2 ring-primary-foreground/40"
                    />
                ) : (
                    <div
                        className="flex size-14 shrink-0 items-center justify-center rounded-full bg-primary-foreground/15 text-lg font-semibold ring-2 ring-primary-foreground/30"
                        aria-hidden
                    >
                        {student.name.slice(0, 1).toUpperCase()}
                    </div>
                )}
                <div className="min-w-0 flex-1">
                    <p className="text-xs text-primary-foreground/80">
                        {formatLongDate(new Date())}
                    </p>
                    <h1 className="truncate text-lg leading-snug font-semibold">
                        {student.name}
                    </h1>
                    <p className="text-sm text-primary-foreground/85">
                        {student.unit}
                        {student.classroom ? ` · ${student.classroom}` : ''} ·
                        NIS {student.nis}
                    </p>
                </div>
            </div>
        </section>
    );
}

function Card({
    title,
    href,
    linkLabel,
    tone = 'default',
    children,
}: {
    title: string;
    href?: string | null;
    linkLabel?: string;
    tone?: 'default' | 'attention';
    children: ReactNode;
}) {
    return (
        <section
            className={cn(
                'overflow-hidden rounded-xl border bg-card shadow-xs',
                tone === 'attention'
                    ? 'border-accent-attention'
                    : 'border-border',
            )}
        >
            <div className="p-4">
                <h2 className="mb-3 text-sm text-muted-foreground">{title}</h2>
                {children}
            </div>
            {href && linkLabel && (
                <Link
                    href={href}
                    className="flex min-h-11 items-center justify-between border-t border-border bg-muted/40 px-4 text-sm font-medium text-primary transition-colors duration-150 hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none focus-visible:ring-inset"
                >
                    {linkLabel}
                    <ChevronRight className="size-5" strokeWidth={1.75} />
                </Link>
            )}
        </section>
    );
}

function TodayCard({
    today,
    week,
    studentId,
    hasPendingLeave,
}: {
    today: NonNullable<Home['today']>;
    week: Home['week'];
    studentId: number;
    hasPendingLeave: boolean;
}) {
    const badge = today.status ? todayBadge[today.status] : null;
    const tapped = today.checked_in_at !== null;

    return (
        <Card
            title={`Hari ini · ${formatDate(today.date)}`}
            href={today.href}
            linkLabel="Riwayat presensi"
        >
            {today.off_day && !today.status ? (
                <p className="flex items-center gap-2 text-base text-muted-foreground">
                    <CalendarOff
                        className="size-5 shrink-0"
                        strokeWidth={1.75}
                    />
                    {today.off_day}
                </p>
            ) : (
                <div className="flex flex-wrap items-center gap-x-3 gap-y-2">
                    <p className="text-xl font-semibold tabular-nums">
                        {tapped
                            ? `Masuk ${today.checked_in_at}`
                            : (today.status_label ?? 'Belum tap masuk')}
                    </p>
                    {badge && tapped && (
                        <StatusBadge variant={badge.variant}>
                            {badge.label}
                        </StatusBadge>
                    )}
                    {badge && !tapped && today.status !== null && (
                        <StatusBadge variant={badge.variant}>
                            Tercatat sekolah
                        </StatusBadge>
                    )}
                </div>
            )}

            {!today.off_day && (
                <p className="mt-1 text-sm text-muted-foreground">
                    {today.checked_out_at
                        ? `Pulang ${today.checked_out_at}`
                        : tapped
                          ? 'Belum tap pulang'
                          : today.late_after
                            ? `Terlambat bila tiba setelah ${today.late_after}`
                            : null}
                </p>
            )}

            {week.length > 1 && (
                <ol
                    className="mt-4 grid grid-cols-5 gap-2 border-t border-border pt-4"
                    aria-label="Presensi lima hari sekolah terakhir"
                >
                    {week.map((day) => {
                        const mark = weekMark[day.status ?? 'none'];
                        const isToday = day.date === today.date;

                        return (
                            <li
                                key={day.date}
                                className="flex flex-col items-center gap-1.5"
                            >
                                <span
                                    className={cn(
                                        'flex size-9 items-center justify-center rounded-full border text-sm font-semibold',
                                        mark.className,
                                        isToday &&
                                            'ring-2 ring-primary/30 ring-offset-2 ring-offset-card',
                                    )}
                                    aria-hidden
                                >
                                    {mark.letter}
                                </span>
                                <span
                                    className={cn(
                                        'text-xs text-muted-foreground',
                                        isToday &&
                                            'font-semibold text-foreground',
                                    )}
                                >
                                    {isToday
                                        ? 'Hari ini'
                                        : weekdayFormatter.format(
                                              dateAt(day.date),
                                          )}
                                </span>
                                <span className="sr-only">
                                    {formatDate(day.date)}: {day.status_label}
                                </span>
                            </li>
                        );
                    })}
                </ol>
            )}

            {!hasPendingLeave && !today.off_day && (
                <Button
                    variant="outline"
                    asChild
                    className="mt-4 min-h-11 w-full"
                >
                    <Link href={LeaveRequestController.create(studentId)}>
                        <FilePlus2 className="size-4" strokeWidth={1.75} />
                        Ajukan izin atau sakit
                    </Link>
                </Button>
            )}
        </Card>
    );
}

function LeaveCard({ leave }: { leave: NonNullable<Home['leave']> }) {
    const pending = leave.status === 'pending';

    return (
        <Card
            title={pending ? 'Perlu diketahui' : 'Pengajuan izin'}
            tone={pending ? 'attention' : 'default'}
        >
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                    <p className="text-base font-semibold">
                        {leave.type_label}{' '}
                        <span className="font-normal text-muted-foreground">
                            ·{' '}
                            {leave.starts_on === leave.ends_on ? (
                                <DateText value={leave.starts_on} />
                            ) : (
                                <>
                                    <DateText value={leave.starts_on} /> –{' '}
                                    <DateText value={leave.ends_on} />
                                </>
                            )}
                        </span>
                    </p>
                    <p className="mt-0.5 text-sm text-muted-foreground">
                        {pending
                            ? 'Menunggu keputusan wali kelas. Anda akan diberi tahu setelah diputuskan.'
                            : (leave.decision_note ??
                              'Keputusan sudah dicatat sekolah.')}
                    </p>
                </div>
                <StatusBadge variant={leaveVariant[leave.status]}>
                    {leave.status_label}
                </StatusBadge>
            </div>
        </Card>
    );
}

function ExamsCard({ exams }: { exams: NonNullable<Home['exams']> }) {
    return (
        <Card title="Ujian" href={exams.href} linkLabel="Semua nilai ujian">
            {exams.upcoming.length > 0 && (
                <ul className="space-y-3">
                    {exams.upcoming.map((exam) => (
                        <li key={exam.id} className="flex items-center gap-3">
                            <div className="flex w-12 shrink-0 flex-col items-center rounded-lg border border-border bg-background py-1.5 leading-tight">
                                <span className="text-[11px] text-muted-foreground">
                                    {weekdayFormatter.format(
                                        new Date(exam.opens_at),
                                    )}
                                </span>
                                <span className="text-sm font-semibold tabular-nums">
                                    {formatTime(exam.opens_at)}
                                </span>
                            </div>
                            <div className="min-w-0 flex-1">
                                <p className="truncate text-sm font-semibold">
                                    {exam.subject}
                                </p>
                                <p className="truncate text-sm text-muted-foreground">
                                    {exam.title} · {formatDate(exam.opens_at)}
                                </p>
                            </div>
                            {exam.status === 'running' && (
                                <StatusBadge variant="success">
                                    Berlangsung
                                </StatusBadge>
                            )}
                        </li>
                    ))}
                </ul>
            )}

            {exams.latest_result && (
                <div
                    className={cn(
                        'flex items-center justify-between gap-3',
                        exams.upcoming.length > 0 &&
                            'mt-4 border-t border-border pt-4',
                    )}
                >
                    <div className="min-w-0">
                        <p className="text-xs text-muted-foreground">
                            Nilai terbaru
                        </p>
                        <p className="truncate text-sm font-semibold">
                            {exams.latest_result.subject}
                        </p>
                        <p className="truncate text-sm text-muted-foreground">
                            {exams.latest_result.title}
                        </p>
                    </div>
                    <p className="text-3xl font-semibold tabular-nums">
                        {exams.latest_result.score}
                    </p>
                </div>
            )}
        </Card>
    );
}

function BehaviorCard({
    behavior,
}: {
    behavior: NonNullable<Home['behavior']>;
}) {
    const latest = behavior.latest;

    return (
        <Card
            title="Buku saku perilaku · tahun ini"
            href={behavior.href}
            linkLabel="Buka buku saku"
        >
            <dl className="grid grid-cols-2 gap-3">
                <div className="rounded-lg border border-success/30 bg-success/10 px-3 py-2.5">
                    <dt className="text-xs text-muted-foreground">
                        Poin kebaikan
                    </dt>
                    <dd className="text-2xl font-semibold text-success tabular-nums">
                        {behavior.merit}
                    </dd>
                </div>
                <div className="rounded-lg border border-border bg-background px-3 py-2.5">
                    <dt className="text-xs text-muted-foreground">
                        Poin pelanggaran
                    </dt>
                    <dd className="text-2xl font-semibold tabular-nums">
                        {behavior.violation}
                    </dd>
                </div>
            </dl>

            {latest && (
                <div className="mt-3 flex items-start justify-between gap-3 border-t border-border pt-3">
                    <div className="min-w-0">
                        <p className="text-xs text-muted-foreground">
                            Catatan terbaru ·{' '}
                            <DateText value={latest.occurred_on} />
                        </p>
                        <p className="text-sm font-medium">{latest.title}</p>
                    </div>
                    <StatusBadge
                        variant={
                            latest.category === 'violation'
                                ? 'destructive'
                                : 'success'
                        }
                    >
                        {latest.category_label} · {latest.points} poin
                    </StatusBadge>
                </div>
            )}
        </Card>
    );
}
