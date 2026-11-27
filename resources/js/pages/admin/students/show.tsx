import { Head, Link } from '@inertiajs/react';
import { Pencil } from 'lucide-react';
import { useState } from 'react';
import StudentController from '@/actions/App/Http/Controllers/Admin/StudentController';
import { DateText } from '@/components/app/date-text';
import type { StatusBadgeVariant } from '@/components/app/status-badge';
import { StatusBadge } from '@/components/app/status-badge';
import { Button } from '@/components/ui/button';
import StaffLayout from '@/layouts/staff-layout';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes/admin';

type Level = {
    name: string;
    level: string | null;
    level_label: string;
    description: string | null;
};

type Props = {
    student: {
        id: number;
        name: string;
        nickname: string | null;
        nis: string;
        nisn: string | null;
        gender: string;
        birth: string;
        address: string | null;
        status: string;
        status_label: string;
        unit: string;
        unit_name: string;
        classroom: string | null;
        photo_url: string | null;
    };
    guardians: {
        name: string;
        phone: string;
        relation: string;
        is_primary: boolean;
    }[];
    enrollments: { year: string; classroom: string; is_active: boolean }[];
    attendance: {
        month: string;
        counts: Record<
            'present' | 'late' | 'excused' | 'sick' | 'absent',
            number
        >;
        days: {
            date: string;
            status: string;
            label: string;
            in: string | null;
            out: string | null;
        }[];
    } | null;
    behavior: {
        merit: number;
        violation: number;
        records: {
            id: number;
            date: string;
            rule: string;
            category: 'violation' | 'achievement' | 'good_deed';
            category_label: string;
            points: number;
            voided: boolean;
        }[];
    } | null;
    habits: {
        verified: number;
        pending: number;
        rejected: number;
        days_logged: number;
    } | null;
    quran: {
        quran: {
            achieved: number;
            target: number;
            pct: number;
            total_memorized_ayahs: number;
        };
        hadith: { achieved: number; target: number; pct: number };
        prayer: { achieved: number; target: number; pct: number };
    } | null;
    character: {
        status: string;
        status_label: string;
        semester: string;
        dimensions: Level[];
    } | null;
    exams:
        | { title: string; subject: string; score: number; date: string }[]
        | null;
    can: { manage: boolean };
};

const ATTENDANCE: {
    key: keyof NonNullable<Props['attendance']>['counts'];
    label: string;
    variant: StatusBadgeVariant;
}[] = [
    { key: 'present', label: 'Hadir', variant: 'success' },
    { key: 'late', label: 'Terlambat', variant: 'warning' },
    { key: 'excused', label: 'Izin', variant: 'default' },
    { key: 'sick', label: 'Sakit', variant: 'default' },
    { key: 'absent', label: 'Alpa', variant: 'destructive' },
];

const STATUS_VARIANT: Record<string, StatusBadgeVariant> = {
    present: 'success',
    late: 'warning',
    excused: 'default',
    sick: 'default',
    absent: 'destructive',
};

export default function StudentShow(props: Props) {
    const {
        student,
        guardians,
        attendance,
        behavior,
        habits,
        quran,
        character,
        exams,
        enrollments,
        can,
    } = props;

    const tabs = [
        attendance && { key: 'attendance', label: 'Presensi' },
        behavior && { key: 'behavior', label: 'Perilaku' },
        habits && { key: 'habits', label: 'Pembiasaan' },
        quran && { key: 'quran', label: 'Hafalan' },
        character && { key: 'character', label: 'Karakter' },
        exams && { key: 'exams', label: 'Ujian' },
        { key: 'enrollments', label: 'Riwayat rombel' },
    ].filter((t): t is { key: string; label: string } => Boolean(t));

    const [tab, setTab] = useState(tabs[0].key);
    const primary = guardians.find((g) => g.is_primary) ?? guardians[0];
    const recorded = attendance
        ? Object.values(attendance.counts).reduce((a, b) => a + b, 0)
        : 0;
    const onTime = attendance
        ? Math.round(
              ((attendance.counts.present + attendance.counts.late) /
                  Math.max(recorded, 1)) *
                  100,
          )
        : null;

    return (
        <StaffLayout
            breadcrumbs={[
                { title: 'Data Induk', href: dashboard() },
                { title: 'Siswa', href: StudentController.index() },
                { title: student.name, href: '' },
            ]}
        >
            <Head title={student.name} />

            <div className="space-y-6">
                <section className="overflow-hidden rounded-xl border border-border bg-card shadow-xs">
                    <div className="flex flex-col gap-5 p-5 sm:flex-row sm:items-start sm:p-6">
                        {student.photo_url ? (
                            <img
                                src={student.photo_url}
                                alt={`Foto ${student.name}`}
                                className="size-24 shrink-0 rounded-lg border border-border object-cover"
                            />
                        ) : (
                            <div
                                className="flex size-24 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-2xl font-semibold text-primary"
                                aria-hidden
                            >
                                {student.name
                                    .split(/\s+/)
                                    .slice(0, 2)
                                    .map((p) => p[0])
                                    .join('')
                                    .toUpperCase()}
                            </div>
                        )}

                        <div className="min-w-0 flex-1">
                            <div className="flex flex-wrap items-start justify-between gap-3">
                                <div className="min-w-0">
                                    <h1 className="text-2xl font-semibold tracking-tight">
                                        {student.name}
                                    </h1>
                                    <p className="text-sm text-muted-foreground">
                                        NIS {student.nis}
                                        {student.classroom
                                            ? ` · ${student.classroom}`
                                            : ' · Belum masuk rombel'}{' '}
                                        · Unit {student.unit}
                                    </p>
                                    <div className="mt-2">
                                        <StatusBadge
                                            variant={
                                                student.status === 'active'
                                                    ? 'success'
                                                    : 'muted'
                                            }
                                        >
                                            {student.status_label}
                                        </StatusBadge>
                                    </div>
                                </div>
                                {can.manage && (
                                    <Button variant="outline" asChild>
                                        <Link
                                            href={StudentController.index({
                                                query: { search: student.nis },
                                            })}
                                        >
                                            <Pencil
                                                className="size-4"
                                                strokeWidth={1.75}
                                            />
                                            Ubah
                                        </Link>
                                    </Button>
                                )}
                            </div>

                            <dl className="mt-5 grid gap-4 border-t border-border pt-4 sm:grid-cols-2 lg:grid-cols-4">
                                <Fact
                                    label="Wali utama"
                                    value={
                                        primary
                                            ? `${primary.name} · ${primary.phone}`
                                            : 'Belum ada wali'
                                    }
                                    muted={!primary}
                                />
                                {onTime !== null && (
                                    <Fact
                                        label={`Kehadiran ${attendance?.month}`}
                                        value={
                                            recorded > 0
                                                ? `${onTime}% hadir`
                                                : 'Belum tercatat'
                                        }
                                        muted={recorded === 0}
                                    />
                                )}
                                {behavior && (
                                    <Fact
                                        label="Poin tahun ini"
                                        value={`+${behavior.merit} kebaikan · ${behavior.violation} pelanggaran`}
                                    />
                                )}
                                {quran && (
                                    <Fact
                                        label="Hafalan Al-Qur'an"
                                        value={`${quran.quran.total_memorized_ayahs} ayat`}
                                    />
                                )}
                            </dl>
                        </div>
                    </div>

                    <div
                        role="tablist"
                        aria-label="Bagian profil"
                        className="flex gap-1 overflow-x-auto border-t border-border bg-muted/40 px-3 sm:px-5"
                    >
                        {tabs.map((t) => (
                            <button
                                key={t.key}
                                type="button"
                                role="tab"
                                aria-selected={tab === t.key}
                                onClick={() => setTab(t.key)}
                                className={cn(
                                    '-mb-px border-b-2 px-3 py-2.5 text-sm whitespace-nowrap transition-colors duration-150 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                                    tab === t.key
                                        ? 'border-primary font-semibold text-foreground'
                                        : 'border-transparent text-muted-foreground hover:text-foreground',
                                )}
                            >
                                {t.label}
                            </button>
                        ))}
                    </div>
                </section>

                <div role="tabpanel" className="grid gap-6 lg:grid-cols-3">
                    <div className="lg:col-span-2">
                        {tab === 'attendance' && attendance && (
                            <Panel title={`Presensi ${attendance.month}`}>
                                <div className="mb-4 flex flex-wrap gap-2">
                                    {ATTENDANCE.map((a) => (
                                        <StatusBadge
                                            key={a.key}
                                            variant={a.variant}
                                        >
                                            {a.label} {attendance.counts[a.key]}
                                        </StatusBadge>
                                    ))}
                                </div>
                                <Rows
                                    empty="Belum ada presensi bulan ini."
                                    items={[...attendance.days]
                                        .reverse()
                                        .map((d) => ({
                                            key: d.date,
                                            left: <DateText value={d.date} />,
                                            middle: d.in
                                                ? `Masuk ${d.in}${d.out ? ` · Pulang ${d.out}` : ''}`
                                                : '—',
                                            right: (
                                                <StatusBadge
                                                    variant={
                                                        STATUS_VARIANT[
                                                            d.status
                                                        ] ?? 'muted'
                                                    }
                                                >
                                                    {d.label}
                                                </StatusBadge>
                                            ),
                                        }))}
                                />
                            </Panel>
                        )}

                        {tab === 'behavior' && behavior && (
                            <Panel title="Catatan perilaku tahun ini">
                                <Rows
                                    empty="Belum ada catatan perilaku tahun ini."
                                    items={behavior.records.map((r) => ({
                                        key: String(r.id),
                                        left: <DateText value={r.date} />,
                                        middle: (
                                            <span
                                                className={cn(
                                                    r.voided &&
                                                        'text-muted-foreground line-through',
                                                )}
                                            >
                                                {r.rule}
                                            </span>
                                        ),
                                        right: (
                                            <StatusBadge
                                                variant={
                                                    r.voided
                                                        ? 'muted'
                                                        : r.category ===
                                                            'violation'
                                                          ? 'destructive'
                                                          : 'success'
                                                }
                                            >
                                                {r.voided
                                                    ? 'Dibatalkan'
                                                    : `${r.category === 'violation' ? '−' : '+'}${r.points}`}
                                            </StatusBadge>
                                        ),
                                    }))}
                                />
                            </Panel>
                        )}

                        {tab === 'habits' && habits && (
                            <Panel title="Pembiasaan bulan ini">
                                <dl className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                                    <Stat
                                        label="Hari dicentang"
                                        value={habits.days_logged}
                                    />
                                    <Stat
                                        label="Terverifikasi"
                                        value={habits.verified}
                                    />
                                    <Stat
                                        label="Menunggu"
                                        value={habits.pending}
                                    />
                                    <Stat
                                        label="Ditolak"
                                        value={habits.rejected}
                                    />
                                </dl>
                            </Panel>
                        )}

                        {tab === 'quran' && quran && (
                            <Panel title="Capaian hafalan tahun ajaran ini">
                                <div className="space-y-4">
                                    <Progress
                                        label="Al-Qur'an"
                                        achieved={quran.quran.achieved}
                                        target={quran.quran.target}
                                        pct={quran.quran.pct}
                                    />
                                    <Progress
                                        label="Hadits"
                                        achieved={quran.hadith.achieved}
                                        target={quran.hadith.target}
                                        pct={quran.hadith.pct}
                                    />
                                    <Progress
                                        label="Doa"
                                        achieved={quran.prayer.achieved}
                                        target={quran.prayer.target}
                                        pct={quran.prayer.pct}
                                    />
                                </div>
                            </Panel>
                        )}

                        {tab === 'character' && character && (
                            <Panel
                                title={`Penilaian karakter ${character.semester}`}
                                meta={character.status_label}
                            >
                                <Rows
                                    empty="Belum dinilai."
                                    items={character.dimensions.map((d) => ({
                                        key: d.name,
                                        left: d.name,
                                        middle: (
                                            <span className="text-muted-foreground">
                                                {d.description ?? '—'}
                                            </span>
                                        ),
                                        right: (
                                            <span className="font-semibold text-primary">
                                                {d.level_label}
                                            </span>
                                        ),
                                    }))}
                                />
                            </Panel>
                        )}

                        {tab === 'exams' && exams && (
                            <Panel title="Hasil ujian">
                                <Rows
                                    empty="Belum ada hasil ujian final."
                                    items={exams.map((e, i) => ({
                                        key: String(i),
                                        left: <DateText value={e.date} />,
                                        middle: `${e.subject} · ${e.title}`,
                                        right: (
                                            <span className="text-lg font-semibold tabular-nums">
                                                {e.score}
                                            </span>
                                        ),
                                    }))}
                                />
                            </Panel>
                        )}

                        {tab === 'enrollments' && (
                            <Panel title="Riwayat rombel">
                                <Rows
                                    empty="Belum pernah masuk rombel."
                                    items={enrollments.map((e) => ({
                                        key: e.year,
                                        left: e.year,
                                        middle: e.classroom,
                                        right: e.is_active ? (
                                            <StatusBadge variant="default">
                                                Aktif
                                            </StatusBadge>
                                        ) : null,
                                    }))}
                                />
                            </Panel>
                        )}
                    </div>

                    <div className="space-y-6">
                        <Panel title="Identitas">
                            <dl className="space-y-2 text-sm">
                                <Line
                                    label="Panggilan"
                                    value={student.nickname}
                                />
                                <Line label="NISN" value={student.nisn} />
                                <Line
                                    label="Jenis kelamin"
                                    value={student.gender}
                                />
                                <Line
                                    label="Lahir"
                                    value={student.birth || null}
                                />
                                <Line label="Alamat" value={student.address} />
                                <Line label="Unit" value={student.unit_name} />
                            </dl>
                        </Panel>
                        <Panel title="Wali">
                            {guardians.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    Belum ada wali terhubung.
                                </p>
                            ) : (
                                <ul className="space-y-3">
                                    {guardians.map((g) => (
                                        <li key={g.phone} className="text-sm">
                                            <p className="font-medium">
                                                {g.name}
                                                {g.is_primary && (
                                                    <span className="ml-2 text-xs font-normal text-muted-foreground">
                                                        utama
                                                    </span>
                                                )}
                                            </p>
                                            <p className="text-muted-foreground">
                                                {g.relation} · {g.phone}
                                            </p>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </Panel>
                    </div>
                </div>
            </div>
        </StaffLayout>
    );
}

function Fact({
    label,
    value,
    muted = false,
}: {
    label: string;
    value: string;
    muted?: boolean;
}) {
    return (
        <div className="min-w-0">
            <dt className="text-xs text-muted-foreground">{label}</dt>
            <dd
                className={cn(
                    'truncate text-sm font-semibold',
                    muted && 'font-normal text-muted-foreground',
                )}
            >
                {value}
            </dd>
        </div>
    );
}

function Panel({
    title,
    meta,
    children,
}: {
    title: string;
    meta?: string;
    children: React.ReactNode;
}) {
    return (
        <section className="overflow-hidden rounded-xl border border-border bg-card shadow-xs">
            <header className="flex items-center justify-between gap-2 border-b border-border bg-muted/60 px-4 py-3 sm:px-5">
                <h2 className="text-sm font-semibold">{title}</h2>
                {meta && (
                    <span className="text-xs text-muted-foreground">
                        {meta}
                    </span>
                )}
            </header>
            <div className="px-4 py-4 sm:px-5">{children}</div>
        </section>
    );
}

function Rows({
    items,
    empty,
}: {
    items: {
        key: string;
        left: React.ReactNode;
        middle: React.ReactNode;
        right: React.ReactNode;
    }[];
    empty: string;
}) {
    if (items.length === 0) {
        return <p className="text-sm text-muted-foreground">{empty}</p>;
    }

    return (
        <ul className="-my-2 divide-y divide-border">
            {items.map((item) => (
                <li
                    key={item.key}
                    className="grid grid-cols-[7rem_1fr_auto] items-center gap-3 py-2.5 text-sm"
                >
                    <span className="text-muted-foreground">{item.left}</span>
                    <span className="min-w-0">{item.middle}</span>
                    <span className="text-right">{item.right}</span>
                </li>
            ))}
        </ul>
    );
}

function Stat({ label, value }: { label: string; value: number }) {
    return (
        <div className="rounded-lg border border-border bg-background/60 px-3 py-2.5">
            <dt className="text-xs text-muted-foreground">{label}</dt>
            <dd className="text-2xl font-semibold tabular-nums">{value}</dd>
        </div>
    );
}

function Progress({
    label,
    achieved,
    target,
    pct,
}: {
    label: string;
    achieved: number;
    target: number;
    pct: number;
}) {
    return (
        <div>
            <div className="mb-1.5 flex justify-between text-sm">
                <span className="font-medium">{label}</span>
                <span className="text-muted-foreground tabular-nums">
                    {target > 0
                        ? `${achieved} dari ${target} · ${pct}%`
                        : 'Belum ada target'}
                </span>
            </div>
            <div
                className="h-2 overflow-hidden rounded-full bg-muted"
                aria-hidden
            >
                <div
                    className="h-full rounded-full bg-primary/75"
                    style={{ width: `${Math.min(pct, 100)}%` }}
                />
            </div>
        </div>
    );
}

function Line({ label, value }: { label: string; value: string | null }) {
    return (
        <div className="flex justify-between gap-3">
            <dt className="text-muted-foreground">{label}</dt>
            <dd className={cn('text-right', !value && 'text-muted-foreground')}>
                {value || '—'}
            </dd>
        </div>
    );
}
