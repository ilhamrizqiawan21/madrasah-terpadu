import { Head, Link, router } from '@inertiajs/react';
import { Calendar, CalendarPlus, Clock, List } from 'lucide-react';
import { useState } from 'react';
import AttendanceController from '@/actions/App/Http/Controllers/Guardian/AttendanceController';
import LeaveRequestController from '@/actions/App/Http/Controllers/Guardian/LeaveRequestController';
import { EmptyState } from '@/components/app/empty-state';
import { StatusBadge } from '@/components/app/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import GuardianLayout from '@/layouts/guardian-layout';
import { formatDate } from '@/lib/format';

type CalendarDay = {
    date: string;
    day: number;
    weekday: number;
    status: string | null;
    status_label: string;
    is_holiday: boolean;
    checked_in_at: string | null;
    checked_out_at: string | null;
};

type AttendanceRecord = {
    date: string;
    status: string;
    status_label: string;
    checked_in_at: string | null;
    checked_out_at: string | null;
};

type Props = {
    student: {
        id: number;
        name: string;
        unit: string;
    };
    today: {
        date: string;
        is_holiday: boolean;
        status: string | null;
        status_label: string;
        checked_in_at: string | null;
        checked_out_at: string | null;
    };
    month: string;
    monthLabel: string;
    summary: {
        present: number;
        late: number;
        excused: number;
        sick: number;
        absent: number;
    };
    calendarDays: CalendarDay[];
    records: AttendanceRecord[];
};

function badgeForStatus(
    status: string | null,
): 'success' | 'warning' | 'destructive' | 'muted' {
    switch (status) {
        case 'present':
            return 'success';
        case 'late':
        case 'excused':
        case 'sick':
            return 'warning';
        case 'absent':
            return 'destructive';
        default:
            return 'muted';
    }
}

function cellTone(day: CalendarDay): string {
    switch (day.status) {
        case 'present':
            return 'border-emerald-300 bg-emerald-50 text-emerald-900';
        case 'late':
        case 'excused':
        case 'sick':
            return 'border-amber-300 bg-amber-50 text-amber-900';
        case 'absent':
            return 'border-destructive/40 bg-destructive/10 text-destructive';
        default:
            return day.is_holiday
                ? 'border-border bg-muted/60 text-muted-foreground'
                : 'border-border bg-card text-foreground';
    }
}

const WEEKDAY_HEADERS = ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'];

export default function GuardianAttendance({
    student,
    today,
    month,
    monthLabel,
    summary,
    calendarDays,
    records,
}: Props) {
    const [viewMode, setViewMode] = useState<'calendar' | 'list'>('calendar');

    const leadingBlanks =
        calendarDays.length > 0 ? calendarDays[0].weekday - 1 : 0;

    return (
        <GuardianLayout title="Presensi Anak">
            <Head title={`Presensi ${student.name}`} />

            <div className="space-y-4">
                <Card className="shadow-xs">
                    <CardHeader className="pb-2">
                        <div className="flex items-center justify-between gap-2">
                            <CardTitle className="text-base">
                                Hari ini · {formatDate(today.date)}
                            </CardTitle>
                            <StatusBadge variant={badgeForStatus(today.status)}>
                                {today.is_holiday && !today.status
                                    ? 'Hari libur'
                                    : today.status_label}
                            </StatusBadge>
                        </div>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        <div className="grid grid-cols-2 gap-3 rounded-lg border border-border bg-muted/30 p-3 text-sm">
                            <div>
                                <p className="text-xs text-muted-foreground">
                                    Jam masuk
                                </p>
                                <p className="mt-0.5 flex items-center gap-1 font-semibold">
                                    <Clock
                                        className="size-3.5 text-muted-foreground"
                                        aria-hidden="true"
                                    />
                                    {today.checked_in_at
                                        ? `${today.checked_in_at} WITA`
                                        : '—'}
                                </p>
                            </div>
                            <div>
                                <p className="text-xs text-muted-foreground">
                                    Jam pulang
                                </p>
                                <p className="mt-0.5 flex items-center gap-1 font-semibold">
                                    <Clock
                                        className="size-3.5 text-muted-foreground"
                                        aria-hidden="true"
                                    />
                                    {today.checked_out_at
                                        ? `${today.checked_out_at} WITA`
                                        : '—'}
                                </p>
                            </div>
                        </div>

                        <Button asChild className="min-h-11 w-full gap-2">
                            <Link
                                href={LeaveRequestController.create.url(
                                    student.id,
                                )}
                            >
                                <CalendarPlus className="size-4" />
                                <span>Ajukan izin / sakit</span>
                            </Link>
                        </Button>
                    </CardContent>
                </Card>

                <Card className="shadow-xs">
                    <CardHeader className="pb-3">
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <CardTitle className="text-base">
                                    Riwayat {monthLabel}
                                </CardTitle>
                            </div>
                            <div className="flex items-center gap-2">
                                <div className="grid gap-1">
                                    <Label
                                        htmlFor="guardian-attendance-month"
                                        className="sr-only"
                                    >
                                        Pilih bulan
                                    </Label>
                                    <Input
                                        id="guardian-attendance-month"
                                        type="month"
                                        value={month}
                                        className="min-h-11 w-40"
                                        onChange={(e) =>
                                            router.get(
                                                AttendanceController.index.url(
                                                    student.id,
                                                ),
                                                { month: e.target.value },
                                                {
                                                    preserveState: true,
                                                    preserveScroll: true,
                                                },
                                            )
                                        }
                                    />
                                </div>
                            </div>
                        </div>

                        <div className="mt-3 grid grid-cols-5 gap-1.5 text-center text-xs">
                            <div className="rounded-md border border-border p-2">
                                <p className="text-muted-foreground">Hadir</p>
                                <p className="font-bold tabular-nums">
                                    {summary.present}
                                </p>
                            </div>
                            <div className="rounded-md border border-border p-2">
                                <p className="text-muted-foreground">Telat</p>
                                <p className="font-bold tabular-nums">
                                    {summary.late}
                                </p>
                            </div>
                            <div className="rounded-md border border-border p-2">
                                <p className="text-muted-foreground">Izin</p>
                                <p className="font-bold tabular-nums">
                                    {summary.excused}
                                </p>
                            </div>
                            <div className="rounded-md border border-border p-2">
                                <p className="text-muted-foreground">Sakit</p>
                                <p className="font-bold tabular-nums">
                                    {summary.sick}
                                </p>
                            </div>
                            <div className="rounded-md border border-border p-2">
                                <p className="text-muted-foreground">Alpa</p>
                                <p className="font-bold tabular-nums">
                                    {summary.absent}
                                </p>
                            </div>
                        </div>

                        <div className="mt-3 grid grid-cols-2 gap-2">
                            <Button
                                type="button"
                                variant={
                                    viewMode === 'calendar'
                                        ? 'default'
                                        : 'outline'
                                }
                                className="min-h-11 gap-1.5"
                                onClick={() => setViewMode('calendar')}
                            >
                                <Calendar className="size-4" />
                                <span>Kalender</span>
                            </Button>
                            <Button
                                type="button"
                                variant={
                                    viewMode === 'list' ? 'default' : 'outline'
                                }
                                className="min-h-11 gap-1.5"
                                onClick={() => setViewMode('list')}
                            >
                                <List className="size-4" />
                                <span>Daftar</span>
                            </Button>
                        </div>
                    </CardHeader>

                    <CardContent>
                        {viewMode === 'calendar' ? (
                            <div className="grid grid-cols-7 gap-1.5 text-center text-xs">
                                {WEEKDAY_HEADERS.map((label) => (
                                    <div
                                        key={label}
                                        className="py-1 font-medium text-muted-foreground"
                                    >
                                        {label}
                                    </div>
                                ))}

                                {Array.from({ length: leadingBlanks }).map(
                                    (_, idx) => (
                                        <div key={`blank-${idx}`} />
                                    ),
                                )}

                                {calendarDays.map((day) => (
                                    <div
                                        key={day.date}
                                        aria-label={`${formatDate(day.date)}: ${day.status_label}`}
                                        className={`flex min-h-11 flex-col items-center justify-center rounded-md border p-1 ${cellTone(day)}`}
                                    >
                                        <span className="font-semibold tabular-nums">
                                            {day.day}
                                        </span>
                                        {day.status && (
                                            <span className="truncate text-[10px] leading-tight">
                                                {day.status_label}
                                            </span>
                                        )}
                                    </div>
                                ))}
                            </div>
                        ) : records.length === 0 ? (
                            <EmptyState
                                icon={Clock}
                                title="Belum ada catatan presensi bulan ini"
                                description="Catatan hadir, terlambat, izin, sakit, dan alpa akan muncul di sini."
                            />
                        ) : (
                            <ul className="divide-y divide-border">
                                {records.map((item) => (
                                    <li
                                        key={item.date}
                                        className="flex items-center justify-between gap-2 py-3 first:pt-0 last:pb-0"
                                    >
                                        <div>
                                            <p className="font-medium">
                                                {formatDate(item.date)}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                Masuk:{' '}
                                                {item.checked_in_at
                                                    ? `${item.checked_in_at} WITA`
                                                    : '—'}{' '}
                                                · Pulang:{' '}
                                                {item.checked_out_at
                                                    ? `${item.checked_out_at} WITA`
                                                    : '—'}
                                            </p>
                                        </div>
                                        <StatusBadge
                                            variant={badgeForStatus(
                                                item.status,
                                            )}
                                        >
                                            {item.status_label}
                                        </StatusBadge>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>
            </div>
        </GuardianLayout>
    );
}
