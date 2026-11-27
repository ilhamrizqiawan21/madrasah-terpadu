import { Head, router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, Flag, Send } from 'lucide-react';
import { useEffect, useState } from 'react';
import ExamSessionController from '@/actions/App/Http/Controllers/Exam/ExamSessionController';
import { ArabicText } from '@/components/app/arabic-text';
import { ConfirmDialog } from '@/components/app/confirm-dialog';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

type QuestionItem = {
    id: number;
    type: 'mcq' | 'matching' | 'essay_paper';
    body: string;
    is_rtl: boolean;
    image_url: string | null;
    options: Array<{ id: number; label: string; body: string }>;
};

type SavedAnswer = {
    answer: { selected?: string } | null;
    is_flagged: boolean;
};

type Props = {
    student: { id: number; nis: string; name: string };
    exam: {
        id: number;
        title: string;
        subject: string;
        violation_limit: number;
    };
    session: {
        id: number;
        device_type: string;
        device_token: string;
        remaining_seconds: number;
    };
    questions: QuestionItem[];
    savedAnswers: Record<number, SavedAnswer>;
};

function formatTimer(totalSeconds: number): string {
    const clamped = Math.max(0, totalSeconds);
    const hours = Math.floor(clamped / 3600);
    const minutes = Math.floor((clamped % 3600) / 60);
    const seconds = clamped % 60;
    const pad = (n: number): string => String(n).padStart(2, '0');

    return hours > 0
        ? `${pad(hours)}:${pad(minutes)}:${pad(seconds)}`
        : `${pad(minutes)}:${pad(seconds)}`;
}

export default function ExamSessionPage({
    student,
    exam,
    session,
    questions,
    savedAnswers,
}: Props) {
    const storageKey = `cbt.answers.${session.id}`;

    const [currentIndex, setCurrentIndex] = useState(0);
    const [remainingSeconds, setRemainingSeconds] = useState(
        session.remaining_seconds,
    );
    const [answers, setAnswers] = useState<Record<number, SavedAnswer>>(() => {
        if (typeof window !== 'undefined') {
            try {
                const raw = window.localStorage.getItem(storageKey);
                if (raw) {
                    return {
                        ...savedAnswers,
                        ...(JSON.parse(raw) as Record<number, SavedAnswer>),
                    };
                }
            } catch {
                // Abaikan cache lokal yang rusak.
            }
        }

        return savedAnswers;
    });
    const [syncState, setSyncState] = useState<'saved' | 'syncing' | 'offline'>(
        'saved',
    );
    const [takeoverMessage, setTakeoverMessage] = useState<string | null>(null);
    const [lockedMessage, setLockedMessage] = useState<string | null>(null);
    const [needsFullscreen, setNeedsFullscreen] = useState<boolean>(
        session.device_type === 'lab' &&
            typeof document !== 'undefined' &&
            document.fullscreenEnabled &&
            !document.fullscreenElement,
    );
    const [submitOpen, setSubmitOpen] = useState(false);
    const [submitting, setSubmitting] = useState(false);

    useEffect(() => {
        const interval = window.setInterval(() => {
            setRemainingSeconds((prev) => Math.max(0, prev - 1));
        }, 1000);

        return () => window.clearInterval(interval);
    }, []);

    useEffect(() => {
        const sendViolation = async (
            kind: 'tab_hidden' | 'fullscreen_exit' | 'copy_paste',
        ): Promise<void> => {
            try {
                const csrf =
                    document
                        .querySelector('meta[name="csrf-token"]')
                        ?.getAttribute('content') ?? '';
                const res = await fetch(
                    ExamSessionController.recordViolation.url(exam.id),
                    {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            Accept: 'application/json',
                            'X-CSRF-TOKEN': csrf,
                        },
                        body: JSON.stringify({
                            device_token: session.device_token,
                            kind,
                        }),
                    },
                );

                if (res.status === 409) {
                    setTakeoverMessage(
                        'Sesi ujian ini dibuka dari perangkat lain.',
                    );

                    return;
                }

                if (res.ok) {
                    const data = (await res.json()) as { locked?: boolean };
                    if (data.locked) {
                        setLockedMessage(
                            'Sesi ujian Anda terkunci karena melebihi batas pelanggaran. Hubungi pengawas.',
                        );
                    }
                }
            } catch {
                // Abaikan gagal jaringan sesaat saat mencatat pelanggaran.
            }
        };

        const onVisibility = (): void => {
            if (document.hidden) {
                void sendViolation('tab_hidden');
            }
        };

        const onFullscreen = (): void => {
            if (session.device_type === 'lab' && !document.fullscreenElement) {
                setNeedsFullscreen(true);
                void sendViolation('fullscreen_exit');
            } else {
                setNeedsFullscreen(false);
            }
        };

        const onCopyPaste = (event: ClipboardEvent): void => {
            event.preventDefault();
            void sendViolation('copy_paste');
        };

        document.addEventListener('visibilitychange', onVisibility);
        document.addEventListener('fullscreenchange', onFullscreen);
        document.addEventListener('copy', onCopyPaste);
        document.addEventListener('paste', onCopyPaste);

        return () => {
            document.removeEventListener('visibilitychange', onVisibility);
            document.removeEventListener('fullscreenchange', onFullscreen);
            document.removeEventListener('copy', onCopyPaste);
            document.removeEventListener('paste', onCopyPaste);
        };
    }, [exam.id, session.device_token, session.device_type]);

    const enterFullscreen = async (): Promise<void> => {
        try {
            await document.documentElement.requestFullscreen();
            setNeedsFullscreen(false);
        } catch {
            // Browser menolak layar penuh otomatis.
        }
    };

    const syncBatch = async (
        nextAnswers: Record<number, SavedAnswer>,
        questionId: number,
    ): Promise<void> => {
        const entry = nextAnswers[questionId];
        if (!entry) {
            return;
        }

        setSyncState('syncing');

        try {
            const csrf =
                document
                    .querySelector('meta[name="csrf-token"]')
                    ?.getAttribute('content') ?? '';
            const res = await fetch(
                ExamSessionController.syncAnswers.url(exam.id),
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': csrf,
                    },
                    body: JSON.stringify({
                        device_token: session.device_token,
                        answers: [
                            {
                                exam_question_id: questionId,
                                answer: entry.answer,
                                is_flagged: entry.is_flagged,
                                client_ts: new Date().toISOString(),
                            },
                        ],
                    }),
                },
            );

            if (res.status === 409) {
                setTakeoverMessage(
                    'Sesi ujian ini dibuka dari perangkat lain.',
                );
                setSyncState('offline');

                return;
            }

            setSyncState(res.ok ? 'saved' : 'offline');
        } catch {
            setSyncState('offline');
        }
    };

    const updateAnswer = (
        questionId: number,
        patch: Partial<SavedAnswer>,
    ): void => {
        const current = answers[questionId] ?? {
            answer: null,
            is_flagged: false,
        };
        const updated: Record<number, SavedAnswer> = {
            ...answers,
            [questionId]: { ...current, ...patch },
        };
        setAnswers(updated);

        if (typeof window !== 'undefined') {
            window.localStorage.setItem(storageKey, JSON.stringify(updated));
        }

        void syncBatch(updated, questionId);
    };

    const submitExam = (): void => {
        setSubmitting(true);
        router.post(
            ExamSessionController.submit.url(exam.id),
            { device_token: session.device_token },
            {
                onFinish: () => setSubmitting(false),
                onSuccess: () => {
                    if (typeof window !== 'undefined') {
                        window.localStorage.removeItem(storageKey);
                    }
                },
            },
        );
    };

    const question = questions[currentIndex];
    const currentAnswer = question ? answers[question.id] : undefined;
    const isLastFiveMinutes = remainingSeconds <= 300;

    return (
        <div className="min-h-screen bg-background text-foreground">
            <Head title={`${exam.title} — Ujian`} />

            <header className="sticky top-0 z-10 border-b border-border bg-card">
                <div className="mx-auto flex max-w-4xl items-center justify-between gap-3 px-4 py-3">
                    <div className="min-w-0">
                        <p className="truncate text-sm font-semibold">
                            {exam.title}
                        </p>
                        <p className="truncate text-xs text-muted-foreground">
                            {student.name} · {exam.subject}
                        </p>
                    </div>

                    <div className="flex items-center gap-3">
                        <span className="hidden text-xs text-muted-foreground sm:inline">
                            {syncState === 'saved'
                                ? 'Tersimpan'
                                : syncState === 'syncing'
                                  ? 'Menyimpan...'
                                  : 'Menyimpan di perangkat'}
                        </span>
                        <span
                            className={cn(
                                'rounded-md border px-2.5 py-1 font-mono text-sm font-semibold tabular-nums',
                                isLastFiveMinutes
                                    ? 'border-warning/40 bg-warning/15 text-warning-foreground'
                                    : 'border-border bg-muted text-foreground',
                            )}
                        >
                            {formatTimer(remainingSeconds)}
                        </span>
                    </div>
                </div>
            </header>

            <main className="mx-auto max-w-4xl space-y-6 px-4 py-6">
                {takeoverMessage && (
                    <div className="rounded-md border border-destructive/30 bg-destructive/10 p-4 text-sm text-destructive">
                        {takeoverMessage}
                    </div>
                )}

                {lockedMessage && (
                    <div className="rounded-md border border-destructive/30 bg-destructive/10 p-4 text-sm text-destructive">
                        {lockedMessage}
                    </div>
                )}

                {needsFullscreen && !lockedMessage && (
                    <div className="flex flex-wrap items-center justify-between gap-3 rounded-md border border-warning/40 bg-warning/15 p-4 text-sm">
                        <span>
                            Mode lab mewajibkan layar penuh selama ujian
                            berlangsung.
                        </span>
                        <Button
                            type="button"
                            size="sm"
                            onClick={() => void enterFullscreen()}
                        >
                            Masuk layar penuh
                        </Button>
                    </div>
                )}

                {question && (
                    <div className="space-y-6 rounded-lg border border-border bg-card p-5 shadow-xs">
                        <div className="flex items-center justify-between gap-2">
                            <span className="text-sm font-semibold tabular-nums">
                                Soal {currentIndex + 1} dari {questions.length}
                            </span>

                            <Button
                                type="button"
                                variant={
                                    currentAnswer?.is_flagged
                                        ? 'secondary'
                                        : 'outline'
                                }
                                size="sm"
                                className="h-9"
                                onClick={() =>
                                    updateAnswer(question.id, {
                                        is_flagged: !currentAnswer?.is_flagged,
                                    })
                                }
                            >
                                <Flag className="size-4" strokeWidth={1.75} />
                                {currentAnswer?.is_flagged
                                    ? 'Ditandai ragu'
                                    : 'Ragu-ragu'}
                            </Button>
                        </div>

                        {question.is_rtl ? (
                            <ArabicText text={question.body} showControls />
                        ) : (
                            <p className="text-base leading-relaxed whitespace-pre-wrap">
                                {question.body}
                            </p>
                        )}

                        {question.image_url && (
                            <img
                                src={question.image_url}
                                alt={`Gambar soal ${currentIndex + 1}`}
                                className="max-h-72 rounded-md border border-border object-contain"
                            />
                        )}

                        {question.type === 'mcq' && (
                            <div className="space-y-2.5">
                                {question.options.map((opt) => {
                                    const selected =
                                        currentAnswer?.answer?.selected ===
                                        opt.label;

                                    return (
                                        <button
                                            key={opt.id}
                                            type="button"
                                            onClick={() =>
                                                updateAnswer(question.id, {
                                                    answer: {
                                                        selected: opt.label,
                                                    },
                                                })
                                            }
                                            className={cn(
                                                'flex min-h-12 w-full items-center gap-3 rounded-md border px-4 py-3 text-left text-sm transition-colors',
                                                selected
                                                    ? 'border-primary bg-primary/10 font-medium text-foreground ring-1 ring-primary'
                                                    : 'border-border bg-background hover:bg-muted',
                                            )}
                                        >
                                            <span className="flex size-7 shrink-0 items-center justify-center rounded-md border border-border bg-card text-xs font-semibold">
                                                {opt.label}
                                            </span>
                                            <span>{opt.body}</span>
                                        </button>
                                    );
                                })}
                            </div>
                        )}

                        {question.type === 'essay_paper' && (
                            <div className="rounded-md border border-border bg-muted/40 p-4 text-sm text-muted-foreground">
                                Kerjakan soal essay ini pada lembar jawaban
                                kertas yang dibagikan pengawas.
                            </div>
                        )}
                    </div>
                )}

                <div className="flex flex-wrap items-center justify-between gap-3">
                    <Button
                        type="button"
                        variant="outline"
                        className="h-11"
                        disabled={currentIndex === 0}
                        onClick={() =>
                            setCurrentIndex((prev) => Math.max(0, prev - 1))
                        }
                    >
                        <ChevronLeft className="size-4" strokeWidth={1.75} />
                        Sebelumnya
                    </Button>

                    <div className="flex items-center gap-2">
                        {currentIndex < questions.length - 1 ? (
                            <Button
                                type="button"
                                className="h-11"
                                onClick={() =>
                                    setCurrentIndex((prev) =>
                                        Math.min(
                                            questions.length - 1,
                                            prev + 1,
                                        ),
                                    )
                                }
                            >
                                Berikutnya
                                <ChevronRight
                                    className="size-4"
                                    strokeWidth={1.75}
                                />
                            </Button>
                        ) : (
                            <Button
                                type="button"
                                className="h-11"
                                onClick={() => setSubmitOpen(true)}
                            >
                                <Send className="size-4" strokeWidth={1.75} />
                                Selesai ujian
                            </Button>
                        )}
                    </div>
                </div>

                <div className="rounded-lg border border-border bg-card p-4">
                    <p className="mb-3 text-xs font-medium text-muted-foreground">
                        Nomor soal
                    </p>
                    <div className="flex flex-wrap gap-2">
                        {questions.map((q, idx) => {
                            const ans = answers[q.id];
                            const isAnswered = Boolean(ans?.answer?.selected);
                            const isFlagged = Boolean(ans?.is_flagged);
                            const isCurrent = idx === currentIndex;

                            return (
                                <button
                                    key={q.id}
                                    type="button"
                                    onClick={() => setCurrentIndex(idx)}
                                    aria-label={`Soal nomor ${idx + 1}${isFlagged ? ', ragu-ragu' : isAnswered ? ', sudah dijawab' : ', belum dijawab'}`}
                                    className={cn(
                                        'flex size-10 items-center justify-center rounded-md border text-sm font-medium tabular-nums',
                                        isCurrent && 'ring-2 ring-primary',
                                        isFlagged
                                            ? 'border-warning bg-warning/20 text-foreground'
                                            : isAnswered
                                              ? 'border-primary bg-primary text-primary-foreground'
                                              : 'border-border bg-background text-foreground',
                                    )}
                                >
                                    {idx + 1}
                                </button>
                            );
                        })}
                    </div>
                </div>
            </main>

            <ConfirmDialog
                open={submitOpen}
                onOpenChange={setSubmitOpen}
                title="Kumpulkan jawaban ujian?"
                description="Setelah dikumpulkan, Anda tidak dapat mengubah jawaban lagi."
                confirmLabel="Ya, kumpulkan"
                variant="default"
                loading={submitting}
                onConfirm={submitExam}
            />
        </div>
    );
}
