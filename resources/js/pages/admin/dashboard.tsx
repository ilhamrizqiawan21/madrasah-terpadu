import { Head, Link, router, usePage } from '@inertiajs/react';
import { CalendarOff, CircleCheck, GraduationCap, Upload } from 'lucide-react';
import AttendanceController from '@/actions/App/Http/Controllers/Admin/AttendanceController';
import CashierController from '@/actions/App/Http/Controllers/Admin/CashierController';
import CbtEssayScoreController from '@/actions/App/Http/Controllers/Admin/CbtEssayScoreController';
import CbtExamController from '@/actions/App/Http/Controllers/Admin/CbtExamController';
import CbtMonitorController from '@/actions/App/Http/Controllers/Admin/CbtMonitorController';
import ClassroomController from '@/actions/App/Http/Controllers/Admin/ClassroomController';
import GuardianController from '@/actions/App/Http/Controllers/Admin/GuardianController';
import InvoiceController from '@/actions/App/Http/Controllers/Admin/InvoiceController';
import LeaveRequestController from '@/actions/App/Http/Controllers/Admin/LeaveRequestController';
import StudentController from '@/actions/App/Http/Controllers/Admin/StudentController';
import StudentImportController from '@/actions/App/Http/Controllers/Admin/StudentImportController';
import StudentMutationController from '@/actions/App/Http/Controllers/Admin/StudentMutationController';
import StudentPromotionController from '@/actions/App/Http/Controllers/Admin/StudentPromotionController';
import TransferProofController from '@/actions/App/Http/Controllers/Admin/TransferProofController';
import { DashboardPanel } from '@/components/app/dashboard-panel';
import { DateText } from '@/components/app/date-text';
import { MoneyText } from '@/components/app/money-text';
import type { StatusBadgeVariant } from '@/components/app/status-badge';
import { StatusBadge } from '@/components/app/status-badge';
import { Button } from '@/components/ui/button';
import StaffLayout from '@/layouts/staff-layout';
import { formatDate, formatLongDate, formatTime } from '@/lib/format';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes/admin';
import type { Auth } from '@/types';

type AttendanceKey =
    | 'present'
    | 'late'
    | 'excused'
    | 'sick'
    | 'absent'
    | 'not_yet';

type Attendance = {
    date: string;
    scope: 'unit' | 'homeroom';
    off_day: string | null;
    late_after: string | null;
    total: number;
    counts: Record<AttendanceKey, number>;
    classrooms: { id: number; name: string; total: number; not_yet: number }[];
    not_yet_students: {
        id: number;
        name: string;
        nis: string;
        classroom: string;
    }[];
    trend: TrendDay[];
};

type TrendDay = {
    date: string;
    present: number;
    late: number;
    excused: number;
    sick: number;
    absent: number;
};

type Leaves = {
    count: number;
    items: {
        id: number;
        student_name: string;
        type_label: string;
        starts_on: string;
        ends_on: string;
        submitted_at: string;
    }[];
};

type Exams = {
    today: {
        id: number;
        title: string;
        subject: string;
        status: 'scheduled' | 'running' | 'finished';
        status_label: string;
        opens_at: string;
        closes_at: string;
        in_progress: number;
        finished: number;
    }[];
    awaiting_essay: {
        id: number;
        title: string;
        subject: string;
        count: number;
    }[];
};

type CashInPeriod = {
    total: number;
    count: number;
    by_method: {
        cash: number;
        transfer: number;
        midtrans: number;
    };
};

type FinanceDashboard = {
    scope: 'unit' | 'all';
    can_view_all_units: boolean;
    unit_code: string | null;
    unit_name: string | null;
    billing_enabled: boolean;
    wallet_enabled: boolean;
    cash_in: {
        today: CashInPeriod;
        month: CashInPeriod;
        academic_year: CashInPeriod;
        pending_transfer_proofs: number;
    } | null;
    receivables: {
        total: number;
        overdue: number;
        overdue_count: number;
        by_fee_type: {
            fee_type_id: number;
            name: string;
            count: number;
            total: number;
            overdue: number;
        }[];
        by_classroom: {
            classroom_id: number | null;
            name: string;
            student_count: number;
            total: number;
            overdue: number;
        }[];
        top_arrears: {
            student_id: number;
            student_name: string;
            nis: string;
            classroom: string;
            overdue_count: number;
            overdue_amount: number;
        }[];
    } | null;
    online_unreconciled: {
        available: boolean;
        midtrans_enabled: boolean;
        pending_count: number;
        pending_amount: number;
        needs_review_count: number;
    };
    wallet: {
        available: boolean;
        total_balance: number;
        active_wallets: number;
        frozen_wallets: number;
        topup_today: number;
        topup_month: number;
    } | null;
    by_unit: {
        code: string;
        name: string;
        cash_in_month: number;
        receivables_total: number;
        receivables_overdue: number;
        wallet_balance: number;
    }[];
};

type MutationSummary = {
    id: number;
    type: { value: 'in' | 'out' | 'graduated'; label: string };
    mutated_on: string;
    reason: string;
    student_name: string;
    student_nis: string;
    unit_code: string;
};

type Props = {
    summary: {
        unit_code: string | null;
        unit_name: string | null;
        academic_year: string | null;
        roles: string[];
        active_students: number | null;
        active_classrooms: number | null;
        guardians: number | null;
    };
    attendance: Attendance | null;
    leaves: Leaves | null;
    exams: Exams | null;
    dataGaps: {
        students_without_classroom: number;
        students_without_guardian: number;
        classrooms_without_homeroom: number;
    } | null;
    queue: { pending_jobs: number; failed_jobs: number } | null;
    heldModules: string[];
    foundation: {
        legacy_url: string;
        modules: {
            key: string;
            label: string;
            detail: string;
            status: 'held' | 'deferred' | 'out_of_scope' | 'planned';
        }[];
    } | null;
    finance: FinanceDashboard | null;
    security: {
        failed_logins_24h: number;
        recent_logins: { name: string; at: string }[];
    } | null;
    units:
        | {
              code: string;
              name: string;
              students: number;
              classrooms: number;
              recorded: number;
              present: number;
              late: number;
              absent: number;
          }[]
        | null;
    recentMutations: MutationSummary[];
    permissions: {
        view_students: boolean;
        manage_students: boolean;
        manage_classrooms: boolean;
        manage_guardians: boolean;
        import_students: boolean;
        manage_attendance: boolean;
        manage_cbt: boolean;
    };
};

const attendanceSegments: {
    key: AttendanceKey;
    label: string;
    swatch: string;
}[] = [
    { key: 'present', label: 'Hadir', swatch: 'bg-success' },
    { key: 'late', label: 'Terlambat', swatch: 'bg-warning' },
    { key: 'excused', label: 'Izin', swatch: 'bg-primary' },
    { key: 'sick', label: 'Sakit', swatch: 'bg-primary/45' },
    { key: 'absent', label: 'Alpa', swatch: 'bg-destructive' },
    { key: 'not_yet', label: 'Belum tap', swatch: 'bg-muted-foreground/25' },
];

const mutationVariant: Record<
    MutationSummary['type']['value'],
    StatusBadgeVariant
> = {
    in: 'success',
    out: 'warning',
    graduated: 'default',
};

const examVariant: Record<
    Exams['today'][number]['status'],
    StatusBadgeVariant
> = {
    scheduled: 'muted',
    running: 'success',
    finished: 'default',
};

const numberFormatter = new Intl.NumberFormat('id-ID');

function initials(name: string): string {
    return name
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase() ?? '')
        .join('');
}

export default function AdminDashboard({
    summary,
    attendance,
    leaves,
    exams,
    dataGaps,
    queue,
    heldModules,
    foundation,
    finance,
    security,
    units,
    recentMutations,
    permissions,
}: Props) {
    const { auth } = usePage<{ auth: Auth }>().props;

    const stats = [
        summary.active_students !== null && {
            key: 'students',
            label: 'Siswa aktif',
            value: summary.active_students,
            href: StudentController.index(),
        },
        summary.active_classrooms !== null && {
            key: 'classrooms',
            label: 'Rombel',
            value: summary.active_classrooms,
            href: permissions.manage_classrooms
                ? ClassroomController.index()
                : StudentController.index(),
        },
        summary.guardians !== null && {
            key: 'guardians',
            label: 'Wali',
            value: summary.guardians,
            href: GuardianController.index(),
        },
    ].filter((item) => item !== false);

    const hasSide =
        leaves !== null ||
        dataGaps !== null ||
        queue !== null ||
        permissions.manage_students;
    const hasMain = attendance !== null || exams !== null;

    return (
        <StaffLayout breadcrumbs={[{ title: 'Beranda', href: dashboard() }]}>
            <Head title="Beranda Staf" />

            <div className="space-y-6">
                <section className="bg-islamic-pattern overflow-hidden rounded-xl bg-primary text-primary-foreground shadow-sm">
                    <div className="flex flex-col gap-5 p-5 sm:p-6 lg:flex-row lg:items-end lg:justify-between">
                        <div className="space-y-1.5">
                            <p className="text-xs font-medium text-primary-foreground/80">
                                {formatLongDate(new Date())}
                                {' · '}
                                {summary.unit_code
                                    ? `${summary.unit_name ?? summary.unit_code}`
                                    : 'Semua unit'}
                                {summary.academic_year
                                    ? ` · ${summary.academic_year}`
                                    : ''}
                            </p>
                            <h1 className="text-xl font-semibold tracking-tight sm:text-2xl">
                                Assalamu&apos;alaikum, {auth.user.name}
                            </h1>
                            {summary.roles.length > 0 && (
                                <p className="text-sm text-primary-foreground/80">
                                    {summary.roles.join(' · ')}
                                </p>
                            )}
                        </div>

                        {stats.length > 0 && (
                            <dl className="grid grid-cols-3 divide-x divide-primary-foreground/20 rounded-lg bg-primary-foreground/10 ring-1 ring-primary-foreground/15 lg:min-w-96">
                                {stats.map((stat) => (
                                    <Link
                                        key={stat.key}
                                        href={stat.href}
                                        className="group px-4 py-3 transition-colors duration-150 first:rounded-l-lg last:rounded-r-lg hover:bg-primary-foreground/10 focus-visible:ring-2 focus-visible:ring-primary-foreground focus-visible:outline-none"
                                    >
                                        <dt className="text-xs text-primary-foreground/75">
                                            {stat.label}
                                        </dt>
                                        <dd className="text-2xl font-semibold tabular-nums">
                                            {numberFormatter.format(stat.value)}
                                        </dd>
                                    </Link>
                                ))}
                            </dl>
                        )}
                    </div>

                    {(permissions.manage_students ||
                        permissions.import_students) && (
                        <div className="flex flex-wrap gap-2 border-t border-primary-foreground/15 bg-primary-foreground/5 px-5 py-3 sm:px-6">
                            {permissions.manage_students && (
                                <Button
                                    size="sm"
                                    asChild
                                    className="bg-card text-foreground hover:bg-card/90"
                                >
                                    <Link href={StudentController.index()}>
                                        Kelola siswa
                                    </Link>
                                </Button>
                            )}
                            {permissions.import_students && (
                                <HeroLink
                                    href={StudentImportController.create()}
                                >
                                    <Upload className="size-4" />
                                    Impor Excel
                                </HeroLink>
                            )}
                            {permissions.manage_students && (
                                <HeroLink
                                    href={StudentPromotionController.index()}
                                >
                                    <GraduationCap className="size-4" />
                                    Kenaikan kelas
                                </HeroLink>
                            )}
                        </div>
                    )}
                </section>

                {foundation && <FoundationRow foundation={foundation} />}
                {finance && <FinanceSection finance={finance} />}

                {(hasMain || hasSide) && (
                    <div
                        className={cn(
                            'grid items-start gap-6',
                            hasMain && hasSide && 'lg:grid-cols-3',
                        )}
                    >
                        {hasMain && (
                            <div
                                className={cn(
                                    'space-y-6',
                                    hasSide && 'lg:col-span-2',
                                )}
                            >
                                {attendance && (
                                    <AttendancePanel attendance={attendance} />
                                )}
                                {attendance && attendance.trend.length > 1 && (
                                    <TrendPanel
                                        trend={attendance.trend}
                                        total={attendance.total}
                                        isHomeroom={
                                            attendance.scope === 'homeroom'
                                        }
                                    />
                                )}
                                {units && units.length > 1 && (
                                    <UnitsPanel units={units} />
                                )}
                                {exams && (
                                    <ExamsPanel
                                        exams={exams}
                                        canManage={permissions.manage_cbt}
                                    />
                                )}
                            </div>
                        )}

                        {hasSide && (
                            <div className="space-y-6">
                                {leaves && (
                                    <LeavesPanel
                                        leaves={leaves}
                                        canDecide={
                                            permissions.manage_attendance
                                        }
                                    />
                                )}
                                {dataGaps && (
                                    <DataGapsPanel
                                        gaps={dataGaps}
                                        canManageClassrooms={
                                            permissions.manage_classrooms
                                        }
                                        canManageGuardians={
                                            permissions.manage_guardians
                                        }
                                    />
                                )}
                                {permissions.manage_students && (
                                    <MutationsPanel
                                        mutations={recentMutations}
                                    />
                                )}
                                {queue && (
                                    <QueuePanel
                                        queue={queue}
                                        heldModules={heldModules}
                                    />
                                )}
                                {security && (
                                    <SecurityPanel security={security} />
                                )}
                            </div>
                        )}
                    </div>
                )}
            </div>
        </StaffLayout>
    );
}

function HeroLink({
    href,
    children,
}: {
    href: ReturnType<typeof StudentImportController.create>;
    children: React.ReactNode;
}) {
    return (
        <Button
            size="sm"
            variant="ghost"
            asChild
            className="border border-primary-foreground/25 bg-primary-foreground/10 text-primary-foreground hover:bg-primary-foreground/20 hover:text-primary-foreground"
        >
            <Link href={href}>{children}</Link>
        </Button>
    );
}

function AttendancePanel({ attendance }: { attendance: Attendance }) {
    const { counts, total } = attendance;
    const recorded = total - counts.not_yet;
    const percent = total > 0 ? Math.round((recorded / total) * 100) : 0;
    const isHomeroom = attendance.scope === 'homeroom';

    const meta = [
        formatDate(attendance.date),
        attendance.late_after
            ? `terlambat setelah ${attendance.late_after}`
            : null,
    ]
        .filter(Boolean)
        .join(' · ');

    return (
        <DashboardPanel
            title={
                isHomeroom
                    ? 'Presensi rombel Anda hari ini'
                    : 'Presensi hari ini'
            }
            meta={meta}
            action={{ label: 'Rekap', href: AttendanceController.index() }}
        >
            {total === 0 ? (
                <p className="py-2 text-sm text-muted-foreground">
                    {isHomeroom
                        ? 'Anda belum tercatat sebagai wali kelas rombel aktif di tahun ajaran ini.'
                        : 'Belum ada siswa aktif di rombel tahun ajaran ini. Isi rombel siswa agar presensi terhitung.'}
                </p>
            ) : (
                <div className="space-y-5">
                    {attendance.off_day && (
                        <div className="flex items-center gap-2 rounded-lg border border-border bg-muted/50 px-3 py-2 text-sm text-muted-foreground">
                            <CalendarOff
                                className="size-4 shrink-0"
                                strokeWidth={1.75}
                            />
                            {attendance.off_day}
                        </div>
                    )}

                    <div className="flex flex-wrap items-end justify-between gap-3">
                        <div>
                            <p className="text-3xl font-semibold tracking-tight tabular-nums">
                                {numberFormatter.format(recorded)}
                                <span className="text-lg font-medium text-muted-foreground">
                                    {' '}
                                    / {numberFormatter.format(total)}
                                </span>
                            </p>
                            <p className="text-sm text-muted-foreground">
                                siswa sudah tercatat ({percent}%)
                            </p>
                        </div>
                        {counts.not_yet > 0 && !attendance.off_day && (
                            <StatusBadge variant="attention">
                                {numberFormatter.format(counts.not_yet)} belum
                                tap
                            </StatusBadge>
                        )}
                    </div>

                    <div
                        className="flex h-3 w-full overflow-hidden rounded-full bg-muted"
                        role="img"
                        aria-label={attendanceSegments
                            .map((s) => `${s.label} ${counts[s.key]}`)
                            .join(', ')}
                    >
                        {attendanceSegments.map((segment) =>
                            counts[segment.key] > 0 ? (
                                <div
                                    key={segment.key}
                                    className={cn(
                                        'h-full border-r-2 border-card last:border-r-0',
                                        segment.swatch,
                                    )}
                                    style={{
                                        width: `${(counts[segment.key] / total) * 100}%`,
                                    }}
                                />
                            ) : null,
                        )}
                    </div>

                    <dl className="grid grid-cols-2 gap-x-6 gap-y-2 sm:grid-cols-3">
                        {attendanceSegments.map((segment) => (
                            <div
                                key={segment.key}
                                className="flex items-center justify-between gap-2 text-sm"
                            >
                                <dt className="flex items-center gap-2 text-muted-foreground">
                                    <span
                                        className={cn(
                                            'size-2.5 rounded-sm',
                                            segment.swatch,
                                        )}
                                        aria-hidden
                                    />
                                    {segment.label}
                                </dt>
                                <dd className="font-medium tabular-nums">
                                    {numberFormatter.format(
                                        counts[segment.key],
                                    )}
                                </dd>
                            </div>
                        ))}
                    </dl>

                    {isHomeroom
                        ? attendance.not_yet_students.length > 0 && (
                              <NotYetStudents
                                  students={attendance.not_yet_students}
                                  remaining={
                                      counts.not_yet -
                                      attendance.not_yet_students.length
                                  }
                              />
                          )
                        : attendance.classrooms.some((c) => c.not_yet > 0) && (
                              <ClassroomBreakdown
                                  classrooms={attendance.classrooms.filter(
                                      (c) => c.not_yet > 0,
                                  )}
                              />
                          )}
                </div>
            )}
        </DashboardPanel>
    );
}

function NotYetStudents({
    students,
    remaining,
}: {
    students: Attendance['not_yet_students'];
    remaining: number;
}) {
    return (
        <div className="space-y-2 border-t border-border pt-4">
            <h3 className="text-xs font-medium text-muted-foreground">
                Belum tap
            </h3>
            <ul className="grid gap-2 sm:grid-cols-2">
                {students.map((student) => (
                    <li
                        key={student.id}
                        className="flex items-center gap-3 rounded-lg border border-border bg-background/60 px-3 py-2"
                    >
                        <span
                            className="flex size-8 shrink-0 items-center justify-center rounded-full bg-primary/10 text-xs font-semibold text-primary"
                            aria-hidden
                        >
                            {initials(student.name)}
                        </span>
                        <div className="min-w-0">
                            <p className="truncate text-sm font-medium">
                                {student.name}
                            </p>
                            <p className="text-xs text-muted-foreground">
                                {student.classroom} · NIS {student.nis}
                            </p>
                        </div>
                    </li>
                ))}
            </ul>
            {remaining > 0 && (
                <p className="text-xs text-muted-foreground">
                    dan {remaining} siswa lainnya di rekap.
                </p>
            )}
        </div>
    );
}

const trendStack: {
    label: string;
    swatch: string;
    value: (day: TrendDay) => number;
}[] = [
    { label: 'Hadir', swatch: 'bg-success', value: (d) => d.present },
    { label: 'Terlambat', swatch: 'bg-warning', value: (d) => d.late },
    {
        label: 'Izin/sakit',
        swatch: 'bg-primary/45',
        value: (d) => d.excused + d.sick,
    },
    { label: 'Alpa', swatch: 'bg-destructive', value: (d) => d.absent },
];

const weekdayFormatter = new Intl.DateTimeFormat('id-ID', {
    weekday: 'short',
    timeZone: 'Asia/Makassar',
});

const dayFormatter = new Intl.DateTimeFormat('id-ID', {
    day: 'numeric',
    timeZone: 'Asia/Makassar',
});

const decimalFormatter = new Intl.NumberFormat('id-ID', {
    maximumFractionDigits: 1,
});

function TrendPanel({
    trend,
    total,
    isHomeroom,
}: {
    trend: TrendDay[];
    total: number;
    isHomeroom: boolean;
}) {
    const past = trend.slice(0, -1);
    const today = trend[trend.length - 1];
    const averageLate =
        past.reduce((sum, day) => sum + day.late, 0) / Math.max(past.length, 1);
    const averageAbsent =
        past.reduce((sum, day) => sum + day.absent, 0) /
        Math.max(past.length, 1);

    return (
        <DashboardPanel
            title={`Tren ${trend.length} hari sekolah terakhir`}
            meta={
                isHomeroom ? 'Rombel yang Anda walikan' : 'Seluruh rombel unit'
            }
        >
            <div className="grid gap-6 sm:grid-cols-[1fr_11rem]">
                <div>
                    <div className="flex h-40 items-end gap-2 sm:gap-3">
                        {trend.map((day, index) => {
                            const isToday = index === trend.length - 1;
                            const date = new Date(`${day.date}T12:00:00+08:00`);
                            const description = trendStack
                                .map((s) => `${s.label} ${s.value(day)}`)
                                .join(', ');

                            return (
                                <div
                                    key={day.date}
                                    className="flex h-full flex-1 flex-col items-center gap-1.5"
                                >
                                    <div
                                        className={cn(
                                            'relative flex w-full max-w-12 flex-1 flex-col-reverse overflow-hidden rounded-md bg-muted',
                                            isToday &&
                                                'ring-2 ring-primary/30 ring-offset-2 ring-offset-card',
                                        )}
                                        role="img"
                                        aria-label={`${formatDate(day.date)}: ${description}`}
                                        title={`${formatDate(day.date)} · ${description}`}
                                    >
                                        {trendStack.map((segment) => {
                                            const value = segment.value(day);

                                            return value > 0 ? (
                                                <div
                                                    key={segment.label}
                                                    className={cn(
                                                        'w-full border-t border-card first:border-t-0',
                                                        segment.swatch,
                                                    )}
                                                    style={{
                                                        height: `${(value / Math.max(total, 1)) * 100}%`,
                                                    }}
                                                />
                                            ) : null;
                                        })}
                                    </div>
                                    <div className="text-center leading-tight">
                                        <p
                                            className={cn(
                                                'text-[11px] text-muted-foreground',
                                                isToday &&
                                                    'font-semibold text-foreground',
                                            )}
                                        >
                                            {isToday
                                                ? 'Hari ini'
                                                : weekdayFormatter.format(date)}
                                        </p>
                                        <p className="text-[11px] text-muted-foreground tabular-nums">
                                            {dayFormatter.format(date)}
                                        </p>
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                    <ul className="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground">
                        {trendStack.map((segment) => (
                            <li
                                key={segment.label}
                                className="flex items-center gap-1.5"
                            >
                                <span
                                    className={cn(
                                        'size-2.5 rounded-sm',
                                        segment.swatch,
                                    )}
                                    aria-hidden
                                />
                                {segment.label}
                            </li>
                        ))}
                    </ul>
                </div>

                <dl className="grid grid-cols-2 gap-3 self-start sm:grid-cols-1">
                    <TrendStat
                        label="Terlambat hari ini"
                        value={today.late}
                        average={averageLate}
                        days={past.length}
                    />
                    <TrendStat
                        label="Alpa hari ini"
                        value={today.absent}
                        average={averageAbsent}
                        days={past.length}
                    />
                </dl>
            </div>
        </DashboardPanel>
    );
}

function TrendStat({
    label,
    value,
    average,
    days,
}: {
    label: string;
    value: number;
    average: number;
    days: number;
}) {
    const higher = value > average;

    return (
        <div className="rounded-lg border border-border bg-background/60 px-3 py-2.5">
            <dt className="text-xs text-muted-foreground">{label}</dt>
            <dd className="mt-0.5 flex items-baseline gap-2">
                <span className="text-2xl font-semibold tabular-nums">
                    {numberFormatter.format(value)}
                </span>
                <span
                    className={cn(
                        'text-xs tabular-nums',
                        higher ? 'text-destructive' : 'text-muted-foreground',
                    )}
                >
                    rata-rata {decimalFormatter.format(average)} · {days} hari
                    sebelumnya
                </span>
            </dd>
        </div>
    );
}

function ClassroomBreakdown({
    classrooms,
}: {
    classrooms: Attendance['classrooms'];
}) {
    return (
        <div className="space-y-3 border-t border-border pt-4">
            <h3 className="text-xs font-medium text-muted-foreground">
                Rombel dengan siswa belum tap
            </h3>
            <ul className="space-y-2.5">
                {classrooms.map((classroom) => {
                    const done = classroom.total - classroom.not_yet;

                    return (
                        <li key={classroom.id}>
                            <Link
                                href={AttendanceController.index({
                                    query: { classroom_id: classroom.id },
                                })}
                                className="group -mx-2 grid grid-cols-[3.5rem_1fr_auto] items-center gap-3 rounded-md px-2 py-1 text-sm transition-colors duration-150 hover:bg-muted/60 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none sm:grid-cols-[6rem_1fr_10rem]"
                            >
                                <span className="truncate font-medium group-hover:text-primary">
                                    {classroom.name}
                                </span>
                                <span
                                    className="flex h-2 overflow-hidden rounded-full bg-accent-attention/35"
                                    aria-hidden
                                >
                                    <span
                                        className="block h-full bg-primary/75"
                                        style={{
                                            width: `${(done / classroom.total) * 100}%`,
                                        }}
                                    />
                                </span>
                                <span className="text-right text-xs text-muted-foreground tabular-nums">
                                    <span className="font-semibold text-foreground">
                                        {classroom.not_yet} belum
                                    </span>{' '}
                                    · {done}/{classroom.total} tercatat
                                </span>
                            </Link>
                        </li>
                    );
                })}
            </ul>
        </div>
    );
}

function ExamsPanel({
    exams,
    canManage,
}: {
    exams: Exams;
    canManage: boolean;
}) {
    return (
        <DashboardPanel
            title="Ujian hari ini"
            meta={
                exams.today.length > 0
                    ? `${exams.today.length} ujian dibuka atau berlangsung`
                    : undefined
            }
            action={{
                label: canManage ? 'Kelola ujian' : 'Daftar ujian',
                href: CbtExamController.index(),
            }}
            bodyClassName="p-0 sm:p-0"
        >
            {exams.today.length === 0 ? (
                <p className="px-4 py-4 text-sm text-muted-foreground sm:px-5">
                    Tidak ada ujian yang dibuka hari ini.
                </p>
            ) : (
                <ul className="divide-y divide-border">
                    {exams.today.map((exam) => (
                        <li
                            key={exam.id}
                            className="flex flex-wrap items-center gap-x-4 gap-y-2 px-4 py-3 sm:px-5"
                        >
                            <div className="w-24 shrink-0 text-sm font-medium tabular-nums">
                                {formatTime(exam.opens_at)}–
                                {formatTime(exam.closes_at)}
                            </div>
                            <div className="min-w-0 flex-1">
                                <p className="truncate text-sm font-medium">
                                    {exam.title}
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    {exam.subject}
                                    {exam.status !== 'scheduled' &&
                                        ` · ${exam.in_progress} mengerjakan · ${exam.finished} selesai`}
                                </p>
                            </div>
                            <StatusBadge variant={examVariant[exam.status]}>
                                {exam.status_label}
                            </StatusBadge>
                            {exam.status === 'running' && (
                                <Button size="sm" variant="outline" asChild>
                                    <Link
                                        href={CbtMonitorController.index(
                                            exam.id,
                                        )}
                                    >
                                        Pantau
                                    </Link>
                                </Button>
                            )}
                        </li>
                    ))}
                </ul>
            )}

            {exams.awaiting_essay.length > 0 && (
                <div className="border-t border-border bg-muted/30 px-4 py-3 sm:px-5">
                    <h3 className="mb-2 text-xs font-medium text-muted-foreground">
                        Esai menunggu dinilai
                    </h3>
                    <ul className="space-y-1.5">
                        {exams.awaiting_essay.map((exam) => (
                            <li key={exam.id}>
                                <Link
                                    href={CbtEssayScoreController.index(
                                        exam.id,
                                    )}
                                    className="flex items-center justify-between gap-3 rounded-md text-sm hover:text-primary focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                >
                                    <span className="truncate">
                                        {exam.title}
                                        <span className="text-muted-foreground">
                                            {' '}
                                            · {exam.subject}
                                        </span>
                                    </span>
                                    <span className="shrink-0 font-medium tabular-nums">
                                        {exam.count} siswa
                                    </span>
                                </Link>
                            </li>
                        ))}
                    </ul>
                </div>
            )}
        </DashboardPanel>
    );
}

function LeavesPanel({
    leaves,
    canDecide,
}: {
    leaves: Leaves;
    canDecide: boolean;
}) {
    const waiting = leaves.count > 0;

    return (
        <DashboardPanel
            title="Izin menunggu keputusan"
            meta={
                waiting
                    ? `${leaves.count} pengajuan dari wali`
                    : 'Semua pengajuan sudah diputuskan'
            }
            tone={waiting && canDecide ? 'attention' : 'default'}
            action={
                waiting
                    ? {
                          label: canDecide ? 'Tinjau' : 'Lihat',
                          href: LeaveRequestController.index(),
                      }
                    : undefined
            }
            bodyClassName={waiting ? 'p-0 sm:p-0' : undefined}
        >
            {waiting ? (
                <ul className="divide-y divide-border">
                    {leaves.items.map((leave) => (
                        <li key={leave.id} className="px-4 py-3 sm:px-5">
                            <div className="flex items-center justify-between gap-2">
                                <p className="truncate text-sm font-medium">
                                    {leave.student_name}
                                </p>
                                <StatusBadge variant="muted">
                                    {leave.type_label}
                                </StatusBadge>
                            </div>
                            <p className="text-xs text-muted-foreground">
                                {leave.starts_on === leave.ends_on ? (
                                    <DateText value={leave.starts_on} />
                                ) : (
                                    <>
                                        <DateText value={leave.starts_on} /> –{' '}
                                        <DateText value={leave.ends_on} />
                                    </>
                                )}
                            </p>
                        </li>
                    ))}
                </ul>
            ) : (
                <EmptyLine text="Tidak ada pengajuan izin yang menunggu." />
            )}
        </DashboardPanel>
    );
}

function DataGapsPanel({
    gaps,
    canManageClassrooms,
    canManageGuardians,
}: {
    gaps: NonNullable<Props['dataGaps']>;
    canManageClassrooms: boolean;
    canManageGuardians: boolean;
}) {
    const rows = [
        {
            key: 'classroom',
            count: gaps.students_without_classroom,
            label: 'siswa aktif belum masuk rombel',
            href: StudentController.index({ query: { classroom_id: 'none' } }),
        },
        canManageGuardians && {
            key: 'guardian',
            count: gaps.students_without_guardian,
            label: 'siswa aktif belum punya wali',
            href: GuardianController.index(),
        },
        canManageClassrooms && {
            key: 'homeroom',
            count: gaps.classrooms_without_homeroom,
            label: 'rombel belum punya wali kelas',
            href: ClassroomController.index(),
        },
    ].filter((row) => row !== false && row.count > 0);

    return (
        <DashboardPanel
            title="Perlu dilengkapi"
            meta="Data induk unit dan tahun ajaran terpilih"
        >
            {rows.length === 0 ? (
                <EmptyLine text="Data induk sudah lengkap." />
            ) : (
                <ul className="space-y-1">
                    {rows.map(
                        (row) =>
                            row && (
                                <li key={row.key}>
                                    <Link
                                        href={row.href}
                                        className="-mx-2 flex items-baseline gap-3 rounded-md px-2 py-1.5 text-sm transition-colors duration-150 hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                    >
                                        <span className="w-10 shrink-0 text-right text-lg font-semibold tabular-nums">
                                            {numberFormatter.format(row.count)}
                                        </span>
                                        <span className="text-muted-foreground">
                                            {row.label}
                                        </span>
                                    </Link>
                                </li>
                            ),
                    )}
                </ul>
            )}
        </DashboardPanel>
    );
}

function MutationsPanel({ mutations }: { mutations: MutationSummary[] }) {
    return (
        <DashboardPanel
            title="Mutasi siswa terbaru"
            action={{
                label: 'Semua',
                href: StudentMutationController.index(),
            }}
            bodyClassName={mutations.length > 0 ? 'p-0 sm:p-0' : undefined}
        >
            {mutations.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    Belum ada mutasi masuk, keluar, atau kelulusan di unit ini.
                </p>
            ) : (
                <ul className="divide-y divide-border">
                    {mutations.map((item) => (
                        <li
                            key={item.id}
                            className="flex items-center justify-between gap-3 px-4 py-3 sm:px-5"
                        >
                            <div className="min-w-0">
                                <p className="truncate text-sm font-medium">
                                    {item.student_name}
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    <DateText value={item.mutated_on} />
                                </p>
                            </div>
                            <StatusBadge
                                variant={mutationVariant[item.type.value]}
                            >
                                {item.type.label}
                            </StatusBadge>
                        </li>
                    ))}
                </ul>
            )}
        </DashboardPanel>
    );
}

const FOUNDATION_STATUS: Record<
    NonNullable<Props['foundation']>['modules'][number]['status'],
    { label: string; note: string }
> = {
    held: {
        label: 'Masih di aplikasi lama',
        note: 'Pindah saat peralihan keuangan',
    },
    deferred: {
        label: 'Masih di aplikasi lama',
        note: 'Akuntansi yayasan, menunggu keputusan klien',
    },
    planned: { label: 'Belum aktif', note: 'Kerangka direncanakan' },
    out_of_scope: {
        label: 'Di luar cakupan',
        note: 'Butuh kesepakatan terpisah',
    },
};

/**
 * Baris kartu yayasan dengan susunan sama seperti dasbor aplikasi lama. Isinya jujur:
 * modul yang belum dikelola aplikasi ini menunjukkan statusnya, bukan angka (ADR-021, U-12).
 */
function FoundationRow({
    foundation,
}: {
    foundation: NonNullable<Props['foundation']>;
}) {
    return (
        <section aria-labelledby="foundation-title" className="space-y-3">
            <div className="flex flex-wrap items-end justify-between gap-2">
                <div>
                    <h2 id="foundation-title" className="text-sm font-semibold">
                        Keuangan & layanan yayasan
                    </h2>
                    <p className="text-xs text-muted-foreground">
                        Selama masa paralel, angka keuangan dikelola aplikasi
                        lama.
                    </p>
                </div>
                {foundation.legacy_url !== '' && (
                    <Button size="sm" variant="outline" asChild>
                        <a
                            href={foundation.legacy_url}
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            Buka dasbor keuangan lama
                        </a>
                    </Button>
                )}
            </div>
            <ul className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                {foundation.modules.map((module) => {
                    const status = FOUNDATION_STATUS[module.status];

                    return (
                        <li
                            key={module.key}
                            className="flex flex-col rounded-xl border border-dashed border-border bg-card/60 px-4 py-3"
                        >
                            <p className="text-sm font-semibold">
                                {module.label}
                            </p>
                            <p className="text-xs text-muted-foreground">
                                {module.detail}
                            </p>
                            <div className="mt-3 flex items-center gap-2">
                                <StatusBadge
                                    variant={
                                        module.status === 'out_of_scope'
                                            ? 'muted'
                                            : 'default'
                                    }
                                >
                                    {status.label}
                                </StatusBadge>
                            </div>
                            <p className="mt-1.5 text-[11px] text-muted-foreground">
                                {status.note}
                            </p>
                        </li>
                    );
                })}
            </ul>
        </section>
    );
}

/**
 * Dasbor keuangan nyata di beranda staf (T-617): muncul otomatis saat `billing` atau `wallet` aktif,
 * menggantikan kartu "keuangan masih di aplikasi lama".
 */
function FinanceSection({ finance }: { finance: FinanceDashboard }) {
    const scopeLabel =
        finance.scope === 'all'
            ? 'Semua unit'
            : finance.unit_code
              ? `${finance.unit_code}${finance.unit_name ? ` — ${finance.unit_name}` : ''}`
              : 'Unit terpilih';

    const switchScope = (nextScope: 'unit' | 'all') => {
        router.get(
            dashboard(),
            nextScope === 'all' ? { finance_scope: 'all' } : {},
            { preserveScroll: true, preserveState: true },
        );
    };

    const cashIn = finance.cash_in;
    const receivables = finance.receivables;
    const online = finance.online_unreconciled;
    const wallet = finance.wallet;

    return (
        <section aria-labelledby="finance-title" className="space-y-4">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 id="finance-title" className="text-base font-semibold">
                        Keuangan sekolah
                    </h2>
                    <p className="text-xs text-muted-foreground">
                        Cakupan: {scopeLabel}
                    </p>
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    {finance.can_view_all_units && (
                        <div
                            role="group"
                            aria-label="Cakupan unit keuangan"
                            className="inline-flex rounded-md border border-border bg-card p-0.5"
                        >
                            <button
                                type="button"
                                onClick={() => switchScope('unit')}
                                aria-pressed={finance.scope === 'unit'}
                                className={cn(
                                    'rounded px-2.5 py-1 text-xs font-medium transition-colors',
                                    finance.scope === 'unit'
                                        ? 'bg-primary text-primary-foreground'
                                        : 'text-muted-foreground hover:text-foreground',
                                )}
                            >
                                Unit terpilih
                            </button>
                            <button
                                type="button"
                                onClick={() => switchScope('all')}
                                aria-pressed={finance.scope === 'all'}
                                className={cn(
                                    'rounded px-2.5 py-1 text-xs font-medium transition-colors',
                                    finance.scope === 'all'
                                        ? 'bg-primary text-primary-foreground'
                                        : 'text-muted-foreground hover:text-foreground',
                                )}
                            >
                                Semua unit
                            </button>
                        </div>
                    )}

                    {finance.billing_enabled && (
                        <>
                            {cashIn && cashIn.pending_transfer_proofs > 0 && (
                                <Button size="sm" variant="outline" asChild>
                                    <Link
                                        href={TransferProofController.index()}
                                    >
                                        <span className="inline-block size-2 rounded-full bg-accent-attention" />
                                        Bukti transfer (
                                        {numberFormatter.format(
                                            cashIn.pending_transfer_proofs,
                                        )}
                                        )
                                    </Link>
                                </Button>
                            )}
                            <Button size="sm" variant="outline" asChild>
                                <Link href={InvoiceController.index()}>
                                    Daftar tagihan
                                </Link>
                            </Button>
                            <Button size="sm" asChild>
                                <Link href={CashierController.index()}>
                                    Buka kasir
                                </Link>
                            </Button>
                        </>
                    )}
                </div>
            </div>

            {cashIn && (
                <DashboardPanel
                    title="Kas masuk"
                    meta="Pembayaran sah per periode dan metode (WITA)"
                >
                    <div className="grid gap-4 sm:grid-cols-3">
                        {(
                            [
                                { key: 'today', label: 'Hari ini' },
                                { key: 'month', label: 'Bulan ini' },
                                { key: 'academic_year', label: 'Tahun ajaran' },
                            ] as const
                        ).map(({ key, label }) => {
                            const period = cashIn[key];

                            return (
                                <div
                                    key={key}
                                    className="rounded-lg border border-border bg-muted/30 p-3.5"
                                >
                                    <div className="flex items-baseline justify-between gap-2">
                                        <span className="text-xs font-medium text-muted-foreground">
                                            {label}
                                        </span>
                                        <span className="text-xs text-muted-foreground tabular-nums">
                                            {numberFormatter.format(
                                                period.count,
                                            )}{' '}
                                            transaksi
                                        </span>
                                    </div>
                                    <p className="mt-1 text-xl font-semibold">
                                        <MoneyText amount={period.total} />
                                    </p>
                                    <dl className="mt-3 space-y-1 border-t border-border pt-2 text-xs">
                                        <div className="flex justify-between gap-2">
                                            <dt className="text-muted-foreground">
                                                Tunai
                                            </dt>
                                            <dd>
                                                <MoneyText
                                                    amount={
                                                        period.by_method.cash
                                                    }
                                                />
                                            </dd>
                                        </div>
                                        <div className="flex justify-between gap-2">
                                            <dt className="text-muted-foreground">
                                                Transfer
                                            </dt>
                                            <dd>
                                                <MoneyText
                                                    amount={
                                                        period.by_method
                                                            .transfer
                                                    }
                                                />
                                            </dd>
                                        </div>
                                        <div className="flex justify-between gap-2">
                                            <dt className="text-muted-foreground">
                                                Midtrans
                                            </dt>
                                            <dd>
                                                <MoneyText
                                                    amount={
                                                        period.by_method
                                                            .midtrans
                                                    }
                                                />
                                            </dd>
                                        </div>
                                    </dl>
                                </div>
                            );
                        })}
                    </div>
                </DashboardPanel>
            )}

            {receivables && (
                <div className="grid items-start gap-6 lg:grid-cols-3">
                    <div className="space-y-6 lg:col-span-2">
                        <DashboardPanel
                            title="Piutang tagihan"
                            meta="Sisa tagihan aktif per jenis tagihan dan rombel"
                            action={{
                                label: 'Lihat semua',
                                href: InvoiceController.index(),
                            }}
                        >
                            <div className="grid gap-3 border-b border-border pb-4 sm:grid-cols-2">
                                <div className="rounded-lg border border-border bg-muted/30 px-3.5 py-2.5">
                                    <p className="text-xs text-muted-foreground">
                                        Total piutang aktif
                                    </p>
                                    <p className="mt-0.5 text-lg font-semibold">
                                        <MoneyText amount={receivables.total} />
                                    </p>
                                </div>
                                <div className="rounded-lg border border-border bg-muted/30 px-3.5 py-2.5">
                                    <p className="text-xs text-muted-foreground">
                                        Sudah jatuh tempo (
                                        {numberFormatter.format(
                                            receivables.overdue_count,
                                        )}{' '}
                                        tagihan)
                                    </p>
                                    <p
                                        className={cn(
                                            'mt-0.5 text-lg font-semibold',
                                            receivables.overdue > 0 &&
                                                'text-destructive',
                                        )}
                                    >
                                        <MoneyText
                                            amount={receivables.overdue}
                                        />
                                    </p>
                                </div>
                            </div>

                            {receivables.by_fee_type.length === 0 &&
                            receivables.by_classroom.length === 0 ? (
                                <p className="pt-4 text-sm text-muted-foreground">
                                    Belum ada piutang tagihan aktif pada cakupan
                                    ini.
                                </p>
                            ) : (
                                <div className="mt-4 grid gap-5 md:grid-cols-2">
                                    <div>
                                        <h3 className="mb-2 text-xs font-semibold text-muted-foreground">
                                            Per jenis tagihan
                                        </h3>
                                        <div className="overflow-x-auto">
                                            <table className="w-full text-sm">
                                                <thead className="text-xs text-muted-foreground">
                                                    <tr className="border-b border-border">
                                                        <th className="py-1.5 pr-2 text-left font-medium">
                                                            Jenis
                                                        </th>
                                                        <th className="px-2 py-1.5 text-right font-medium">
                                                            Sisa
                                                        </th>
                                                        <th className="py-1.5 pl-2 text-right font-medium">
                                                            Menunggak
                                                        </th>
                                                    </tr>
                                                </thead>
                                                <tbody className="divide-y divide-border">
                                                    {receivables.by_fee_type.map(
                                                        (row) => (
                                                            <tr
                                                                key={
                                                                    row.fee_type_id
                                                                }
                                                            >
                                                                <td className="py-2 pr-2 font-medium">
                                                                    {row.name}
                                                                </td>
                                                                <td className="px-2 py-2 text-right">
                                                                    <MoneyText
                                                                        amount={
                                                                            row.total
                                                                        }
                                                                    />
                                                                </td>
                                                                <td
                                                                    className={cn(
                                                                        'py-2 pl-2 text-right',
                                                                        row.overdue >
                                                                            0 &&
                                                                            'text-destructive',
                                                                    )}
                                                                >
                                                                    <MoneyText
                                                                        amount={
                                                                            row.overdue
                                                                        }
                                                                    />
                                                                </td>
                                                            </tr>
                                                        ),
                                                    )}
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>

                                    <div>
                                        <h3 className="mb-2 text-xs font-semibold text-muted-foreground">
                                            Per rombel
                                        </h3>
                                        <div className="overflow-x-auto">
                                            <table className="w-full text-sm">
                                                <thead className="text-xs text-muted-foreground">
                                                    <tr className="border-b border-border">
                                                        <th className="py-1.5 pr-2 text-left font-medium">
                                                            Rombel
                                                        </th>
                                                        <th className="px-2 py-1.5 text-right font-medium">
                                                            Sisa
                                                        </th>
                                                        <th className="py-1.5 pl-2 text-right font-medium">
                                                            Menunggak
                                                        </th>
                                                    </tr>
                                                </thead>
                                                <tbody className="divide-y divide-border">
                                                    {receivables.by_classroom.map(
                                                        (row) => (
                                                            <tr
                                                                key={
                                                                    row.classroom_id ??
                                                                    'none'
                                                                }
                                                            >
                                                                <td className="py-2 pr-2 font-medium">
                                                                    {row.name}
                                                                </td>
                                                                <td className="px-2 py-2 text-right">
                                                                    <MoneyText
                                                                        amount={
                                                                            row.total
                                                                        }
                                                                    />
                                                                </td>
                                                                <td
                                                                    className={cn(
                                                                        'py-2 pl-2 text-right',
                                                                        row.overdue >
                                                                            0 &&
                                                                            'text-destructive',
                                                                    )}
                                                                >
                                                                    <MoneyText
                                                                        amount={
                                                                            row.overdue
                                                                        }
                                                                    />
                                                                </td>
                                                            </tr>
                                                        ),
                                                    )}
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            )}
                        </DashboardPanel>
                    </div>

                    <DashboardPanel
                        title="Tunggakan teratas"
                        meta="Siswa dengan tagihan jatuh tempo terbesar"
                        tone={
                            receivables.top_arrears.length > 0
                                ? 'attention'
                                : 'default'
                        }
                    >
                        {receivables.top_arrears.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                Tidak ada tagihan jatuh tempo pada cakupan ini.
                            </p>
                        ) : (
                            <ul className="divide-y divide-border">
                                {receivables.top_arrears.map((item) => (
                                    <li
                                        key={item.student_id}
                                        className="flex items-center justify-between gap-3 py-2.5 first:pt-0 last:pb-0"
                                    >
                                        <div className="min-w-0">
                                            <Link
                                                href={InvoiceController.studentCard(
                                                    {
                                                        student:
                                                            item.student_id,
                                                    },
                                                )}
                                                className="block truncate text-sm font-medium hover:text-primary hover:underline"
                                            >
                                                {item.student_name}
                                            </Link>
                                            <p className="text-xs text-muted-foreground">
                                                {item.nis} · {item.classroom} ·{' '}
                                                {numberFormatter.format(
                                                    item.overdue_count,
                                                )}{' '}
                                                tagihan
                                            </p>
                                        </div>
                                        <span className="shrink-0 text-sm font-semibold text-destructive">
                                            <MoneyText
                                                amount={item.overdue_amount}
                                            />
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </DashboardPanel>
                </div>
            )}

            <div className="grid gap-6 md:grid-cols-2">
                <DashboardPanel
                    title="Pembayaran online"
                    meta="Order Midtrans yang menunggu konfirmasi atau rekonsiliasi"
                >
                    {!online.available ? (
                        <p className="text-sm text-muted-foreground">
                            {online.midtrans_enabled
                                ? 'Tabel pembayaran online belum tersedia.'
                                : 'Pembayaran online (Midtrans) belum diaktifkan.'}
                        </p>
                    ) : online.pending_count === 0 &&
                      online.needs_review_count === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            Semua pembayaran online sudah terekonsiliasi.
                        </p>
                    ) : (
                        <dl className="grid grid-cols-2 gap-3 text-sm">
                            <div className="rounded-lg border border-border bg-muted/30 px-3 py-2.5">
                                <dt className="text-xs text-muted-foreground">
                                    Menunggu konfirmasi
                                </dt>
                                <dd className="mt-0.5 font-semibold tabular-nums">
                                    {numberFormatter.format(
                                        online.pending_count,
                                    )}{' '}
                                    order ·{' '}
                                    <MoneyText amount={online.pending_amount} />
                                </dd>
                            </div>
                            <div className="rounded-lg border border-border bg-muted/30 px-3 py-2.5">
                                <dt className="text-xs text-muted-foreground">
                                    Perlu tinjauan
                                </dt>
                                <dd
                                    className={cn(
                                        'mt-0.5 font-semibold tabular-nums',
                                        online.needs_review_count > 0 &&
                                            'text-destructive',
                                    )}
                                >
                                    {numberFormatter.format(
                                        online.needs_review_count,
                                    )}{' '}
                                    order
                                </dd>
                            </div>
                        </dl>
                    )}
                </DashboardPanel>

                {wallet && (
                    <DashboardPanel
                        title="Dompet kantin"
                        meta="Saldo mengendap dan isi saldo (top-up) siswa"
                    >
                        {!wallet.available ? (
                            <p className="text-sm text-muted-foreground">
                                Data dompet kantin belum tersedia.
                            </p>
                        ) : (
                            <dl className="grid grid-cols-2 gap-3 text-sm sm:grid-cols-3">
                                <div className="rounded-lg border border-border bg-muted/30 px-3 py-2.5">
                                    <dt className="text-xs text-muted-foreground">
                                        Saldo mengendap
                                    </dt>
                                    <dd className="mt-0.5 font-semibold">
                                        <MoneyText
                                            amount={wallet.total_balance}
                                        />
                                    </dd>
                                </div>
                                <div className="rounded-lg border border-border bg-muted/30 px-3 py-2.5">
                                    <dt className="text-xs text-muted-foreground">
                                        Top-up hari ini
                                    </dt>
                                    <dd className="mt-0.5 font-semibold">
                                        <MoneyText
                                            amount={wallet.topup_today}
                                        />
                                    </dd>
                                </div>
                                <div className="rounded-lg border border-border bg-muted/30 px-3 py-2.5">
                                    <dt className="text-xs text-muted-foreground">
                                        Top-up bulan ini
                                    </dt>
                                    <dd className="mt-0.5 font-semibold">
                                        <MoneyText
                                            amount={wallet.topup_month}
                                        />
                                    </dd>
                                </div>
                            </dl>
                        )}
                    </DashboardPanel>
                )}
            </div>

            {finance.by_unit.length > 0 && (
                <DashboardPanel
                    title="Ringkasan keuangan per unit"
                    meta="Kas masuk bulan ini dan piutang aktif per unit"
                    bodyClassName="p-0 sm:p-0"
                >
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[520px] text-sm">
                            <thead className="text-xs text-muted-foreground">
                                <tr className="border-b border-border">
                                    <th className="px-4 py-2 text-left font-medium sm:px-5">
                                        Unit
                                    </th>
                                    <th className="px-3 py-2 text-right font-medium">
                                        Kas masuk bulan ini
                                    </th>
                                    <th className="px-3 py-2 text-right font-medium">
                                        Piutang aktif
                                    </th>
                                    <th className="px-4 py-2 text-right font-medium sm:px-5">
                                        Menunggak
                                    </th>
                                    {finance.wallet_enabled && (
                                        <th className="px-4 py-2 text-right font-medium sm:px-5">
                                            Saldo dompet
                                        </th>
                                    )}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {finance.by_unit.map((unit) => (
                                    <tr key={unit.code}>
                                        <td className="px-4 py-2.5 sm:px-5">
                                            <div className="font-medium">
                                                {unit.code}
                                            </div>
                                            <div className="text-xs text-muted-foreground">
                                                {unit.name}
                                            </div>
                                        </td>
                                        <td className="px-3 py-2.5 text-right">
                                            <MoneyText
                                                amount={unit.cash_in_month}
                                            />
                                        </td>
                                        <td className="px-3 py-2.5 text-right">
                                            <MoneyText
                                                amount={unit.receivables_total}
                                            />
                                        </td>
                                        <td
                                            className={cn(
                                                'px-4 py-2.5 text-right sm:px-5',
                                                unit.receivables_overdue > 0 &&
                                                    'font-medium text-destructive',
                                            )}
                                        >
                                            <MoneyText
                                                amount={
                                                    unit.receivables_overdue
                                                }
                                            />
                                        </td>
                                        {finance.wallet_enabled && (
                                            <td className="px-4 py-2.5 text-right sm:px-5">
                                                <MoneyText
                                                    amount={unit.wallet_balance}
                                                />
                                            </td>
                                        )}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </DashboardPanel>
            )}
        </section>
    );
}

function UnitsPanel({ units }: { units: NonNullable<Props['units']> }) {
    return (
        <DashboardPanel
            title="Perbandingan unit hari ini"
            meta="Siswa aktif, rombel, dan presensi gerbang per unit"
            bodyClassName="p-0 sm:p-0"
        >
            <div className="overflow-x-auto">
                <table className="w-full min-w-[520px] text-sm">
                    <thead className="text-xs text-muted-foreground">
                        <tr className="border-b border-border">
                            <th className="px-4 py-2 text-left font-medium sm:px-5">
                                Unit
                            </th>
                            <th className="px-3 py-2 text-right font-medium">
                                Siswa
                            </th>
                            <th className="px-3 py-2 text-right font-medium">
                                Rombel
                            </th>
                            <th className="w-40 px-3 py-2 text-left font-medium">
                                Tercatat
                            </th>
                            <th className="px-3 py-2 text-right font-medium">
                                Terlambat
                            </th>
                            <th className="px-4 py-2 text-right font-medium sm:px-5">
                                Alpa
                            </th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-border">
                        {units.map((unit) => {
                            const pct =
                                unit.students > 0
                                    ? Math.round(
                                          (unit.recorded / unit.students) * 100,
                                      )
                                    : 0;

                            return (
                                <tr key={unit.code}>
                                    <td className="px-4 py-2.5 sm:px-5">
                                        <div className="font-medium">
                                            {unit.code}
                                        </div>
                                        <div className="text-xs text-muted-foreground">
                                            {unit.name}
                                        </div>
                                    </td>
                                    <td className="px-3 py-2.5 text-right tabular-nums">
                                        {numberFormatter.format(unit.students)}
                                    </td>
                                    <td className="px-3 py-2.5 text-right tabular-nums">
                                        {numberFormatter.format(
                                            unit.classrooms,
                                        )}
                                    </td>
                                    <td className="px-3 py-2.5">
                                        <div className="flex items-center gap-2">
                                            <span
                                                className="h-1.5 flex-1 overflow-hidden rounded-full bg-muted"
                                                aria-hidden
                                            >
                                                <span
                                                    className="block h-full rounded-full bg-primary/75"
                                                    style={{
                                                        width: `${Math.min(pct, 100)}%`,
                                                    }}
                                                />
                                            </span>
                                            <span className="w-9 text-right text-xs tabular-nums">
                                                {pct}%
                                            </span>
                                        </div>
                                    </td>
                                    <td className="px-3 py-2.5 text-right tabular-nums">
                                        {unit.late}
                                    </td>
                                    <td
                                        className={cn(
                                            'px-4 py-2.5 text-right tabular-nums sm:px-5',
                                            unit.absent > 0 &&
                                                'font-medium text-destructive',
                                        )}
                                    >
                                        {unit.absent}
                                    </td>
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
            </div>
        </DashboardPanel>
    );
}

function SecurityPanel({
    security,
}: {
    security: NonNullable<Props['security']>;
}) {
    return (
        <DashboardPanel
            title="Keamanan akun"
            meta={
                security.failed_logins_24h > 0
                    ? `${security.failed_logins_24h} percobaan login gagal dalam 24 jam`
                    : 'Tidak ada login gagal dalam 24 jam'
            }
            tone={security.failed_logins_24h >= 10 ? 'attention' : 'default'}
            bodyClassName={
                security.recent_logins.length > 0 ? 'p-0 sm:p-0' : undefined
            }
        >
            {security.recent_logins.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    Belum ada staf yang login.
                </p>
            ) : (
                <ul className="divide-y divide-border">
                    {security.recent_logins.map((login, index) => (
                        <li
                            key={`${login.name}-${index}`}
                            className="flex items-center justify-between gap-3 px-4 py-2.5 text-sm sm:px-5"
                        >
                            <span className="truncate">{login.name}</span>
                            <span className="shrink-0 text-xs text-muted-foreground">
                                <DateText value={login.at} mode="datetime" />
                            </span>
                        </li>
                    ))}
                </ul>
            )}
        </DashboardPanel>
    );
}

function QueuePanel({
    queue,
    heldModules,
}: {
    queue: NonNullable<Props['queue']>;
    heldModules: string[];
}) {
    return (
        <DashboardPanel title="Status sistem">
            <dl className="grid grid-cols-2 gap-3">
                <div className="rounded-lg border border-border bg-background/60 px-3 py-2">
                    <dt className="text-xs text-muted-foreground">Menunggu</dt>
                    <dd className="text-lg font-semibold tabular-nums">
                        {numberFormatter.format(queue.pending_jobs)}
                    </dd>
                </div>
                <div
                    className={cn(
                        'rounded-lg border px-3 py-2',
                        queue.failed_jobs > 0
                            ? 'border-destructive/40 bg-destructive/10'
                            : 'border-border bg-background/60',
                    )}
                >
                    <dt className="text-xs text-muted-foreground">Gagal</dt>
                    <dd
                        className={cn(
                            'text-lg font-semibold tabular-nums',
                            queue.failed_jobs > 0 && 'text-destructive',
                        )}
                    >
                        {numberFormatter.format(queue.failed_jobs)}
                    </dd>
                </div>
            </dl>
            {queue.failed_jobs > 0 && (
                <p className="mt-2 text-xs text-muted-foreground">
                    Notifikasi ke wali bisa tertunda. Periksa log server dan
                    jalankan ulang tugas yang gagal.
                </p>
            )}
            <dl className="mt-3 space-y-1.5 border-t border-border pt-3 text-sm">
                <div className="flex justify-between gap-3">
                    <dt className="text-muted-foreground">Cadangan data</dt>
                    <dd>Belum diatur</dd>
                </div>
                {heldModules.length > 0 && (
                    <div className="flex justify-between gap-3">
                        <dt className="text-muted-foreground">
                            Modul keuangan
                        </dt>
                        <dd className="text-right">Nonaktif (masa paralel)</dd>
                    </div>
                )}
            </dl>
        </DashboardPanel>
    );
}

function EmptyLine({ text }: { text: string }) {
    return (
        <p className="flex items-center gap-2 text-sm text-muted-foreground">
            <CircleCheck
                className="size-4 shrink-0 text-success"
                strokeWidth={1.75}
            />
            {text}
        </p>
    );
}
