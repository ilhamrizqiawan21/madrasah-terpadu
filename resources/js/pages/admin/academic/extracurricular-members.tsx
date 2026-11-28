import { Head, router, useForm } from '@inertiajs/react';
import { CalendarCheck, UserPlus } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { useState } from 'react';
import ExtracurricularController from '@/actions/App/Http/Controllers/Admin/ExtracurricularController';
import { EmptyState } from '@/components/app/empty-state';
import { FormDialog } from '@/components/app/form-dialog';
import { PageHeader } from '@/components/app/page-header';
import { RowActions } from '@/components/app/row-actions';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
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
type Member = {
    id: number;
    student_name: string;
    nis: string;
    classroom: string;
    sessions: number;
    present: number;
    grade: string | null;
};

type Props = {
    ekskulOptions: Option[];
    ekskulId: number | null;
    classroomOptions: Option[];
    classroomId: number | null;
    candidateClassroomId: number | null;
    members: Member[];
    candidates: Option[];
    grades: string[];
    statuses: Option[];
};

const ALL = 'semua';
const NO_GRADE = 'belum';

const today = () => {
    const d = new Date();
    const pad = (n: number) => String(n).padStart(2, '0');

    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
};

export default function ExtracurricularMembers(props: Props) {
    const {
        ekskulOptions,
        ekskulId,
        classroomOptions,
        classroomId,
        candidateClassroomId,
        members,
        candidates,
        grades,
        statuses,
    } = props;
    const [enrollOpen, setEnrollOpen] = useState(false);
    const [attendanceOpen, setAttendanceOpen] = useState(false);
    const [removingId, setRemovingId] = useState<number | null>(null);
    const enrollForm = useForm({ student_ids: [] as string[] });
    const attendanceForm = useForm({
        met_on: today(),
        statuses: {} as Record<string, string>,
    });

    const query = (changes: Record<string, string | number | null>) =>
        Object.fromEntries(
            Object.entries({
                ekskul: ekskulId,
                classroom: classroomId,
                ...changes,
            }).filter(([, v]) => v !== null && String(v) !== ''),
        );
    const go = (
        changes: Record<string, string | number | null>,
        keepDialog = false,
    ) =>
        router.get(ExtracurricularController.members.url(), query(changes), {
            preserveScroll: true,
            preserveState: keepDialog,
            replace: true,
        });

    const openAttendance = () => {
        attendanceForm.clearErrors();
        attendanceForm.setData({
            met_on: today(),
            statuses: Object.fromEntries(
                members.map((m) => [String(m.id), 'present']),
            ),
        });
        setAttendanceOpen(true);
    };

    const submitEnroll = (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        if (ekskulId === null) {
            return;
        }
        enrollForm.post(ExtracurricularController.enroll.url(ekskulId), {
            preserveScroll: true,
            onSuccess: () => {
                enrollForm.reset();
                setEnrollOpen(false);
            },
        });
    };

    const submitAttendance = (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        if (ekskulId === null) {
            return;
        }
        attendanceForm.post(
            ExtracurricularController.attendance.url(ekskulId),
            {
                preserveScroll: true,
                onSuccess: () => setAttendanceOpen(false),
            },
        );
    };

    const setGrade = (member: Member, value: string) =>
        router.patch(
            ExtracurricularController.grade.url(member.id),
            { grade: value === NO_GRADE ? null : value },
            { preserveScroll: true },
        );

    return (
        <StaffLayout
            breadcrumbs={[
                { title: 'Akademik', href: '#' },
                {
                    title: 'Anggota & Nilai Ekskul',
                    href: ExtracurricularController.members.url(),
                },
            ]}
        >
            <Head title="Anggota & Nilai Ekskul" />

            <div className="@container space-y-4">
                <PageHeader
                    title="Anggota & Nilai Ekskul"
                    description="Anggota per kegiatan di tahun ajaran aktif, presensi pertemuan, dan predikat untuk rapor."
                    actions={
                        ekskulId !== null && (
                            <>
                                <Button
                                    type="button"
                                    variant="soft"
                                    onClick={openAttendance}
                                    disabled={members.length === 0}
                                >
                                    <CalendarCheck
                                        className="size-4"
                                        strokeWidth={1.75}
                                    />
                                    Input presensi
                                </Button>
                                <Button
                                    type="button"
                                    onClick={() => setEnrollOpen(true)}
                                >
                                    <UserPlus
                                        className="size-4"
                                        strokeWidth={1.75}
                                    />
                                    Daftarkan siswa
                                </Button>
                            </>
                        )
                    }
                />

                {ekskulId === null ? (
                    <EmptyState
                        title="Belum ada ekstrakurikuler"
                        description="Tambahkan kegiatan di menu Ekstrakurikuler terlebih dahulu."
                    />
                ) : (
                    <>
                        <div className="flex flex-wrap gap-2">
                            <Select
                                value={String(ekskulId)}
                                onValueChange={(v) =>
                                    go({ ekskul: v, classroom: null })
                                }
                            >
                                <SelectTrigger
                                    className="w-full @lg:w-64"
                                    aria-label="Pilih ekskul"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {ekskulOptions.map((o) => (
                                        <SelectItem
                                            key={o.value}
                                            value={o.value}
                                        >
                                            {o.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <Select
                                value={
                                    classroomId === null
                                        ? ALL
                                        : String(classroomId)
                                }
                                onValueChange={(v) =>
                                    go({ classroom: v === ALL ? null : v })
                                }
                            >
                                <SelectTrigger
                                    className="w-full @lg:w-48"
                                    aria-label="Saring rombel"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={ALL}>
                                        Semua rombel
                                    </SelectItem>
                                    {classroomOptions.map((o) => (
                                        <SelectItem
                                            key={o.value}
                                            value={o.value}
                                        >
                                            {o.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        {members.length === 0 ? (
                            <EmptyState
                                title="Belum ada anggota"
                                description="Daftarkan siswa ke kegiatan ini untuk mulai mencatat presensi dan nilai."
                            />
                        ) : (
                            <div className="overflow-x-auto rounded-xl border border-border bg-card shadow-xs">
                                <table className="w-full text-sm">
                                    <thead>
                                        <tr className="border-b border-border bg-primary/[0.04] text-left text-xs text-muted-foreground">
                                            <th className="px-3 py-2 font-medium">
                                                Siswa
                                            </th>
                                            <th className="px-3 py-2 font-medium">
                                                Rombel
                                            </th>
                                            <th className="hidden px-3 py-2 text-center font-medium @3xl:table-cell">
                                                Kehadiran
                                            </th>
                                            <th className="px-3 py-2 font-medium">
                                                Predikat rapor
                                            </th>
                                            <th className="px-3 py-2 text-center font-medium">
                                                Aksi
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-border">
                                        {members.map((m) => (
                                            <tr key={m.id}>
                                                <td className="px-3 py-2">
                                                    <div className="font-medium">
                                                        {m.student_name}
                                                    </div>
                                                    <div className="text-xs text-muted-foreground tabular-nums">
                                                        {m.nis}
                                                    </div>
                                                </td>
                                                <td className="px-3 py-2">
                                                    {m.classroom}
                                                </td>
                                                <td className="hidden px-3 py-2 text-center tabular-nums @3xl:table-cell">
                                                    {m.sessions === 0
                                                        ? '—'
                                                        : `${m.present}/${m.sessions} hadir`}
                                                </td>
                                                <td className="px-3 py-2">
                                                    <Select
                                                        value={
                                                            m.grade ?? NO_GRADE
                                                        }
                                                        onValueChange={(v) =>
                                                            setGrade(m, v)
                                                        }
                                                    >
                                                        <SelectTrigger
                                                            className="w-32"
                                                            aria-label={`Predikat ${m.student_name}`}
                                                        >
                                                            <SelectValue />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            <SelectItem
                                                                value={NO_GRADE}
                                                            >
                                                                Belum dinilai
                                                            </SelectItem>
                                                            {grades.map((g) => (
                                                                <SelectItem
                                                                    key={g}
                                                                    value={g}
                                                                >
                                                                    {g}
                                                                </SelectItem>
                                                            ))}
                                                        </SelectContent>
                                                    </Select>
                                                </td>
                                                <td className="px-3 py-2 text-center">
                                                    <RowActions
                                                        onDelete={() =>
                                                            router.delete(
                                                                ExtracurricularController.removeMember.url(
                                                                    m.id,
                                                                ),
                                                                {
                                                                    preserveScroll: true,
                                                                    onStart:
                                                                        () =>
                                                                            setRemovingId(
                                                                                m.id,
                                                                            ),
                                                                    onFinish:
                                                                        () =>
                                                                            setRemovingId(
                                                                                null,
                                                                            ),
                                                                },
                                                            )
                                                        }
                                                        deleting={
                                                            removingId === m.id
                                                        }
                                                        itemName="anggota"
                                                        deleteTitle="Keluarkan dari ekskul?"
                                                        deleteDescription={`${m.student_name} dikeluarkan beserta riwayat presensinya di kegiatan ini.`}
                                                    />
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </>
                )}
            </div>

            <FormDialog
                open={enrollOpen}
                onOpenChange={setEnrollOpen}
                title="Daftarkan siswa"
                onSubmit={submitEnroll}
                processing={enrollForm.processing}
                submitLabel={`Daftarkan ${enrollForm.data.student_ids.length || ''} siswa`.replace(
                    '  ',
                    ' ',
                )}
            >
                <div className="grid gap-2">
                    <Label htmlFor="enroll-classroom">Ambil dari rombel</Label>
                    <Select
                        value={
                            candidateClassroomId === null
                                ? undefined
                                : String(candidateClassroomId)
                        }
                        onValueChange={(v) => {
                            enrollForm.setData('student_ids', []);
                            go({ from: v }, true);
                        }}
                    >
                        <SelectTrigger id="enroll-classroom">
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
                {candidates.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        Semua siswa aktif di rombel ini sudah terdaftar.
                    </p>
                ) : (
                    <fieldset className="grid gap-2">
                        <div className="flex items-center justify-between">
                            <legend className="text-sm font-medium">
                                Pilih siswa
                            </legend>
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                onClick={() =>
                                    enrollForm.setData(
                                        'student_ids',
                                        enrollForm.data.student_ids.length ===
                                            candidates.length
                                            ? []
                                            : candidates.map((c) => c.value),
                                    )
                                }
                            >
                                {enrollForm.data.student_ids.length ===
                                candidates.length
                                    ? 'Batal pilih semua'
                                    : 'Pilih semua'}
                            </Button>
                        </div>
                        <div className="grid max-h-72 gap-2 overflow-y-auto rounded-md border border-border p-3 sm:grid-cols-2">
                            {candidates.map((c) => (
                                <label
                                    key={c.value}
                                    className="flex items-center gap-2 text-sm"
                                >
                                    <Checkbox
                                        checked={enrollForm.data.student_ids.includes(
                                            c.value,
                                        )}
                                        onCheckedChange={(on) =>
                                            enrollForm.setData(
                                                'student_ids',
                                                on === true
                                                    ? [
                                                          ...enrollForm.data
                                                              .student_ids,
                                                          c.value,
                                                      ]
                                                    : enrollForm.data.student_ids.filter(
                                                          (x) => x !== c.value,
                                                      ),
                                            )
                                        }
                                    />
                                    {c.label}
                                </label>
                            ))}
                        </div>
                    </fieldset>
                )}
                <InputError message={enrollForm.errors.student_ids} />
            </FormDialog>

            <FormDialog
                open={attendanceOpen}
                onOpenChange={setAttendanceOpen}
                title="Input presensi pertemuan"
                onSubmit={submitAttendance}
                processing={attendanceForm.processing}
                submitLabel="Simpan presensi"
            >
                <div className="flex flex-wrap items-end justify-between gap-3">
                    <div className="grid gap-2">
                        <Label htmlFor="met-on">Tanggal pertemuan</Label>
                        <Input
                            id="met-on"
                            type="date"
                            value={attendanceForm.data.met_on}
                            max={today()}
                            onChange={(e) =>
                                attendanceForm.setData('met_on', e.target.value)
                            }
                        />
                        <InputError message={attendanceForm.errors.met_on} />
                    </div>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() =>
                            attendanceForm.setData(
                                'statuses',
                                Object.fromEntries(
                                    members.map((m) => [
                                        String(m.id),
                                        'present',
                                    ]),
                                ),
                            )
                        }
                    >
                        Set semua hadir
                    </Button>
                </div>
                <div className="max-h-80 divide-y divide-border overflow-y-auto rounded-md border border-border">
                    {members.map((m) => (
                        <div
                            key={m.id}
                            className="flex items-center justify-between gap-3 px-3 py-2 text-sm"
                        >
                            <span>
                                {m.student_name}{' '}
                                <span className="text-muted-foreground">
                                    · {m.classroom}
                                </span>
                            </span>
                            <Select
                                value={
                                    attendanceForm.data.statuses[
                                        String(m.id)
                                    ] ?? 'present'
                                }
                                onValueChange={(v) =>
                                    attendanceForm.setData('statuses', {
                                        ...attendanceForm.data.statuses,
                                        [String(m.id)]: v,
                                    })
                                }
                            >
                                <SelectTrigger
                                    className="w-28"
                                    aria-label={`Status ${m.student_name}`}
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {statuses.map((s) => (
                                        <SelectItem
                                            key={s.value}
                                            value={s.value}
                                        >
                                            {s.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    ))}
                </div>
            </FormDialog>
        </StaffLayout>
    );
}

ExtracurricularMembers.layout = (page: ReactNode) => page;
