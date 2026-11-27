import { Head, router, useForm } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { useEffect, useState } from 'react';
import MemorizationController from '@/actions/App/Http/Controllers/Admin/MemorizationController';
import { DateText } from '@/components/app/date-text';
import { EmptyState } from '@/components/app/empty-state';
import {
    MistakeCounterGroup,
    type MistakeCounterItem,
} from '@/components/app/mistake-counter';
import { PageHeader } from '@/components/app/page-header';
import { StatusBadge } from '@/components/app/status-badge';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import StaffLayout from '@/layouts/staff-layout';

type StudentOption = {
    id: number;
    nis: string;
    name: string;
    classroom_name: string;
};
type Criterion = {
    id: number;
    name: string;
    mode: 'direct' | 'deduction';
    weight: number;
    max_score: number;
    deduction_per_error: number;
};
type RubricVersionOption = {
    id: number;
    label: string;
    pass_threshold: number;
    criteria: Criterion[];
};
type SurahOption = { number: number; name_latin: string; ayah_count: number };
type MaterialOption = { id: number; kind: string; title: string };
type RecentRecord = {
    id: number;
    student_name: string;
    kind: string;
    kind_label: string;
    type: string | null;
    summary: string;
    final_score: number;
    predicate: string;
    passed: boolean;
    recorded_on: string;
};

type Props = {
    students: StudentOption[];
    rubricVersions: RubricVersionOption[];
    surahs: SurahOption[];
    materials: MaterialOption[];
    recentRecords: RecentRecord[];
    today: string;
    canRecord: boolean;
};

export default function MemorizationIndex({
    students,
    rubricVersions,
    surahs,
    materials,
    recentRecords,
    today,
    canRecord,
}: Props) {
    const firstVersion = rubricVersions[0] ?? null;

    const form = useForm({
        student_id: students[0] ? String(students[0].id) : '',
        rubric_version_id: firstVersion ? String(firstVersion.id) : '',
        kind: 'quran' as 'quran' | 'hadith' | 'prayer',
        type: 'ziyadah' as 'ziyadah' | 'murajaah',
        memorization_material_id: '',
        surah_from: '114',
        ayah_from: '1',
        surah_to: '114',
        ayah_to: '6',
        recorded_on: today,
        note: '',
    });

    const [scores, setScores] = useState<Record<number, number>>(() => {
        const init: Record<number, number> = {};
        for (const c of firstVersion?.criteria ?? []) {
            init[c.id] = c.mode === 'deduction' ? 0 : c.max_score;
        }
        return init;
    });

    const selectedVersion =
        rubricVersions.find(
            (v) => String(v.id) === form.data.rubric_version_id,
        ) ?? firstVersion;

    const deductionCriteria = (selectedVersion?.criteria ?? []).filter(
        (c) => c.mode === 'deduction',
    );
    const directCriteria = (selectedVersion?.criteria ?? []).filter(
        (c) => c.mode === 'direct',
    );

    const [activeCriterionId, setActiveCriterionId] = useState<number | null>(
        deductionCriteria[0]?.id ?? null,
    );

    useEffect(() => {
        if (!selectedVersion) {
            return;
        }
        const nextScores: Record<number, number> = {};
        for (const c of selectedVersion.criteria) {
            nextScores[c.id] = c.mode === 'deduction' ? 0 : c.max_score;
        }
        setScores(nextScores);
        const firstDeduction = selectedVersion.criteria.find(
            (c) => c.mode === 'deduction',
        );
        setActiveCriterionId(firstDeduction?.id ?? null);
    }, [selectedVersion?.id]);

    function advanceStudent() {
        const idx = students.findIndex(
            (s) => String(s.id) === form.data.student_id,
        );
        if (idx >= 0 && idx + 1 < students.length) {
            form.setData('student_id', String(students[idx + 1].id));
        }
        if (selectedVersion) {
            const reset: Record<number, number> = {};
            for (const c of selectedVersion.criteria) {
                reset[c.id] = c.mode === 'deduction' ? 0 : c.max_score;
            }
            setScores(reset);
        }
    }

    function submit(e: FormEvent<HTMLFormElement>) {
        e.preventDefault();
        router.post(
            MemorizationController.store.url(),
            {
                student_id: Number(form.data.student_id),
                rubric_version_id: Number(form.data.rubric_version_id),
                kind: form.data.kind,
                type: form.data.kind === 'quran' ? form.data.type : null,
                memorization_material_id:
                    form.data.kind !== 'quran'
                        ? Number(form.data.memorization_material_id)
                        : null,
                surah_from:
                    form.data.kind === 'quran'
                        ? Number(form.data.surah_from)
                        : null,
                ayah_from:
                    form.data.kind === 'quran'
                        ? Number(form.data.ayah_from)
                        : null,
                surah_to:
                    form.data.kind === 'quran'
                        ? Number(form.data.surah_to)
                        : null,
                ayah_to:
                    form.data.kind === 'quran'
                        ? Number(form.data.ayah_to)
                        : null,
                recorded_on: form.data.recorded_on,
                note: form.data.note || null,
                scores,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    form.setData('note', '');
                    advanceStudent();
                },
                onError: (errors) =>
                    form.setError(errors as Record<string, string>),
            },
        );
    }

    const mistakeItems: MistakeCounterItem[] = deductionCriteria.map((c) => ({
        id: c.id,
        label: c.name,
        value: scores[c.id] ?? 0,
        subtitle: `Bobot ${c.weight}% · −${c.deduction_per_error}/salah`,
    }));

    const filteredMaterials = materials.filter(
        (m) => m.kind === form.data.kind,
    );

    return (
        <>
            <Head title="Setoran hafalan" />
            <div className="space-y-6">
                <PageHeader
                    title="Setoran hafalan"
                    description="Catat setoran Al-Qur'an, Hadits, dan Do'a dengan rubrik penilaian terukur."
                />

                {canRecord ? (
                    rubricVersions.length === 0 ? (
                        <EmptyState
                            title="Belum ada rubrik terbit"
                            description="Admin perlu menerbitkan minimal satu versi rubrik sebelum guru dapat mencatat setoran."
                        />
                    ) : (
                        <form
                            onSubmit={submit}
                            className="space-y-5 rounded-lg border border-border bg-card p-5 shadow-xs"
                        >
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-4">
                                <div className="space-y-2 sm:col-span-2">
                                    <Label htmlFor="mem-student">Siswa</Label>
                                    <Select
                                        value={form.data.student_id}
                                        onValueChange={(v) =>
                                            form.setData('student_id', v)
                                        }
                                    >
                                        <SelectTrigger id="mem-student">
                                            <SelectValue placeholder="Pilih siswa" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {students.map((s) => (
                                                <SelectItem
                                                    key={s.id}
                                                    value={String(s.id)}
                                                >
                                                    {s.name} ({s.classroom_name}{' '}
                                                    · {s.nis})
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError
                                        message={form.errors.student_id}
                                    />
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="mem-rubric">Rubrik</Label>
                                    <Select
                                        value={form.data.rubric_version_id}
                                        onValueChange={(v) =>
                                            form.setData('rubric_version_id', v)
                                        }
                                    >
                                        <SelectTrigger id="mem-rubric">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {rubricVersions.map((v) => (
                                                <SelectItem
                                                    key={v.id}
                                                    value={String(v.id)}
                                                >
                                                    {v.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="mem-date">Tanggal</Label>
                                    <Input
                                        id="mem-date"
                                        type="date"
                                        value={form.data.recorded_on}
                                        onChange={(e) =>
                                            form.setData(
                                                'recorded_on',
                                                e.target.value,
                                            )
                                        }
                                        required
                                    />
                                </div>
                            </div>

                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-4">
                                <div className="space-y-2">
                                    <Label htmlFor="mem-kind">
                                        Jenis hafalan
                                    </Label>
                                    <Select
                                        value={form.data.kind}
                                        onValueChange={(v) =>
                                            form.setData(
                                                'kind',
                                                v as
                                                    | 'quran'
                                                    | 'hadith'
                                                    | 'prayer',
                                            )
                                        }
                                    >
                                        <SelectTrigger id="mem-kind">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="quran">
                                                Al-Qur'an
                                            </SelectItem>
                                            <SelectItem value="hadith">
                                                Hadits
                                            </SelectItem>
                                            <SelectItem value="prayer">
                                                Do'a
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>

                                {form.data.kind === 'quran' ? (
                                    <>
                                        <div className="space-y-2">
                                            <Label htmlFor="mem-type">
                                                Tipe
                                            </Label>
                                            <Select
                                                value={form.data.type}
                                                onValueChange={(v) =>
                                                    form.setData(
                                                        'type',
                                                        v as
                                                            | 'ziyadah'
                                                            | 'murajaah',
                                                    )
                                                }
                                            >
                                                <SelectTrigger id="mem-type">
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="ziyadah">
                                                        Ziyadah
                                                    </SelectItem>
                                                    <SelectItem value="murajaah">
                                                        Murajaah
                                                    </SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </div>

                                        <div className="space-y-2 sm:col-span-2">
                                            <Label>
                                                Surah & ayat (awal → akhir)
                                            </Label>
                                            <div className="grid grid-cols-4 gap-2">
                                                <Select
                                                    value={form.data.surah_from}
                                                    onValueChange={(v) => {
                                                        form.setData(
                                                            'surah_from',
                                                            v,
                                                        );
                                                        form.setData(
                                                            'surah_to',
                                                            v,
                                                        );
                                                    }}
                                                >
                                                    <SelectTrigger className="col-span-2">
                                                        <SelectValue />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        {surahs.map((s) => (
                                                            <SelectItem
                                                                key={s.number}
                                                                value={String(
                                                                    s.number,
                                                                )}
                                                            >
                                                                {s.number}.{' '}
                                                                {s.name_latin}
                                                            </SelectItem>
                                                        ))}
                                                    </SelectContent>
                                                </Select>
                                                <Input
                                                    type="number"
                                                    min={1}
                                                    value={form.data.ayah_from}
                                                    aria-label="Ayat awal"
                                                    onChange={(e) =>
                                                        form.setData(
                                                            'ayah_from',
                                                            e.target.value,
                                                        )
                                                    }
                                                />
                                                <Input
                                                    type="number"
                                                    min={1}
                                                    value={form.data.ayah_to}
                                                    aria-label="Ayat akhir"
                                                    onChange={(e) =>
                                                        form.setData(
                                                            'ayah_to',
                                                            e.target.value,
                                                        )
                                                    }
                                                />
                                            </div>
                                            <InputError
                                                message={form.errors.ayah_to}
                                            />
                                        </div>
                                    </>
                                ) : (
                                    <div className="space-y-2 sm:col-span-3">
                                        <Label htmlFor="mem-material">
                                            Materi
                                        </Label>
                                        <Select
                                            value={
                                                form.data
                                                    .memorization_material_id
                                            }
                                            onValueChange={(v) =>
                                                form.setData(
                                                    'memorization_material_id',
                                                    v,
                                                )
                                            }
                                        >
                                            <SelectTrigger id="mem-material">
                                                <SelectValue placeholder="Pilih materi" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {filteredMaterials.map((m) => (
                                                    <SelectItem
                                                        key={m.id}
                                                        value={String(m.id)}
                                                    >
                                                        {m.title}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        <InputError
                                            message={
                                                form.errors
                                                    .memorization_material_id
                                            }
                                        />
                                    </div>
                                )}
                            </div>

                            {mistakeItems.length > 0 ? (
                                <MistakeCounterGroup
                                    items={mistakeItems}
                                    activeId={activeCriterionId}
                                    onSelectActive={setActiveCriterionId}
                                    onChange={(id, val) =>
                                        setScores((prev) => ({
                                            ...prev,
                                            [id]: val,
                                        }))
                                    }
                                />
                            ) : null}

                            {directCriteria.length > 0 ? (
                                <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                                    {directCriteria.map((c) => (
                                        <div key={c.id} className="space-y-1.5">
                                            <Label htmlFor={`crit-${c.id}`}>
                                                {c.name} (maks {c.max_score})
                                            </Label>
                                            <Input
                                                id={`crit-${c.id}`}
                                                type="number"
                                                min={0}
                                                max={c.max_score}
                                                value={scores[c.id] ?? 0}
                                                onChange={(e) =>
                                                    setScores((prev) => ({
                                                        ...prev,
                                                        [c.id]: Number(
                                                            e.target.value,
                                                        ),
                                                    }))
                                                }
                                            />
                                        </div>
                                    ))}
                                </div>
                            ) : null}

                            <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                                <div className="flex-1 space-y-1.5">
                                    <Label htmlFor="mem-note">
                                        Catatan (opsional)
                                    </Label>
                                    <Textarea
                                        id="mem-note"
                                        rows={2}
                                        value={form.data.note}
                                        onChange={(e) =>
                                            form.setData('note', e.target.value)
                                        }
                                        placeholder="Catatan kelancaran atau tajwid..."
                                    />
                                </div>
                                <Button
                                    type="submit"
                                    disabled={form.processing}
                                >
                                    Simpan & lanjut siswa berikut
                                    <ChevronRight className="ml-1.5 h-4 w-4" />
                                </Button>
                            </div>
                        </form>
                    )
                ) : null}

                <section className="space-y-3">
                    <h2 className="text-sm font-semibold text-foreground">
                        Setoran terbaru
                    </h2>
                    {recentRecords.length === 0 ? (
                        <EmptyState
                            title="Belum ada setoran tercatat"
                            description="Setoran hafalan siswa yang dicatat guru akan muncul di sini."
                        />
                    ) : (
                        <div className="divide-y divide-border rounded-lg border border-border bg-card shadow-xs">
                            {recentRecords.map((rec) => (
                                <div
                                    key={rec.id}
                                    className="flex flex-wrap items-center justify-between gap-3 px-4 py-3 text-sm"
                                >
                                    <div>
                                        <div className="font-medium text-foreground">
                                            {rec.student_name} — {rec.summary}
                                        </div>
                                        <div className="text-xs text-muted-foreground">
                                            {rec.kind_label}
                                            {rec.type
                                                ? ` (${rec.type})`
                                                : ''} ·{' '}
                                            <DateText value={rec.recorded_on} />
                                        </div>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <span className="font-semibold tabular-nums">
                                            {rec.final_score} ({rec.predicate})
                                        </span>
                                        <StatusBadge
                                            variant={
                                                rec.passed
                                                    ? 'success'
                                                    : 'warning'
                                            }
                                        >
                                            {rec.passed ? 'Lulus' : 'Mengulang'}
                                        </StatusBadge>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </section>
            </div>
        </>
    );
}

MemorizationIndex.layout = (page: ReactNode) => (
    <StaffLayout
        breadcrumbs={[
            { title: 'Ibadah', href: '#' },
            {
                title: 'Setoran hafalan',
                href: MemorizationController.index.url(),
            },
        ]}
    >
        {page}
    </StaffLayout>
);
