import { DateInput } from '@/components/app/date-input';
import { Head, router, useForm } from '@inertiajs/react';
import { CheckSquare, ClockAlert } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { useState } from 'react';
import BehaviorRecordController from '@/actions/App/Http/Controllers/Admin/BehaviorRecordController';
import LateBehaviorController from '@/actions/App/Http/Controllers/Admin/LateBehaviorController';
import { EmptyState } from '@/components/app/empty-state';
import { PageHeader } from '@/components/app/page-header';
import { StatusBadge } from '@/components/app/status-badge';
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

type LateStudentRow = {
    student_id: number;
    nis: string;
    name: string;
    unit_code: string;
    classroom_name: string;
    checked_in_at: string;
    already_recorded: boolean;
};

type RuleOption = {
    id: number;
    code: string;
    name: string;
    points: number;
};

type UnitOption = { id: number; code: string; name: string };

type Props = {
    lateStudents: LateStudentRow[];
    violationRules: RuleOption[];
    units: UnitOption[];
    filters: { date: string; unit_id: string };
};

const ALL_UNITS = 'semua';

export default function LateBehaviorIndex({
    lateStudents,
    violationRules,
    units,
    filters,
}: Props) {
    const [selectedIds, setSelectedIds] = useState<number[]>(() =>
        lateStudents
            .filter((s) => !s.already_recorded)
            .map((s) => s.student_id),
    );

    const form = useForm<{
        point_rule_id: string;
        note: string;
    }>({
        point_rule_id: violationRules[0] ? String(violationRules[0].id) : '',
        note: 'Terlambat hadir di gerbang',
    });

    function visitFilters(next: Partial<{ date: string; unit_id: string }>) {
        const merged = { ...filters, ...next };
        router.get(
            LateBehaviorController.index.url({
                query: {
                    date: merged.date || undefined,
                    unit_id: merged.unit_id || undefined,
                },
            }),
            {},
            { preserveState: false, replace: true },
        );
    }

    function toggleStudent(studentId: number, checked: boolean) {
        setSelectedIds((prev) =>
            checked
                ? Array.from(new Set([...prev, studentId]))
                : prev.filter((id) => id !== studentId),
        );
    }

    function submit(e: FormEvent<HTMLFormElement>) {
        e.preventDefault();
        if (selectedIds.length === 0) {
            return;
        }

        router.post(
            BehaviorRecordController.store.url(),
            {
                student_ids: selectedIds,
                point_rule_id: Number(form.data.point_rule_id),
                occurred_on: filters.date,
                note: form.data.note || null,
            },
            {
                preserveScroll: true,
                onSuccess: () => setSelectedIds([]),
                onError: (errors) =>
                    form.setError(
                        errors as Record<'point_rule_id' | 'note', string>,
                    ),
            },
        );
    }

    return (
        <>
            <Head title="Catat pelanggaran keterlambatan" />
            <div className="space-y-6">
                <PageHeader
                    title="Siswa terlambat hari ini"
                    description="Pilih siswa dari rekap keterlambatan presensi untuk dicatat pelanggarannya sekaligus."
                />

                <div className="flex flex-wrap items-center gap-3">
                    <DateInput
                        value={filters.date}
                        onChange={(e) => visitFilters({ date: e.target.value })}
                        className="w-[170px]"
                        aria-label="Tanggal presensi"
                    />

                    <Select
                        value={filters.unit_id || ALL_UNITS}
                        onValueChange={(v) =>
                            visitFilters({ unit_id: v === ALL_UNITS ? '' : v })
                        }
                    >
                        <SelectTrigger
                            className="w-[180px]"
                            aria-label="Filter unit"
                        >
                            <SelectValue placeholder="Semua unit" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL_UNITS}>
                                Semua unit
                            </SelectItem>
                            {units.map((u) => (
                                <SelectItem key={u.id} value={String(u.id)}>
                                    {u.code} — {u.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                {lateStudents.length === 0 ? (
                    <EmptyState
                        icon={ClockAlert}
                        title="Tidak ada siswa terlambat"
                        description="Tidak ditemukan catatan presensi terlambat pada tanggal dan unit terpilih."
                    />
                ) : (
                    <form onSubmit={submit} className="space-y-4">
                        <div className="grid grid-cols-1 gap-4 rounded-lg border border-border bg-card p-4 shadow-xs sm:grid-cols-3">
                            <div className="space-y-2">
                                <Label htmlFor="late-rule">
                                    Aturan pelanggaran
                                </Label>
                                <Select
                                    value={form.data.point_rule_id}
                                    onValueChange={(v) =>
                                        form.setData('point_rule_id', v)
                                    }
                                >
                                    <SelectTrigger id="late-rule">
                                        <SelectValue placeholder="Pilih aturan" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {violationRules.map((r) => (
                                            <SelectItem
                                                key={r.id}
                                                value={String(r.id)}
                                            >
                                                {r.code} · {r.name} ({r.points}{' '}
                                                poin)
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError
                                    message={form.errors.point_rule_id}
                                />
                            </div>

                            <div className="space-y-2 sm:col-span-2">
                                <Label htmlFor="late-note">Catatan</Label>
                                <div className="flex flex-wrap gap-2">
                                    <Input
                                        id="late-note"
                                        className="flex-1"
                                        value={form.data.note}
                                        onChange={(e) =>
                                            form.setData('note', e.target.value)
                                        }
                                        placeholder="Keterangan tambahan..."
                                    />
                                    <Button
                                        type="submit"
                                        disabled={
                                            selectedIds.length === 0 ||
                                            !form.data.point_rule_id ||
                                            form.processing
                                        }
                                    >
                                        Catat ({selectedIds.length} siswa)
                                    </Button>
                                </div>
                                <InputError message={form.errors.note} />
                            </div>
                        </div>

                        <div className="rounded-lg border border-border bg-card shadow-xs">
                            <div className="flex items-center justify-between border-b border-border px-4 py-3">
                                <span className="text-sm font-medium text-foreground">
                                    Daftar terlambat ({lateStudents.length}{' '}
                                    siswa)
                                </span>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={() =>
                                        setSelectedIds(
                                            lateStudents
                                                .filter(
                                                    (s) => !s.already_recorded,
                                                )
                                                .map((s) => s.student_id),
                                        )
                                    }
                                >
                                    <CheckSquare className="mr-1.5 h-3.5 w-3.5" />
                                    Pilih yang belum dicatat
                                </Button>
                            </div>

                            <div className="divide-y divide-border">
                                {lateStudents.map((s) => {
                                    const checked = selectedIds.includes(
                                        s.student_id,
                                    );

                                    return (
                                        <label
                                            key={s.student_id}
                                            className="flex cursor-pointer items-center gap-3 px-4 py-3 text-sm hover:bg-muted/40"
                                        >
                                            <Checkbox
                                                checked={checked}
                                                onCheckedChange={(v) =>
                                                    toggleStudent(
                                                        s.student_id,
                                                        v === true,
                                                    )
                                                }
                                            />
                                            <div className="min-w-0 flex-1">
                                                <div className="font-medium text-foreground">
                                                    {s.name}
                                                </div>
                                                <div className="text-xs text-muted-foreground">
                                                    {s.unit_code} ·{' '}
                                                    {s.classroom_name} · NIS{' '}
                                                    {s.nis}
                                                </div>
                                            </div>
                                            <span className="text-xs text-muted-foreground tabular-nums">
                                                Masuk {s.checked_in_at} WITA
                                            </span>
                                            {s.already_recorded ? (
                                                <StatusBadge variant="muted">
                                                    Sudah dicatat
                                                </StatusBadge>
                                            ) : (
                                                <StatusBadge variant="warning">
                                                    Terlambat
                                                </StatusBadge>
                                            )}
                                        </label>
                                    );
                                })}
                            </div>
                        </div>
                    </form>
                )}
            </div>
        </>
    );
}

LateBehaviorIndex.layout = (page: ReactNode) => (
    <StaffLayout
        breadcrumbs={[
            { title: 'Kesiswaan & Asrama', href: '#' },
            {
                title: 'Catatan perilaku',
                href: BehaviorRecordController.index.url(),
            },
            {
                title: 'Keterlambatan',
                href: LateBehaviorController.index.url(),
            },
        ]}
    >
        {page}
    </StaffLayout>
);
