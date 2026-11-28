import { Head } from '@inertiajs/react';
import { Paperclip } from 'lucide-react';
import type { ReactNode } from 'react';
import AssignmentController from '@/actions/App/Http/Controllers/Guardian/AssignmentController';
import { DateText } from '@/components/app/date-text';
import { Button } from '@/components/ui/button';
import GuardianLayout from '@/layouts/guardian-layout';
import { formatTime } from '@/lib/format';

type AssignmentRow = {
    id: number;
    subject_name: string;
    teacher_name: string;
    title: string;
    description: string | null;
    due_at: string;
    has_attachment: boolean;
};

type Props = {
    student: { id: number; name: string; classroom: string | null };
    assignments: AssignmentRow[];
};

export default function GuardianAssignments({ student, assignments }: Props) {
    return (
        <GuardianLayout>
            <Head title={`Tugas & PR — ${student.name}`} />

            <div className="space-y-4">
                <section className="rounded-xl border border-border bg-card p-4 shadow-xs">
                    <h1 className="text-base font-semibold">
                        Tugas &amp; PR · {student.name}
                    </h1>
                    <p className="text-xs text-muted-foreground">
                        {student.classroom
                            ? `Rombel ${student.classroom}`
                            : 'Belum terdaftar di rombel aktif'}
                    </p>
                </section>

                {assignments.length === 0 ? (
                    <section className="rounded-xl border border-border bg-card p-6 text-center text-sm text-muted-foreground">
                        Belum ada tugas atau PR untuk rombel anak Anda.
                    </section>
                ) : (
                    <ul className="space-y-3">
                        {assignments.map((item) => (
                            <li
                                key={item.id}
                                className="rounded-xl border border-border bg-card p-4 shadow-xs"
                            >
                                <div className="flex items-start justify-between gap-2">
                                    <div>
                                        <p className="text-xs font-medium text-primary">
                                            {item.subject_name}
                                        </p>
                                        <h2 className="text-sm font-semibold">
                                            {item.title}
                                        </h2>
                                    </div>
                                    <span className="shrink-0 text-xs text-muted-foreground">
                                        Tenggat <DateText value={item.due_at} />{' '}
                                        {formatTime(item.due_at)}
                                    </span>
                                </div>
                                {item.description && (
                                    <p className="mt-2 text-sm text-muted-foreground">
                                        {item.description}
                                    </p>
                                )}
                                <div className="mt-3 flex items-center justify-between border-t border-border pt-2 text-xs text-muted-foreground">
                                    <span>Guru: {item.teacher_name}</span>
                                    {item.has_attachment && (
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            className="min-h-10"
                                            asChild
                                        >
                                            <a
                                                href={AssignmentController.attachment.url(
                                                    {
                                                        student: student.id,
                                                        assignment: item.id,
                                                    },
                                                )}
                                            >
                                                <Paperclip className="size-3.5" />
                                                Unduh lampiran
                                            </a>
                                        </Button>
                                    )}
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </GuardianLayout>
    );
}

GuardianAssignments.layout = (page: ReactNode) => page;
