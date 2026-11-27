import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Check, Info, Lock, PencilLine, RefreshCw, Unlock } from 'lucide-react';
import type { FormEvent } from 'react';
import { Fragment, useState } from 'react';
import CharacterAssessmentController from '@/actions/App/Http/Controllers/Admin/CharacterAssessmentController';
import { ConfirmDialog } from '@/components/app/confirm-dialog';
import { DateText } from '@/components/app/date-text';
import { EmptyState } from '@/components/app/empty-state';
import { FormDialog } from '@/components/app/form-dialog';
import { PageHeader } from '@/components/app/page-header';
import { StatusBadge } from '@/components/app/status-badge';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
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
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes/admin';

type Level = { code: string; label: string; min: number };

type ComponentDetail = {
    phrase: string;
    weight: number;
    effective_weight: number;
    score: number | null;
    coverage: number | null;
    used: boolean;
    reason: string | null;
};

type Assessment = {
    id: number;
    computed_score: number | null;
    computed_level: string | null;
    final_level: string | null;
    override_reason: string | null;
    observation_level: number | null;
    description: string | null;
    description_edited: boolean;
    components: ComponentDetail[];
};

type Row = {
    student_id: number;
    name: string;
    nis: string;
    assessments: Record<number, Assessment>;
};

type Dimension = { id: number; name: string; has_observation: boolean };

type Props = {
    classroom: { id: number; name: string; semester: string };
    scale: Level[];
    configured: boolean;
    dimensions: Dimension[];
    review: {
        status: 'draft' | 'submitted' | 'approved';
        status_label: string;
        computed_at: string | null;
        submitted_by: string | null;
        submitted_at: string | null;
        approved_by: string | null;
        approved_at: string | null;
        reopen_reason: string | null;
    } | null;
    rows: Row[];
    can: { manage: boolean; review: boolean; reopen: boolean };
};

const STEPS = [
    { key: 'draft', label: 'Draf' },
    { key: 'submitted', label: 'Diajukan' },
    { key: 'approved', label: 'Disetujui kepala unit' },
] as const;

const OBSERVATION = [
    { value: 1, label: 'Perlu bimbingan' },
    { value: 2, label: 'Cukup' },
    { value: 3, label: 'Baik' },
    { value: 4, label: 'Sangat baik' },
];

export default function CharacterShow(props: Props) {
    const { classroom, scale, dimensions, review, rows, can } = props;
    const [editing, setEditing] = useState<{
        row: Row;
        dimension: Dimension;
        assessment: Assessment;
    } | null>(null);
    const [confirm, setConfirm] = useState<'submit' | 'approve' | null>(null);
    const [reopenOpen, setReopenOpen] = useState(false);
    const [busy, setBusy] = useState(false);

    const pageError = usePage<{ errors: Record<string, string> }>().props.errors
        .review;
    const levelLabel = (code: string | null): string =>
        scale.find((l) => l.code === code)?.label ?? code ?? '—';
    const status = review?.status ?? 'draft';
    const stepIndex = STEPS.findIndex((s) => s.key === status);
    const computed = review?.computed_at !== null && review !== null;

    const missing = rows.reduce(
        (sum, row) =>
            sum +
            Object.values(row.assessments).filter(
                (a) => a.final_level === null && a.computed_level === null,
            ).length,
        0,
    );

    const post = (url: string): void => {
        router.post(
            url,
            {},
            {
                preserveScroll: true,
                onStart: () => setBusy(true),
                onFinish: () => {
                    setBusy(false);
                    setConfirm(null);
                },
            },
        );
    };

    return (
        <StaffLayout
            breadcrumbs={[
                { title: 'Kesiswaan', href: dashboard() },
                {
                    title: 'Penilaian karakter',
                    href: CharacterAssessmentController.index(),
                },
                {
                    title: classroom.name,
                    href: CharacterAssessmentController.show(classroom.id),
                },
            ]}
        >
            <Head title={`Penilaian karakter ${classroom.name}`} />

            <div className="space-y-5">
                <PageHeader
                    title={`Penilaian karakter · ${classroom.name}`}
                    description={`Semester ${classroom.semester}`}
                    actions={
                        <div className="flex flex-wrap gap-2">
                            {can.manage && (
                                <Button
                                    type="button"
                                    variant="outline"
                                    disabled={busy}
                                    onClick={() =>
                                        post(
                                            CharacterAssessmentController.compute.url(
                                                classroom.id,
                                            ),
                                        )
                                    }
                                >
                                    <RefreshCw
                                        className="size-4"
                                        strokeWidth={1.75}
                                    />
                                    {computed
                                        ? 'Hitung ulang'
                                        : 'Hitung penilaian'}
                                </Button>
                            )}
                            {can.manage && computed && (
                                <Button
                                    type="button"
                                    disabled={busy}
                                    onClick={() => setConfirm('submit')}
                                >
                                    <Lock
                                        className="size-4"
                                        strokeWidth={1.75}
                                    />
                                    Ajukan & kunci
                                </Button>
                            )}
                            {can.reopen && (
                                <Button
                                    type="button"
                                    variant="outline"
                                    disabled={busy}
                                    onClick={() => setReopenOpen(true)}
                                >
                                    <Unlock
                                        className="size-4"
                                        strokeWidth={1.75}
                                    />
                                    Buka kunci
                                </Button>
                            )}
                            {can.review && (
                                <Button
                                    type="button"
                                    disabled={busy}
                                    onClick={() => setConfirm('approve')}
                                >
                                    <Check
                                        className="size-4"
                                        strokeWidth={1.75}
                                    />
                                    Setujui
                                </Button>
                            )}
                        </div>
                    }
                />

                <ol
                    className="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm"
                    aria-label="Status penilaian"
                >
                    {STEPS.map((step, index) => (
                        <Fragment key={step.key}>
                            {index > 0 && (
                                <li
                                    aria-hidden
                                    className="h-px w-6 bg-border"
                                />
                            )}
                            <li
                                aria-current={
                                    index === stepIndex ? 'step' : undefined
                                }
                                className={cn(
                                    'flex items-center gap-1.5',
                                    index === stepIndex
                                        ? 'font-semibold text-foreground'
                                        : 'text-muted-foreground',
                                )}
                            >
                                <span
                                    className={cn(
                                        'size-2.5 rounded-full border',
                                        index <= stepIndex
                                            ? 'border-primary bg-primary'
                                            : 'border-muted-foreground/40',
                                    )}
                                    aria-hidden
                                />
                                {step.label}
                            </li>
                        </Fragment>
                    ))}
                    {review?.submitted_by && status !== 'draft' && (
                        <li className="ml-2 text-xs text-muted-foreground">
                            diajukan {review.submitted_by} ·{' '}
                            <DateText value={review.submitted_at} />
                        </li>
                    )}
                </ol>

                {pageError && (
                    <p
                        role="alert"
                        className="rounded-lg border border-destructive/40 bg-destructive/10 px-3 py-2.5 text-sm text-destructive"
                    >
                        {pageError}
                    </p>
                )}

                <p className="flex gap-2 rounded-lg border border-border bg-muted/50 px-3 py-2.5 text-sm text-muted-foreground">
                    <Info
                        className="mt-0.5 size-4 shrink-0"
                        strokeWidth={1.75}
                    />
                    {status === 'draft' && review?.reopen_reason
                        ? `Kunci dibuka: ${review.reopen_reason}`
                        : 'Setelah diajukan, nilai terkunci kecuali dibuka kepala unit. Wali murid melihat nilai setelah disetujui.'}
                </p>

                {!props.configured ? (
                    <EmptyState
                        title="Penilaian karakter unit ini belum diatur"
                        description="Admin perlu memilih kurikulum dan dimensi di Pengaturan › Penilaian karakter."
                    />
                ) : !computed ? (
                    <EmptyState
                        title="Belum dihitung"
                        description="Hitung penilaian untuk menyusun capaian dari pembiasaan, kehadiran, dan catatan perilaku semester ini."
                        action={
                            can.manage ? (
                                <Button
                                    type="button"
                                    disabled={busy}
                                    onClick={() =>
                                        post(
                                            CharacterAssessmentController.compute.url(
                                                classroom.id,
                                            ),
                                        )
                                    }
                                >
                                    <RefreshCw
                                        className="size-4"
                                        strokeWidth={1.75}
                                    />
                                    Hitung penilaian
                                </Button>
                            ) : undefined
                        }
                    />
                ) : (
                    <>
                        {missing > 0 && (
                            <p className="rounded-lg border border-accent-attention/60 bg-accent-attention/10 px-3 py-2.5 text-sm">
                                <span className="font-semibold">
                                    {missing} capaian bertanda data kurang.
                                </span>{' '}
                                Tetapkan capaiannya dengan alasan sebelum
                                mengajukan.
                            </p>
                        )}

                        <div className="overflow-x-auto rounded-xl border border-border bg-card shadow-xs">
                            <table className="w-full min-w-[640px] text-sm">
                                <thead className="bg-muted/60 text-xs text-muted-foreground">
                                    <tr>
                                        <th className="px-4 py-2.5 text-left font-medium">
                                            Siswa
                                        </th>
                                        {dimensions.map((d) => (
                                            <th
                                                key={d.id}
                                                className="px-3 py-2.5 text-center font-medium"
                                            >
                                                {d.name}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border">
                                    {rows.map((row) => (
                                        <tr key={row.student_id}>
                                            <td className="px-4 py-3">
                                                <div className="font-medium">
                                                    {row.name}
                                                </div>
                                                <div className="text-xs text-muted-foreground">
                                                    NIS {row.nis}
                                                </div>
                                            </td>
                                            {dimensions.map((dimension) => {
                                                const a =
                                                    row.assessments[
                                                        dimension.id
                                                    ];

                                                return (
                                                    <td
                                                        key={dimension.id}
                                                        className="px-3 py-2 text-center"
                                                    >
                                                        {a ? (
                                                            <LevelCell
                                                                assessment={a}
                                                                label={levelLabel(
                                                                    a.final_level ??
                                                                        a.computed_level,
                                                                )}
                                                                onOpen={() =>
                                                                    setEditing({
                                                                        row,
                                                                        dimension,
                                                                        assessment:
                                                                            a,
                                                                    })
                                                                }
                                                                editable={
                                                                    can.manage ||
                                                                    can.review
                                                                }
                                                            />
                                                        ) : (
                                                            <span className="text-xs text-muted-foreground">
                                                                Hitung ulang
                                                            </span>
                                                        )}
                                                    </td>
                                                );
                                            })}
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </>
                )}
            </div>

            {editing && (
                <AssessmentDialog
                    key={editing.assessment.id}
                    {...editing}
                    scale={scale}
                    readOnly={!(can.manage || can.review)}
                    onClose={() => setEditing(null)}
                />
            )}

            <ConfirmDialog
                open={confirm !== null}
                onOpenChange={(open) => !open && setConfirm(null)}
                title={
                    confirm === 'submit'
                        ? `Ajukan penilaian ${classroom.name}?`
                        : `Setujui penilaian ${classroom.name}?`
                }
                description={
                    confirm === 'submit'
                        ? 'Nilai dikunci dan dikirim ke kepala unit. Perubahan berikutnya hanya lewat kepala unit.'
                        : 'Wali murid dapat melihat capaian dan deskripsi setelah disetujui.'
                }
                confirmLabel={
                    confirm === 'submit' ? 'Ajukan & kunci' : 'Setujui'
                }
                loading={busy}
                onConfirm={() =>
                    post(
                        confirm === 'submit'
                            ? CharacterAssessmentController.submit.url(
                                  classroom.id,
                              )
                            : CharacterAssessmentController.approve.url(
                                  classroom.id,
                              ),
                    )
                }
            />

            <ReopenDialog
                open={reopenOpen}
                onOpenChange={setReopenOpen}
                classroomId={classroom.id}
            />
        </StaffLayout>
    );
}

function LevelCell({
    assessment,
    label,
    onOpen,
    editable,
}: {
    assessment: Assessment;
    label: string;
    onOpen: () => void;
    editable: boolean;
}) {
    const insufficient =
        assessment.final_level === null && assessment.computed_level === null;

    return (
        <button
            type="button"
            onClick={onOpen}
            className="group mx-auto flex w-full max-w-48 flex-col items-center gap-0.5 rounded-md px-2 py-1.5 transition-colors duration-150 hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
            aria-label={`${editable ? 'Ubah' : 'Lihat'} penilaian: ${insufficient ? 'data kurang' : label}`}
        >
            {insufficient ? (
                <StatusBadge variant="attention">Data kurang</StatusBadge>
            ) : (
                <span className="flex items-center gap-1 text-sm font-medium">
                    {label}
                    {assessment.final_level && (
                        <PencilLine
                            className="size-3.5 text-muted-foreground"
                            strokeWidth={1.75}
                            aria-label="diubah"
                        />
                    )}
                </span>
            )}
            <span className="text-xs text-muted-foreground tabular-nums">
                {assessment.computed_score !== null
                    ? `skor ${assessment.computed_score.toLocaleString('id-ID')}`
                    : 'tanpa skor'}
            </span>
        </button>
    );
}

function AssessmentDialog({
    row,
    dimension,
    assessment,
    scale,
    readOnly,
    onClose,
}: {
    row: Row;
    dimension: Dimension;
    assessment: Assessment;
    scale: Level[];
    readOnly: boolean;
    onClose: () => void;
}) {
    const form = useForm<{
        final_level: string;
        override_reason: string;
        observation_level: string;
        description: string;
    }>({
        final_level: assessment.final_level ?? assessment.computed_level ?? '',
        override_reason: assessment.override_reason ?? '',
        observation_level: assessment.observation_level
            ? String(assessment.observation_level)
            : '',
        description: assessment.description ?? '',
    });

    const differs =
        form.data.final_level !== '' &&
        form.data.final_level !== assessment.computed_level;

    const submit = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();

        if (readOnly) {
            onClose();

            return;
        }

        form.transform((data) => ({
            final_level: data.final_level || null,
            override_reason: differs ? data.override_reason : null,
            observation_level: data.observation_level
                ? Number(data.observation_level)
                : null,
            description: data.description,
        }));
        form.patch(CharacterAssessmentController.correct.url(assessment.id), {
            preserveScroll: true,
            onSuccess: onClose,
        });
    };

    return (
        <FormDialog
            open
            onOpenChange={(open) => !open && onClose()}
            title={`${row.name} · ${dimension.name}`}
            description={
                assessment.computed_level
                    ? `Hasil hitung: ${scale.find((l) => l.code === assessment.computed_level)?.label ?? assessment.computed_level} (skor ${assessment.computed_score?.toLocaleString('id-ID')})`
                    : 'Data kurang: capaian wajib ditetapkan wali kelas.'
            }
            onSubmit={submit}
            processing={form.processing}
            submitLabel={readOnly ? 'Tutup' : 'Simpan'}
            contentClassName="sm:max-w-xl"
        >
            <div className="rounded-lg border border-border">
                <table className="w-full text-sm">
                    <thead className="bg-muted/50 text-xs text-muted-foreground">
                        <tr>
                            <th className="px-3 py-2 text-left font-medium">
                                Sumber
                            </th>
                            <th className="px-3 py-2 text-right font-medium">
                                Skor
                            </th>
                            <th className="px-3 py-2 text-right font-medium">
                                Bobot
                            </th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-border">
                        {assessment.components.map((c, index) => (
                            <tr
                                key={index}
                                className={cn(
                                    !c.used && 'text-muted-foreground',
                                )}
                            >
                                <td className="px-3 py-2">
                                    <div className="first-letter:uppercase">
                                        {c.phrase}
                                    </div>
                                    {(c.reason || c.coverage !== null) && (
                                        <div className="text-xs text-muted-foreground">
                                            {c.reason ?? ''}
                                            {c.coverage !== null &&
                                                `${c.reason ? ' · ' : ''}cakupan ${c.coverage.toLocaleString('id-ID')}%`}
                                        </div>
                                    )}
                                </td>
                                <td className="px-3 py-2 text-right tabular-nums">
                                    {c.score !== null
                                        ? c.score.toLocaleString('id-ID')
                                        : '—'}
                                </td>
                                <td className="px-3 py-2 text-right tabular-nums">
                                    {c.used
                                        ? `${c.effective_weight.toLocaleString('id-ID')}%`
                                        : 'tidak dipakai'}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {dimension.has_observation && (
                <div className="grid gap-2">
                    <Label>Observasi wali kelas</Label>
                    <Select
                        disabled={readOnly}
                        value={form.data.observation_level}
                        onValueChange={(v) =>
                            form.setData('observation_level', v)
                        }
                    >
                        <SelectTrigger aria-label="Observasi wali kelas">
                            <SelectValue placeholder="Belum diisi" />
                        </SelectTrigger>
                        <SelectContent>
                            {OBSERVATION.map((o) => (
                                <SelectItem
                                    key={o.value}
                                    value={String(o.value)}
                                >
                                    {o.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <InputError message={form.errors.observation_level} />
                </div>
            )}

            <div className="grid gap-2">
                <Label>Capaian akhir</Label>
                <Select
                    disabled={readOnly}
                    value={form.data.final_level}
                    onValueChange={(v) => form.setData('final_level', v)}
                >
                    <SelectTrigger aria-label="Capaian akhir">
                        <SelectValue placeholder="Pilih capaian" />
                    </SelectTrigger>
                    <SelectContent>
                        {scale.map((level) => (
                            <SelectItem key={level.code} value={level.code}>
                                {level.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <InputError message={form.errors.final_level} />
            </div>

            {differs && !readOnly && (
                <div className="grid gap-2">
                    <Label htmlFor="override-reason">
                        Alasan mengubah capaian
                    </Label>
                    <Textarea
                        id="override-reason"
                        rows={2}
                        maxLength={500}
                        required
                        value={form.data.override_reason}
                        onChange={(e) =>
                            form.setData('override_reason', e.target.value)
                        }
                    />
                    <InputError message={form.errors.override_reason} />
                </div>
            )}

            <div className="grid gap-2">
                <Label htmlFor="description">Deskripsi</Label>
                <Textarea
                    id="description"
                    rows={3}
                    maxLength={600}
                    readOnly={readOnly}
                    value={form.data.description}
                    onChange={(e) =>
                        form.setData('description', e.target.value)
                    }
                />
                <p className="text-xs text-muted-foreground">
                    {assessment.description_edited
                        ? 'Deskripsi sudah diubah manual; hitung ulang tidak menimpanya.'
                        : 'Disusun otomatis dari templat; mengubahnya menjadikannya manual.'}
                </p>
                <InputError message={form.errors.description} />
            </div>
        </FormDialog>
    );
}

function ReopenDialog({
    open,
    onOpenChange,
    classroomId,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    classroomId: number;
}) {
    const form = useForm({ reason: '' });

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title="Buka kunci penilaian"
            description="Penilaian kembali menjadi draf dan wali kelas dapat mengubahnya. Alasan tercatat."
            submitLabel="Buka kunci"
            processing={form.processing}
            onSubmit={(event) => {
                event.preventDefault();
                form.post(
                    CharacterAssessmentController.reopen.url(classroomId),
                    {
                        preserveScroll: true,
                        onSuccess: () => {
                            form.reset();
                            onOpenChange(false);
                        },
                    },
                );
            }}
        >
            <div className="grid gap-2">
                <Label htmlFor="reopen-reason">Alasan</Label>
                <Textarea
                    id="reopen-reason"
                    rows={3}
                    maxLength={500}
                    required
                    value={form.data.reason}
                    onChange={(e) => form.setData('reason', e.target.value)}
                />
                <InputError message={form.errors.reason} />
            </div>
        </FormDialog>
    );
}
