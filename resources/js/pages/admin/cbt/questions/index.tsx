import { Head, router, useForm } from '@inertiajs/react';
import { FileSpreadsheet, KeyRound, Lock, Plus } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import CbtExamController from '@/actions/App/Http/Controllers/Admin/CbtExamController';
import CbtQuestionController from '@/actions/App/Http/Controllers/Admin/CbtQuestionController';
import AdminDashboardController from '@/actions/App/Http/Controllers/Admin/DashboardController';
import { ArabicText } from '@/components/app/arabic-text';
import { EmptyState } from '@/components/app/empty-state';
import { FormDialog } from '@/components/app/form-dialog';
import { PageHeader } from '@/components/app/page-header';
import { RowActions } from '@/components/app/row-actions';
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
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import StaffLayout from '@/layouts/staff-layout';

type QuestionOption = {
    id: number;
    label: string;
    body: string;
    sort_order: number;
};

type QuestionRow = {
    id: number;
    type: 'mcq' | 'matching' | 'essay_paper';
    type_label: string;
    body: string;
    is_rtl: boolean;
    image_url: string | null;
    sort_order: number;
    answer_key: { correct?: string; pairs?: Record<string, string> } | null;
    options: QuestionOption[];
};

type Props = {
    exam: {
        id: number;
        title: string;
        subject: string;
        is_locked: boolean;
        status: string;
        status_label: string;
    };
    canEdit: boolean;
    canCorrectKey: boolean;
    questions: QuestionRow[];
    questionTypeOptions: Array<{ value: string; label: string }>;
};

export default function CbtQuestionsIndex({
    exam,
    canEdit,
    canCorrectKey,
    questions,
    questionTypeOptions,
}: Props) {
    const [formOpen, setFormOpen] = useState(false);
    const [editing, setEditing] = useState<QuestionRow | null>(null);
    const [importOpen, setImportOpen] = useState(false);
    const [correctTarget, setCorrectTarget] = useState<QuestionRow | null>(
        null,
    );

    const form = useForm<{
        type: 'mcq' | 'matching' | 'essay_paper';
        body: string;
        is_rtl: boolean;
        image: File | null;
        option_a: string;
        option_b: string;
        option_c: string;
        option_d: string;
        correct_option: string;
    }>({
        type: 'mcq',
        body: '',
        is_rtl: false,
        image: null,
        option_a: '',
        option_b: '',
        option_c: '',
        option_d: '',
        correct_option: 'A',
    });

    const importForm = useForm<{ file: File | null }>({ file: null });
    const correctForm = useForm<{ correct: string; reason: string }>({
        correct: 'A',
        reason: '',
    });

    const openCreate = (): void => {
        setEditing(null);
        form.clearErrors();
        form.reset();
        setFormOpen(true);
    };

    const openEdit = (question: QuestionRow): void => {
        setEditing(question);
        form.clearErrors();
        const optMap = new Map(
            question.options.map((opt) => [opt.label, opt.body]),
        );
        form.setData({
            type: question.type,
            body: question.body,
            is_rtl: question.is_rtl,
            image: null,
            option_a: optMap.get('A') ?? '',
            option_b: optMap.get('B') ?? '',
            option_c: optMap.get('C') ?? '',
            option_d: optMap.get('D') ?? '',
            correct_option: question.answer_key?.correct ?? 'A',
        });
        setFormOpen(true);
    };

    const submitQuestion = (event: FormEvent): void => {
        event.preventDefault();

        const options =
            form.data.type === 'mcq'
                ? [
                      { label: 'A', body: form.data.option_a },
                      { label: 'B', body: form.data.option_b },
                      { label: 'C', body: form.data.option_c },
                      { label: 'D', body: form.data.option_d },
                  ].filter((opt) => opt.body.trim() !== '')
                : [];

        form.transform((data) => ({
            type: data.type,
            body: data.body,
            is_rtl: data.is_rtl,
            image: data.image,
            options,
            answer_key:
                data.type === 'mcq' ? { correct: data.correct_option } : null,
        }));

        if (editing === null) {
            form.post(CbtQuestionController.store.url(exam.id), {
                forceFormData: true,
                preserveScroll: true,
                onSuccess: () => setFormOpen(false),
            });

            return;
        }

        form.transform((data) => ({
            _method: 'put',
            type: data.type,
            body: data.body,
            is_rtl: data.is_rtl,
            image: data.image,
            options,
            answer_key:
                data.type === 'mcq' ? { correct: data.correct_option } : null,
        }));
        form.post(CbtQuestionController.update.url([exam.id, editing.id]), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                setFormOpen(false);
                setEditing(null);
            },
        });
    };

    const submitImport = (event: FormEvent): void => {
        event.preventDefault();
        importForm.post(CbtQuestionController.import.url(exam.id), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                setImportOpen(false);
                importForm.reset();
            },
        });
    };

    const submitCorrection = (event: FormEvent): void => {
        event.preventDefault();
        if (correctTarget === null) {
            return;
        }

        correctForm.transform((data) => ({
            answer_key: { correct: data.correct },
            reason: data.reason,
        }));
        correctForm.patch(
            CbtQuestionController.correctKey.url([exam.id, correctTarget.id]),
            {
                preserveScroll: true,
                onSuccess: () => {
                    setCorrectTarget(null);
                    correctForm.reset();
                },
            },
        );
    };

    return (
        <StaffLayout
            breadcrumbs={[
                { title: 'Beranda', href: AdminDashboardController.url() },
                { title: 'Daftar ujian', href: CbtExamController.index.url() },
                {
                    title: 'Bank soal',
                    href: CbtQuestionController.index.url(exam.id),
                },
            ]}
        >
            <Head title={`Bank soal — ${exam.title}`} />

            <div className="space-y-6">
                <PageHeader
                    title={`Bank soal — ${exam.title}`}
                    description={`${exam.subject} · ${questions.length} soal (${exam.status_label})`}
                    actions={
                        canEdit ? (
                            <div className="flex flex-wrap items-center gap-2">
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
                                <Button type="button" onClick={openCreate}>
                                    <Plus
                                        className="size-4"
                                        strokeWidth={1.75}
                                    />
                                    Tambah soal
                                </Button>
                            </div>
                        ) : exam.is_locked ? (
                            <StatusBadge variant="warning">
                                <Lock className="mr-1 size-3" /> Soal terkunci
                            </StatusBadge>
                        ) : undefined
                    }
                />

                {questions.length === 0 ? (
                    <EmptyState
                        title="Belum ada soal"
                        description={
                            canEdit
                                ? 'Tambahkan soal pilihan ganda, menjodohkan, atau essay kertas secara manual maupun lewat impor Excel.'
                                : 'Belum ada soal pada ujian ini.'
                        }
                        action={
                            canEdit ? (
                                <Button type="button" onClick={openCreate}>
                                    <Plus
                                        className="size-4"
                                        strokeWidth={1.75}
                                    />
                                    Tambah soal
                                </Button>
                            ) : undefined
                        }
                    />
                ) : (
                    <div className="space-y-4">
                        {questions.map((question, index) => (
                            <div
                                key={question.id}
                                className="rounded-lg border border-border bg-card p-4 shadow-xs"
                            >
                                <div className="flex items-start justify-between gap-4">
                                    <div className="space-y-2">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <span className="text-sm font-semibold tabular-nums">
                                                No. {index + 1}
                                            </span>
                                            <StatusBadge variant="muted">
                                                {question.type_label}
                                            </StatusBadge>
                                            {question.answer_key?.correct && (
                                                <StatusBadge variant="success">
                                                    Kunci:{' '}
                                                    {
                                                        question.answer_key
                                                            .correct
                                                    }
                                                </StatusBadge>
                                            )}
                                        </div>

                                        {question.is_rtl ? (
                                            <ArabicText text={question.body} />
                                        ) : (
                                            <p className="text-sm whitespace-pre-wrap">
                                                {question.body}
                                            </p>
                                        )}

                                        {question.image_url && (
                                            <img
                                                src={question.image_url}
                                                alt={`Gambar soal ${index + 1}`}
                                                className="max-h-48 rounded-md border border-border object-contain"
                                            />
                                        )}

                                        {question.options.length > 0 && (
                                            <ul className="space-y-1 pt-1 text-sm text-muted-foreground">
                                                {question.options.map((opt) => (
                                                    <li key={opt.id}>
                                                        <span className="font-medium text-foreground">
                                                            {opt.label}.
                                                        </span>{' '}
                                                        {opt.body}
                                                    </li>
                                                ))}
                                            </ul>
                                        )}
                                    </div>

                                    {(canEdit ||
                                        (exam.is_locked &&
                                            canCorrectKey &&
                                            question.type === 'mcq')) && (
                                        <RowActions
                                            itemName={`Soal ${index + 1}`}
                                            onEdit={
                                                canEdit
                                                    ? () => openEdit(question)
                                                    : undefined
                                            }
                                            onDelete={
                                                canEdit
                                                    ? () =>
                                                          router.delete(
                                                              CbtQuestionController.destroy.url(
                                                                  [
                                                                      exam.id,
                                                                      question.id,
                                                                  ],
                                                              ),
                                                              {
                                                                  preserveScroll: true,
                                                              },
                                                          )
                                                    : undefined
                                            }
                                        >
                                            {exam.is_locked &&
                                                canCorrectKey &&
                                                question.type === 'mcq' && (
                                                    <Tooltip>
                                                        <TooltipTrigger asChild>
                                                            <Button
                                                                type="button"
                                                                variant="ghost"
                                                                size="icon"
                                                                aria-label={`Koreksi kunci soal ${index + 1}`}
                                                                onClick={() => {
                                                                    setCorrectTarget(
                                                                        question,
                                                                    );
                                                                    correctForm.setData(
                                                                        {
                                                                            correct:
                                                                                question
                                                                                    .answer_key
                                                                                    ?.correct ??
                                                                                'A',
                                                                            reason: '',
                                                                        },
                                                                    );
                                                                }}
                                                            >
                                                                <KeyRound
                                                                    className="size-4"
                                                                    strokeWidth={
                                                                        1.75
                                                                    }
                                                                />
                                                            </Button>
                                                        </TooltipTrigger>
                                                        <TooltipContent>
                                                            Koreksi kunci
                                                        </TooltipContent>
                                                    </Tooltip>
                                                )}
                                        </RowActions>
                                    )}
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </div>

            <FormDialog
                open={formOpen}
                onOpenChange={setFormOpen}
                title={editing === null ? 'Tambah soal' : 'Ubah soal'}
                contentClassName="sm:max-w-2xl max-h-[88vh] overflow-y-auto"
                processing={form.processing}
                onSubmit={submitQuestion}
            >
                <div className="space-y-4">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="space-y-1.5">
                            <Label htmlFor="q-type">Jenis soal</Label>
                            <Select
                                value={form.data.type}
                                onValueChange={(value) =>
                                    form.setData(
                                        'type',
                                        value as
                                            | 'mcq'
                                            | 'matching'
                                            | 'essay_paper',
                                    )
                                }
                            >
                                <SelectTrigger id="q-type">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {questionTypeOptions.map((opt) => (
                                        <SelectItem
                                            key={opt.value}
                                            value={opt.value}
                                        >
                                            {opt.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="flex items-end pb-2">
                            <label className="flex cursor-pointer items-center gap-2 text-sm">
                                <Checkbox
                                    checked={form.data.is_rtl}
                                    onCheckedChange={(checked) =>
                                        form.setData('is_rtl', Boolean(checked))
                                    }
                                />
                                <span>Teks Arab (kanan ke kiri)</span>
                            </label>
                        </div>
                    </div>

                    <div className="space-y-1.5">
                        <Label htmlFor="q-body">Isi soal</Label>
                        <textarea
                            id="q-body"
                            rows={4}
                            dir={form.data.is_rtl ? 'rtl' : 'ltr'}
                            className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                            value={form.data.body}
                            onChange={(event) =>
                                form.setData('body', event.target.value)
                            }
                        />
                        <InputError message={form.errors.body} />
                    </div>

                    <div className="space-y-1.5">
                        <Label htmlFor="q-image">
                            Gambar opsional (PNG/JPG, maks 2 MB)
                        </Label>
                        <Input
                            id="q-image"
                            type="file"
                            accept=".jpg,.jpeg,.png,.webp"
                            onChange={(event) =>
                                form.setData(
                                    'image',
                                    event.target.files?.[0] ?? null,
                                )
                            }
                        />
                        <InputError message={form.errors.image} />
                    </div>

                    {form.data.type === 'mcq' && (
                        <div className="space-y-3 rounded-md border border-border p-3">
                            <div className="grid gap-3 sm:grid-cols-2">
                                {(['a', 'b', 'c', 'd'] as const).map((key) => (
                                    <div key={key} className="space-y-1">
                                        <Label htmlFor={`opt-${key}`}>
                                            Pilihan {key.toUpperCase()}
                                        </Label>
                                        <Input
                                            id={`opt-${key}`}
                                            value={form.data[`option_${key}`]}
                                            onChange={(event) =>
                                                form.setData(
                                                    `option_${key}`,
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    </div>
                                ))}
                            </div>

                            <div className="space-y-1">
                                <Label htmlFor="q-correct">Kunci jawaban</Label>
                                <Select
                                    value={form.data.correct_option}
                                    onValueChange={(value) =>
                                        form.setData('correct_option', value)
                                    }
                                >
                                    <SelectTrigger
                                        id="q-correct"
                                        className="w-32"
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {['A', 'B', 'C', 'D'].map((opt) => (
                                            <SelectItem key={opt} value={opt}>
                                                {opt}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>
                    )}
                </div>
            </FormDialog>

            <FormDialog
                open={importOpen}
                onOpenChange={setImportOpen}
                title="Impor soal dari Excel (.xlsx)"
                description="Kolom: type, body, is_rtl, option_a, option_b, option_c, option_d, answer_key."
                processing={importForm.processing}
                submitLabel="Impor soal"
                onSubmit={submitImport}
            >
                <div className="space-y-1.5">
                    <Label htmlFor="import-xlsx">Berkas Excel</Label>
                    <Input
                        id="import-xlsx"
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

            <FormDialog
                open={correctTarget !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setCorrectTarget(null);
                    }
                }}
                title="Koreksi kunci jawaban"
                description="Perubahan kunci setelah ujian dimulai dicatat di jejak audit."
                processing={correctForm.processing}
                submitLabel="Simpan koreksi"
                onSubmit={submitCorrection}
            >
                <div className="space-y-4">
                    <div className="space-y-1.5">
                        <Label htmlFor="correct-key">Kunci baru</Label>
                        <Select
                            value={correctForm.data.correct}
                            onValueChange={(value) =>
                                correctForm.setData('correct', value)
                            }
                        >
                            <SelectTrigger id="correct-key" className="w-32">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {['A', 'B', 'C', 'D'].map((opt) => (
                                    <SelectItem key={opt} value={opt}>
                                        {opt}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="space-y-1.5">
                        <Label htmlFor="correct-reason">Alasan koreksi</Label>
                        <Input
                            id="correct-reason"
                            value={correctForm.data.reason}
                            onChange={(event) =>
                                correctForm.setData(
                                    'reason',
                                    event.target.value,
                                )
                            }
                            placeholder="Jelaskan alasan perubahan kunci jawaban"
                        />
                        <InputError message={correctForm.errors.reason} />
                    </div>
                </div>
            </FormDialog>
        </StaffLayout>
    );
}
