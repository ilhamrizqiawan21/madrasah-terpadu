import { Head, router, useForm } from '@inertiajs/react';
import { FileSpreadsheet, Save } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import CbtEssayScoreController from '@/actions/App/Http/Controllers/Admin/CbtEssayScoreController';
import CbtExamController from '@/actions/App/Http/Controllers/Admin/CbtExamController';
import AdminDashboardController from '@/actions/App/Http/Controllers/Admin/DashboardController';
import { EmptyState } from '@/components/app/empty-state';
import { FormDialog } from '@/components/app/form-dialog';
import { PageHeader } from '@/components/app/page-header';
import { StatusBadge } from '@/components/app/status-badge';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import StaffLayout from '@/layouts/staff-layout';

type EssayQuestion = {
    id: number;
    sort_order: number;
    body: string;
};

type SessionRow = {
    id: number;
    nis: string;
    name: string;
    status_label: string;
    final_score: number | null;
    essay_scores: Record<number, number>;
};

type Props = {
    exam: {
        id: number;
        title: string;
        subject: string;
        max_essay_weight: number;
    };
    essayQuestions: EssayQuestion[];
    sessions: SessionRow[];
};

export default function CbtEssayIndex({
    exam,
    essayQuestions,
    sessions,
}: Props) {
    const [draftScores, setDraftScores] = useState<
        Record<number, Record<number, string>>
    >(() => {
        const initial: Record<number, Record<number, string>> = {};
        for (const s of sessions) {
            initial[s.id] = {};
            for (const q of essayQuestions) {
                const existing = s.essay_scores[q.id];
                initial[s.id][q.id] =
                    existing !== undefined ? String(existing) : '';
            }
        }

        return initial;
    });

    const [savingSessionId, setSavingSessionId] = useState<number | null>(null);
    const [importOpen, setImportOpen] = useState(false);
    const importForm = useForm<{ file: File | null }>({ file: null });

    const setScore = (
        sessionId: number,
        questionId: number,
        val: string,
    ): void => {
        setDraftScores((prev) => ({
            ...prev,
            [sessionId]: {
                ...prev[sessionId],
                [questionId]: val,
            },
        }));
    };

    const saveRow = (sessionId: number): void => {
        const row = draftScores[sessionId] ?? {};
        const scores = essayQuestions
            .filter((q) => row[q.id] !== undefined && row[q.id] !== '')
            .map((q) => ({
                exam_question_id: q.id,
                score: Number(row[q.id]),
            }));

        if (scores.length === 0) {
            return;
        }

        setSavingSessionId(sessionId);
        router.post(
            CbtEssayScoreController.store.url(exam.id),
            {
                exam_session_id: sessionId,
                scores,
            },
            {
                preserveScroll: true,
                onFinish: () => setSavingSessionId(null),
            },
        );
    };

    const submitImport = (event: FormEvent): void => {
        event.preventDefault();
        importForm.post(CbtEssayScoreController.import.url(exam.id), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                setImportOpen(false);
                importForm.reset();
            },
        });
    };

    return (
        <StaffLayout
            breadcrumbs={[
                { title: 'Beranda', href: AdminDashboardController.url() },
                { title: 'Daftar ujian', href: CbtExamController.index.url() },
                {
                    title: 'Nilai essay',
                    href: CbtEssayScoreController.index.url(exam.id),
                },
            ]}
        >
            <Head title={`Nilai essay — ${exam.title}`} />

            <div className="space-y-6">
                <PageHeader
                    title={`Nilai essay — ${exam.title}`}
                    description={`${exam.subject} · Bobot maks per soal essay: ${exam.max_essay_weight}`}
                    actions={
                        essayQuestions.length > 0 ? (
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setImportOpen(true)}
                            >
                                <FileSpreadsheet
                                    className="size-4"
                                    strokeWidth={1.75}
                                />
                                Impor Excel
                            </Button>
                        ) : undefined
                    }
                />

                {essayQuestions.length === 0 ? (
                    <EmptyState
                        title="Tidak ada soal essay kertas"
                        description="Ujian ini tidak memiliki soal bertipe essay kertas."
                    />
                ) : sessions.length === 0 ? (
                    <EmptyState
                        title="Belum ada peserta ujian"
                        description="Belum ada siswa yang memulai sesi ujian ini."
                    />
                ) : (
                    <div className="overflow-x-auto rounded-lg border border-border bg-card shadow-xs">
                        <table className="w-full text-sm">
                            <thead className="border-b border-border bg-muted/50 text-xs">
                                <tr>
                                    <th className="px-3 py-2.5 text-center font-medium">
                                        NIS
                                    </th>
                                    <th className="px-3 py-2.5 text-center font-medium">
                                        Siswa
                                    </th>
                                    {essayQuestions.map((q, idx) => (
                                        <th
                                            key={q.id}
                                            className="px-3 py-2.5 text-center font-medium"
                                        >
                                            Essay #{idx + 1}
                                        </th>
                                    ))}
                                    <th className="px-3 py-2.5 text-center font-medium">
                                        Status / Nilai
                                    </th>
                                    <th className="px-3 py-2.5 text-center font-medium">
                                        Aksi
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {sessions.map((session) => (
                                    <tr key={session.id}>
                                        <td className="px-3 py-2 font-mono text-xs tabular-nums">
                                            {session.nis}
                                        </td>
                                        <td className="px-3 py-2 font-medium">
                                            {session.name}
                                        </td>
                                        {essayQuestions.map((q) => (
                                            <td
                                                key={q.id}
                                                className="px-3 py-2 text-center"
                                            >
                                                <Input
                                                    type="number"
                                                    min={0}
                                                    max={exam.max_essay_weight}
                                                    aria-label={`Nilai essay ${session.name} soal ${q.sort_order}`}
                                                    className="mx-auto w-20 text-right tabular-nums"
                                                    value={
                                                        draftScores[
                                                            session.id
                                                        ]?.[q.id] ?? ''
                                                    }
                                                    onChange={(event) =>
                                                        setScore(
                                                            session.id,
                                                            q.id,
                                                            event.target.value,
                                                        )
                                                    }
                                                />
                                            </td>
                                        ))}
                                        <td className="px-3 py-2 text-center">
                                            <div className="flex flex-col items-center gap-1">
                                                <StatusBadge
                                                    variant={
                                                        session.final_score !==
                                                        null
                                                            ? 'success'
                                                            : 'warning'
                                                    }
                                                >
                                                    {session.status_label}
                                                </StatusBadge>
                                                {session.final_score !==
                                                    null && (
                                                    <span className="text-xs font-semibold tabular-nums">
                                                        {session.final_score}
                                                    </span>
                                                )}
                                            </div>
                                        </td>
                                        <td className="px-3 py-2 text-center">
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="outline"
                                                disabled={
                                                    savingSessionId ===
                                                    session.id
                                                }
                                                onClick={() =>
                                                    saveRow(session.id)
                                                }
                                            >
                                                <Save
                                                    className="size-4"
                                                    strokeWidth={1.75}
                                                />
                                                Simpan
                                            </Button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>

            <FormDialog
                open={importOpen}
                onOpenChange={setImportOpen}
                title="Impor nilai essay (.xlsx)"
                description="Kolom: nis, question_order, score."
                processing={importForm.processing}
                submitLabel="Impor nilai"
                onSubmit={submitImport}
            >
                <div className="space-y-1.5">
                    <Label htmlFor="import-essay-xlsx">Berkas Excel</Label>
                    <Input
                        id="import-essay-xlsx"
                        type="file"
                        accept=".xlsx"
                        onChange={(event) =>
                            importForm.setData(
                                'file',
                                event.target.files?.[0] ?? null,
                            )
                        }
                    />
                    <InputError message={importForm.errors.file} />
                </div>
            </FormDialog>
        </StaffLayout>
    );
}
