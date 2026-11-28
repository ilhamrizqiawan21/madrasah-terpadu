import { Head, router, useForm } from '@inertiajs/react';
import { Plus, Save } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { useState } from 'react';
import PibController from '@/actions/App/Http/Controllers/Admin/PibController';
import { EmptyState } from '@/components/app/empty-state';
import { FormDialog } from '@/components/app/form-dialog';
import { MistakeCounterGroup } from '@/components/app/mistake-counter';
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
import StaffLayout from '@/layouts/staff-layout';

type MaterialNode = { id: number; name: string; weight: number };
type SubchapterNode = { id: number; name: string; materials: MaterialNode[] };
type ChapterNode = { id: number; name: string; subchapters: SubchapterNode[] };
type ClassroomOption = { id: number; label: string };
type UnitOption = { id: number; code: string; name: string };
type StudentRow = {
    student_id: number;
    nis: string;
    name: string;
    mistakes: number | null;
    score: number | null;
};

type Props = {
    chapters: ChapterNode[];
    classrooms: ClassroomOption[];
    units: UnitOption[];
    selectedClassroomId: number | null;
    selectedMaterialId: number | null;
    students: StudentRow[];
    canManage: boolean;
    canRecord: boolean;
};

export default function PibIndex({
    chapters,
    classrooms,
    units,
    selectedClassroomId,
    selectedMaterialId,
    students,
    canManage,
    canRecord,
}: Props) {
    const [activeStudentId, setActiveStudentId] = useState<number | null>(
        students[0]?.student_id ?? null,
    );
    const [mistakesMap, setMistakesMap] = useState<Record<number, number>>(
        () => {
            const init: Record<number, number> = {};
            for (const s of students) {
                init[s.student_id] = s.mistakes ?? 0;
            }
            return init;
        },
    );

    const [chapterOpen, setChapterOpen] = useState(false);
    const [subchapterOpen, setSubchapterOpen] = useState(false);
    const [materialOpen, setMaterialOpen] = useState(false);

    const chapterForm = useForm({ unit_id: 'semua', name: '' });
    const subchapterForm = useForm({
        pib_chapter_id: chapters[0] ? String(chapters[0].id) : '',
        name: '',
    });
    const allSubchapters = chapters.flatMap((c) =>
        c.subchapters.map((s) => ({
            id: s.id,
            label: `${c.name} › ${s.name}`,
        })),
    );
    const materialForm = useForm({
        pib_subchapter_id: allSubchapters[0]
            ? String(allSubchapters[0].id)
            : '',
        name: '',
        weight: '1',
    });

    const allMaterials = chapters.flatMap((c) =>
        c.subchapters.flatMap((s) =>
            s.materials.map((m) => ({
                id: m.id,
                label: `${c.name} › ${s.name} › ${m.name}`,
            })),
        ),
    );

    function visitFilters(next: {
        classroom_id?: number;
        material_id?: number;
    }) {
        router.get(
            PibController.index.url({
                query: {
                    classroom_id:
                        next.classroom_id ?? selectedClassroomId ?? undefined,
                    material_id:
                        next.material_id ?? selectedMaterialId ?? undefined,
                },
            }),
            {},
            { preserveState: false, replace: true },
        );
    }

    function saveStudentScore(studentId: number) {
        if (!selectedMaterialId) {
            return;
        }
        router.post(
            PibController.storeScore.url(),
            {
                student_id: studentId,
                pib_material_id: selectedMaterialId,
                mistakes: mistakesMap[studentId] ?? 0,
            },
            { preserveScroll: true },
        );
    }

    function submitChapter(e: FormEvent<HTMLFormElement>) {
        e.preventDefault();
        router.post(
            PibController.storeChapter.url(),
            {
                unit_id:
                    chapterForm.data.unit_id === 'semua'
                        ? null
                        : Number(chapterForm.data.unit_id),
                name: chapterForm.data.name,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    chapterForm.reset();
                    setChapterOpen(false);
                },
                onError: (errors) =>
                    chapterForm.setError(
                        errors as Record<'unit_id' | 'name', string>,
                    ),
            },
        );
    }

    function submitSubchapter(e: FormEvent<HTMLFormElement>) {
        e.preventDefault();
        router.post(
            PibController.storeSubchapter.url(),
            {
                pib_chapter_id: Number(subchapterForm.data.pib_chapter_id),
                name: subchapterForm.data.name,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    subchapterForm.setData('name', '');
                    setSubchapterOpen(false);
                },
                onError: (errors) =>
                    subchapterForm.setError(
                        errors as Record<'pib_chapter_id' | 'name', string>,
                    ),
            },
        );
    }

    function submitMaterial(e: FormEvent<HTMLFormElement>) {
        e.preventDefault();
        router.post(
            PibController.storeMaterial.url(),
            {
                pib_subchapter_id: Number(materialForm.data.pib_subchapter_id),
                name: materialForm.data.name,
                weight: Number(materialForm.data.weight),
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    materialForm.setData({
                        ...materialForm.data,
                        name: '',
                        weight: '1',
                    });
                    setMaterialOpen(false);
                },
                onError: (errors) =>
                    materialForm.setError(
                        errors as Record<
                            'pib_subchapter_id' | 'name' | 'weight',
                            string
                        >,
                    ),
            },
        );
    }

    const activeStudent =
        students.find((s) => s.student_id === activeStudentId) ??
        students[0] ??
        null;

    return (
        <>
            <Head title="Praktik ibadah (PIB)" />
            <div className="space-y-6">
                <PageHeader
                    title="Praktik ibadah (PIB)"
                    description="Penilaian praktik ibadah per rombel (nilai = 90 − jumlah kesalahan)."
                    actions={
                        canManage ? (
                            <div className="flex flex-wrap items-center gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => setChapterOpen(true)}
                                >
                                    <Plus className="mr-2 h-4 w-4" />
                                    Tambah bab
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    disabled={chapters.length === 0}
                                    onClick={() => {
                                        if (
                                            !subchapterForm.data
                                                .pib_chapter_id &&
                                            chapters[0]
                                        ) {
                                            subchapterForm.setData(
                                                'pib_chapter_id',
                                                String(chapters[0].id),
                                            );
                                        }
                                        setSubchapterOpen(true);
                                    }}
                                >
                                    <Plus className="mr-2 h-4 w-4" />
                                    Tambah subbab
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    disabled={allSubchapters.length === 0}
                                    onClick={() => {
                                        if (
                                            !materialForm.data
                                                .pib_subchapter_id &&
                                            allSubchapters[0]
                                        ) {
                                            materialForm.setData(
                                                'pib_subchapter_id',
                                                String(allSubchapters[0].id),
                                            );
                                        }
                                        setMaterialOpen(true);
                                    }}
                                >
                                    <Plus className="mr-2 h-4 w-4" />
                                    Tambah materi
                                </Button>
                            </div>
                        ) : null
                    }
                />

                <div className="flex flex-wrap items-center gap-3">
                    <Select
                        value={
                            selectedClassroomId
                                ? String(selectedClassroomId)
                                : ''
                        }
                        onValueChange={(v) =>
                            visitFilters({ classroom_id: Number(v) })
                        }
                    >
                        <SelectTrigger
                            className="w-[200px]"
                            aria-label="Pilih rombel"
                        >
                            <SelectValue placeholder="Pilih rombel" />
                        </SelectTrigger>
                        <SelectContent>
                            {classrooms.map((c) => (
                                <SelectItem key={c.id} value={String(c.id)}>
                                    {c.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    <Select
                        value={
                            selectedMaterialId ? String(selectedMaterialId) : ''
                        }
                        onValueChange={(v) =>
                            visitFilters({ material_id: Number(v) })
                        }
                    >
                        <SelectTrigger
                            className="min-w-[260px] flex-1 sm:max-w-md"
                            aria-label="Pilih materi PIB"
                        >
                            <SelectValue placeholder="Pilih materi PIB" />
                        </SelectTrigger>
                        <SelectContent>
                            {allMaterials.map((m) => (
                                <SelectItem key={m.id} value={String(m.id)}>
                                    {m.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                {allMaterials.length === 0 || students.length === 0 ? (
                    <EmptyState
                        title="Belum ada materi atau siswa PIB"
                        description="Tambahkan kurikulum bab/subbab/materi PIB dan pilih rombel aktif untuk mulai menilai."
                    />
                ) : (
                    <div className="space-y-4">
                        {canRecord && activeStudent ? (
                            <div className="space-y-3 rounded-lg border border-border bg-card p-4 shadow-xs">
                                <div className="flex flex-wrap items-center justify-between gap-2">
                                    <div>
                                        <p className="font-medium text-foreground">
                                            Penilaian cepat:{' '}
                                            {activeStudent.name}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            Pratinjau nilai:{' '}
                                            {Math.max(
                                                0,
                                                90 -
                                                    (mistakesMap[
                                                        activeStudent.student_id
                                                    ] ?? 0),
                                            )}{' '}
                                            / 90
                                        </p>
                                    </div>
                                    <Button
                                        type="button"
                                        onClick={() =>
                                            saveStudentScore(
                                                activeStudent.student_id,
                                            )
                                        }
                                    >
                                        <Save className="mr-1.5 h-4 w-4" />
                                        Simpan nilai
                                    </Button>
                                </div>

                                <MistakeCounterGroup
                                    items={[
                                        {
                                            id: activeStudent.student_id,
                                            label: 'Kesalahan',
                                            value:
                                                mistakesMap[
                                                    activeStudent.student_id
                                                ] ?? 0,
                                            max: 90,
                                            subtitle: 'Maksimal 90 kesalahan',
                                        },
                                    ]}
                                    activeId={activeStudent.student_id}
                                    onSelectActive={setActiveStudentId}
                                    onChange={(id, val) =>
                                        setMistakesMap((prev) => ({
                                            ...prev,
                                            [id]: val,
                                        }))
                                    }
                                />
                            </div>
                        ) : null}

                        <div className="divide-y divide-border rounded-lg border border-border bg-card shadow-xs">
                            {students.map((s) => (
                                <div
                                    key={s.student_id}
                                    onClick={() =>
                                        setActiveStudentId(s.student_id)
                                    }
                                    className="flex cursor-pointer items-center justify-between gap-3 px-4 py-3 text-sm hover:bg-muted/35"
                                >
                                    <div>
                                        <div className="font-medium text-foreground">
                                            {s.name}
                                        </div>
                                        <div className="text-xs text-muted-foreground">
                                            NIS {s.nis}
                                        </div>
                                    </div>
                                    <div className="flex items-center gap-3">
                                        {s.score !== null ? (
                                            <StatusBadge variant="success">
                                                Nilai {s.score} ({s.mistakes}{' '}
                                                salah)
                                            </StatusBadge>
                                        ) : (
                                            <StatusBadge variant="muted">
                                                Belum dinilai
                                            </StatusBadge>
                                        )}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                )}
            </div>

            <FormDialog
                open={chapterOpen}
                onOpenChange={setChapterOpen}
                title="Tambah bab PIB"
                onSubmit={submitChapter}
                processing={chapterForm.processing}
            >
                <div className="space-y-4">
                    <div className="space-y-2">
                        <Label htmlFor="pib-unit">Unit</Label>
                        <Select
                            value={chapterForm.data.unit_id}
                            onValueChange={(v) =>
                                chapterForm.setData('unit_id', v)
                            }
                        >
                            <SelectTrigger id="pib-unit">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="semua">
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
                    <div className="space-y-2">
                        <Label htmlFor="pib-chap-name">Nama bab</Label>
                        <Input
                            id="pib-chap-name"
                            value={chapterForm.data.name}
                            onChange={(e) =>
                                chapterForm.setData('name', e.target.value)
                            }
                            placeholder="Contoh: Thaharah / Sholat Wajib"
                            required
                        />
                        <InputError message={chapterForm.errors.name} />
                    </div>
                </div>
            </FormDialog>

            <FormDialog
                open={subchapterOpen}
                onOpenChange={setSubchapterOpen}
                title="Tambah subbab PIB"
                onSubmit={submitSubchapter}
                processing={subchapterForm.processing}
            >
                <div className="space-y-4">
                    <div className="space-y-2">
                        <Label htmlFor="pib-sub-chap">Bab</Label>
                        <Select
                            value={subchapterForm.data.pib_chapter_id}
                            onValueChange={(v) =>
                                subchapterForm.setData('pib_chapter_id', v)
                            }
                        >
                            <SelectTrigger id="pib-sub-chap">
                                <SelectValue placeholder="Pilih bab" />
                            </SelectTrigger>
                            <SelectContent>
                                {chapters.map((c) => (
                                    <SelectItem key={c.id} value={String(c.id)}>
                                        {c.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError
                            message={subchapterForm.errors.pib_chapter_id}
                        />
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="pib-sub-name">Nama subbab</Label>
                        <Input
                            id="pib-sub-name"
                            value={subchapterForm.data.name}
                            onChange={(e) =>
                                subchapterForm.setData('name', e.target.value)
                            }
                            placeholder="Contoh: Doa Harian / Bacaan Sholat"
                            required
                        />
                        <InputError message={subchapterForm.errors.name} />
                    </div>
                </div>
            </FormDialog>

            <FormDialog
                open={materialOpen}
                onOpenChange={setMaterialOpen}
                title="Tambah materi / komponen PIB"
                onSubmit={submitMaterial}
                processing={materialForm.processing}
            >
                <div className="space-y-4">
                    <div className="space-y-2">
                        <Label htmlFor="pib-mat-sub">Subbab</Label>
                        <Select
                            value={materialForm.data.pib_subchapter_id}
                            onValueChange={(v) =>
                                materialForm.setData('pib_subchapter_id', v)
                            }
                        >
                            <SelectTrigger id="pib-mat-sub">
                                <SelectValue placeholder="Pilih subbab" />
                            </SelectTrigger>
                            <SelectContent>
                                {allSubchapters.map((s) => (
                                    <SelectItem key={s.id} value={String(s.id)}>
                                        {s.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError
                            message={materialForm.errors.pib_subchapter_id}
                        />
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="pib-mat-name">
                            Nama materi / doa / komponen
                        </Label>
                        <Input
                            id="pib-mat-name"
                            value={materialForm.data.name}
                            onChange={(e) =>
                                materialForm.setData('name', e.target.value)
                            }
                            placeholder="Contoh: Doa Sebelum Makan / Niat Wudhu"
                            required
                        />
                        <InputError message={materialForm.errors.name} />
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="pib-mat-weight">Bobot</Label>
                        <Input
                            id="pib-mat-weight"
                            type="number"
                            min={0.1}
                            max={100}
                            step="0.1"
                            value={materialForm.data.weight}
                            onChange={(e) =>
                                materialForm.setData('weight', e.target.value)
                            }
                            required
                        />
                        <InputError message={materialForm.errors.weight} />
                    </div>
                </div>
            </FormDialog>
        </>
    );
}

PibIndex.layout = (page: ReactNode) => (
    <StaffLayout
        breadcrumbs={[
            { title: 'Kesiswaan & Asrama', href: '#' },
            { title: 'Praktik ibadah (PIB)', href: PibController.index.url() },
        ]}
    >
        {page}
    </StaffLayout>
);
