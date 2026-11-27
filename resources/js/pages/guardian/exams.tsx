import { Head } from '@inertiajs/react';
import { Award } from 'lucide-react';
import { DateText } from '@/components/app/date-text';
import { EmptyState } from '@/components/app/empty-state';
import GuardianLayout from '@/layouts/guardian-layout';

type ExamResultItem = {
    id: number;
    exam_title: string;
    subject: string;
    score: number;
    computed_at: string;
};

type Props = {
    student: { id: number; name: string; nis: string };
    results: ExamResultItem[];
};

export default function GuardianExams({ student, results }: Props) {
    return (
        <GuardianLayout title="Hasil Ujian">
            <Head title={`Hasil Ujian — ${student.name}`} />

            <div className="space-y-4">
                <div>
                    <h1 className="text-lg font-semibold tracking-tight">
                        Hasil ujian {student.name}
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Menampilkan nilai ujian akhir yang sudah diterbitkan
                        sekolah.
                    </p>
                </div>

                {results.length === 0 ? (
                    <EmptyState
                        icon={Award}
                        title="Belum ada hasil ujian diterbitkan"
                        description="Nilai ujian akan muncul di sini setelah penilaian selesai dan diizinkan tampil oleh sekolah."
                    />
                ) : (
                    <div className="space-y-3">
                        {results.map((item) => (
                            <div
                                key={item.id}
                                className="flex items-center justify-between gap-4 rounded-lg border border-border bg-card p-4 shadow-xs"
                            >
                                <div className="min-w-0">
                                    <p className="truncate font-medium">
                                        {item.exam_title}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        {item.subject} ·{' '}
                                        <DateText value={item.computed_at} />
                                    </p>
                                </div>
                                <span className="text-xl font-semibold text-primary tabular-nums">
                                    {item.score}
                                </span>
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </GuardianLayout>
    );
}
