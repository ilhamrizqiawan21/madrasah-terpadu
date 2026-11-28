import { Head, Link, router, useForm } from '@inertiajs/react';
import { Plus, Printer } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { useState } from 'react';
import LessonScheduleController from '@/actions/App/Http/Controllers/Admin/LessonScheduleController';
import { EmptyState } from '@/components/app/empty-state';
import { FormDialog } from '@/components/app/form-dialog';
import { PageHeader } from '@/components/app/page-header';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import StaffLayout from '@/layouts/staff-layout';

type Option = { value: string; label: string };
type Period = {
    id: number;
    name: string;
    starts_at: string;
    ends_at: string;
    is_break: boolean;
};
type Cell = {
    weekday: number;
    period_id: number;
    subject_id: number;
    subject: string;
    teacher_id: number;
    teacher: string;
    room_id: number | null;
    room: string | null;
};

type Props = {
    weekdays: { value: number; label: string }[];
    unitOptions: Option[];
    unitId: number | null;
    classroomOptions: Option[];
    classroomId: number | null;
    classroomName: string | null;
    periods: Period[];
    cells: Cell[];
    subjectOptions: Option[];
    teacherOptions: Option[];
    roomOptions: Option[];
};

const NONE = 'tanpa';
const dot = (t: string) => t.replace(':', '.');

export default function LessonSchedule(props: Props) {
    const {
        weekdays,
        unitOptions,
        unitId,
        classroomOptions,
        classroomId,
        classroomName,
        periods,
        cells,
        subjectOptions,
        teacherOptions,
        roomOptions,
    } = props;
    const [target, setTarget] = useState<{
        weekday: number;
        period: Period;
        existing: Cell | undefined;
    } | null>(null);
    const form = useForm({
        subject_id: '',
        teacher_id: '',
        room_id: NONE,
        parallel_ids: [] as string[],
    });

    const cellAt = (weekday: number, periodId: number) =>
        cells.find((c) => c.weekday === weekday && c.period_id === periodId);

    const go = (query: Record<string, string>) =>
        router.get(LessonScheduleController.schedule.url(), query, {
            preserveScroll: true,
            replace: true,
        });

    const open = (weekday: number, period: Period) => {
        const existing = cellAt(weekday, period.id);
        form.clearErrors();
        form.setData({
            subject_id: existing ? String(existing.subject_id) : '',
            teacher_id: existing ? String(existing.teacher_id) : '',
            room_id: existing?.room_id ? String(existing.room_id) : NONE,
            parallel_ids: [],
        });
        setTarget({ weekday, period, existing });
    };

    const submit = (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        if (!target || classroomId === null) {
            return;
        }
        form.transform((d) => ({
            ...d,
            room_id: d.room_id === NONE ? null : d.room_id,
            classroom_id: classroomId,
            weekday: target.weekday,
            lesson_period_id: target.period.id,
        }));
        form.post(LessonScheduleController.plot.url(), {
            preserveScroll: true,
            onSuccess: () => setTarget(null),
        });
    };

    const clearCell = () => {
        if (!target || classroomId === null) {
            return;
        }
        router.post(
            LessonScheduleController.clear.url(),
            {
                classroom_id: classroomId,
                weekday: target.weekday,
                lesson_period_id: target.period.id,
            },
            { preserveScroll: true, onSuccess: () => setTarget(null) },
        );
    };

    const selectField = (
        id: string,
        label: string,
        value: string,
        onChange: (v: string) => void,
        options: Option[],
        error?: string,
    ) => (
        <div className="grid gap-2">
            <Label htmlFor={id}>{label}</Label>
            <Select value={value || undefined} onValueChange={onChange}>
                <SelectTrigger id={id}>
                    <SelectValue placeholder="Pilih" />
                </SelectTrigger>
                <SelectContent>
                    {options.map((o) => (
                        <SelectItem key={o.value} value={o.value}>
                            {o.label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
            <InputError message={error} />
        </div>
    );

    return (
        <StaffLayout
            breadcrumbs={[
                { title: 'Akademik', href: '#' },
                {
                    title: 'Jadwal Pelajaran',
                    href: LessonScheduleController.schedule.url(),
                },
            ]}
        >
            <Head title="Jadwal Pelajaran" />

            <div className="@container space-y-4">
                <PageHeader
                    title="Jadwal Pelajaran"
                    description="Matriks mingguan per rombel tahun ajaran aktif: mapel, guru pengampu, dan ruangan."
                    actions={
                        <Button
                            type="button"
                            variant="soft"
                            onClick={() => window.print()}
                        >
                            <Printer className="size-4" strokeWidth={1.75} />
                            Cetak jadwal kelas
                        </Button>
                    }
                />

                <div className="flex flex-wrap gap-2 print:hidden">
                    <Select
                        value={unitId === null ? undefined : String(unitId)}
                        onValueChange={(v) => go({ unit: v })}
                    >
                        <SelectTrigger
                            className="w-full @lg:w-56"
                            aria-label="Pilih unit"
                        >
                            <SelectValue placeholder="Pilih unit" />
                        </SelectTrigger>
                        <SelectContent>
                            {unitOptions.map((o) => (
                                <SelectItem key={o.value} value={o.value}>
                                    {o.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <Select
                        value={
                            classroomId === null
                                ? undefined
                                : String(classroomId)
                        }
                        onValueChange={(v) =>
                            go({ unit: String(unitId ?? ''), classroom: v })
                        }
                    >
                        <SelectTrigger
                            className="w-full @lg:w-48"
                            aria-label="Pilih rombel"
                        >
                            <SelectValue placeholder="Pilih rombel" />
                        </SelectTrigger>
                        <SelectContent>
                            {classroomOptions.map((o) => (
                                <SelectItem key={o.value} value={o.value}>
                                    {o.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                {periods.length === 0 || classroomId === null ? (
                    <EmptyState
                        title={
                            periods.length === 0
                                ? 'Belum ada slot jam pelajaran'
                                : 'Belum ada rombel aktif'
                        }
                        description={
                            periods.length === 0
                                ? 'Tambahkan slot di menu Jam Pelajaran untuk unit ini.'
                                : 'Buat rombel tahun ajaran aktif di menu Kelas & Wali Kelas.'
                        }
                        action={
                            periods.length === 0 ? (
                                <Button asChild variant="outline">
                                    <Link
                                        href={LessonScheduleController.periods.url()}
                                    >
                                        Buka Jam Pelajaran
                                    </Link>
                                </Button>
                            ) : undefined
                        }
                    />
                ) : (
                    <div className="overflow-x-auto rounded-xl border border-border bg-card shadow-xs">
                        <table className="w-full min-w-[56rem] border-collapse text-sm">
                            <caption className="sr-only">
                                Jadwal mingguan {classroomName}
                            </caption>
                            <thead>
                                <tr className="border-b border-border bg-primary/[0.04] text-left text-xs text-muted-foreground">
                                    <th className="w-36 px-3 py-2 font-medium">
                                        Slot waktu
                                    </th>
                                    {weekdays.map((d) => (
                                        <th
                                            key={d.value}
                                            className="px-3 py-2 font-medium"
                                        >
                                            {d.label}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {periods.map((p) => (
                                    <tr
                                        key={p.id}
                                        className={
                                            p.is_break
                                                ? 'bg-muted/40'
                                                : undefined
                                        }
                                    >
                                        <th
                                            scope="row"
                                            className="px-3 py-2 text-left align-top font-medium"
                                        >
                                            <div>{p.name}</div>
                                            <div className="text-xs font-normal text-muted-foreground tabular-nums">
                                                {dot(p.starts_at)}–
                                                {dot(p.ends_at)}
                                            </div>
                                        </th>
                                        {p.is_break ? (
                                            <td
                                                colSpan={weekdays.length}
                                                className="px-3 py-2 text-center text-xs text-muted-foreground"
                                            >
                                                Istirahat
                                            </td>
                                        ) : (
                                            weekdays.map((d) => {
                                                const cell = cellAt(
                                                    d.value,
                                                    p.id,
                                                );

                                                return (
                                                    <td
                                                        key={d.value}
                                                        className="p-1.5 align-top"
                                                    >
                                                        <button
                                                            type="button"
                                                            onClick={() =>
                                                                open(d.value, p)
                                                            }
                                                            className={
                                                                cell
                                                                    ? 'w-full rounded-md border border-primary/20 bg-primary/5 p-2 text-left transition-colors hover:bg-primary/10 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none'
                                                                    : 'flex w-full items-center justify-center gap-1 rounded-md border border-dashed border-border p-2 text-xs text-muted-foreground transition-colors hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none print:hidden'
                                                            }
                                                            aria-label={`${d.label} ${p.name}${cell ? `: ${cell.subject}` : ', kosong'}`}
                                                        >
                                                            {cell ? (
                                                                <>
                                                                    <span className="block font-medium">
                                                                        {
                                                                            cell.subject
                                                                        }
                                                                    </span>
                                                                    <span className="block text-xs text-muted-foreground">
                                                                        {
                                                                            cell.teacher
                                                                        }
                                                                    </span>
                                                                    {cell.room && (
                                                                        <span className="block text-xs text-muted-foreground">
                                                                            {
                                                                                cell.room
                                                                            }
                                                                        </span>
                                                                    )}
                                                                </>
                                                            ) : (
                                                                <>
                                                                    <Plus
                                                                        className="size-3.5"
                                                                        strokeWidth={
                                                                            1.75
                                                                        }
                                                                    />
                                                                    Plot
                                                                </>
                                                            )}
                                                        </button>
                                                    </td>
                                                );
                                            })
                                        )}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>

            <FormDialog
                open={target !== null}
                onOpenChange={(o) => !o && setTarget(null)}
                title={
                    target
                        ? `${weekdays.find((d) => d.value === target.weekday)?.label ?? ''} · ${target.period.name} · ${classroomName ?? ''}`
                        : ''
                }
                onSubmit={submit}
                processing={form.processing}
                submitLabel="Simpan jadwal"
            >
                {selectField(
                    'plot-subject',
                    'Mata pelajaran',
                    form.data.subject_id,
                    (v) => form.setData('subject_id', v),
                    subjectOptions,
                    form.errors.subject_id,
                )}
                {selectField(
                    'plot-teacher',
                    'Guru pengampu',
                    form.data.teacher_id,
                    (v) => form.setData('teacher_id', v),
                    teacherOptions,
                    form.errors.teacher_id,
                )}
                {selectField(
                    'plot-room',
                    'Ruangan / lab',
                    form.data.room_id,
                    (v) => form.setData('room_id', v),
                    [{ value: NONE, label: 'Tanpa ruangan' }, ...roomOptions],
                    form.errors.room_id,
                )}
                {classroomOptions.length > 1 && (
                    <fieldset className="grid gap-2">
                        <legend className="mb-1 text-sm font-medium">
                            Gabungkan rombel lain (kelas paralel)
                        </legend>
                        <div className="grid gap-2 sm:grid-cols-3">
                            {classroomOptions
                                .filter((o) => o.value !== String(classroomId))
                                .map((o) => (
                                    <label
                                        key={o.value}
                                        className="flex items-center gap-2 text-sm"
                                    >
                                        <Checkbox
                                            checked={form.data.parallel_ids.includes(
                                                o.value,
                                            )}
                                            onCheckedChange={(c) =>
                                                form.setData(
                                                    'parallel_ids',
                                                    c === true
                                                        ? [
                                                              ...form.data
                                                                  .parallel_ids,
                                                              o.value,
                                                          ]
                                                        : form.data.parallel_ids.filter(
                                                              (x) =>
                                                                  x !== o.value,
                                                          ),
                                                )
                                            }
                                        />
                                        {o.label}
                                    </label>
                                ))}
                        </div>
                    </fieldset>
                )}
                {target?.existing && (
                    <Button
                        type="button"
                        variant="outline"
                        onClick={clearCell}
                        className="justify-self-start text-destructive"
                    >
                        Kosongkan sel ini
                    </Button>
                )}
            </FormDialog>
        </StaffLayout>
    );
}

LessonSchedule.layout = (page: ReactNode) => page;
