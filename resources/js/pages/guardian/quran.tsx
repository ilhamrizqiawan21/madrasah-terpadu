import { Head } from '@inertiajs/react';
import { BookOpen } from 'lucide-react';
import { useState } from 'react';
import { DateText } from '@/components/app/date-text';
import { EmptyState } from '@/components/app/empty-state';
import { StatusBadge } from '@/components/app/status-badge';
import { Button } from '@/components/ui/button';
import GuardianLayout from '@/layouts/guardian-layout';

type ProgressSummary = {
    quran: {
        achieved: number;
        target: number;
        pct: number;
        total_memorized_ayahs: number;
    };
    hadith: { achieved: number; target: number; pct: number };
    prayer: { achieved: number; target: number; pct: number };
};

type RecordItem = {
    id: number;
    kind: 'quran' | 'hadith' | 'prayer';
    type: string | null;
    summary: string;
    final_score: number;
    predicate: string;
    passed: boolean;
    note: string | null;
    recorded_on: string;
};

type Props = {
    student: { id: number; name: string; nis: string };
    progress: ProgressSummary;
    records: RecordItem[];
};

const TABS: { key: 'quran' | 'hadith' | 'prayer'; label: string }[] = [
    { key: 'quran', label: "Al-Qur'an" },
    { key: 'hadith', label: 'Hadits' },
    { key: 'prayer', label: "Do'a" },
];

export default function GuardianQuran({ student, progress, records }: Props) {
    const [tab, setTab] = useState<'quran' | 'hadith' | 'prayer'>('quran');

    const currentProgress = progress[tab];
    const filteredRecords = records.filter((r) => r.kind === tab);

    return (
        <GuardianLayout title="Hafalan">
            <Head title={`Hafalan — ${student.name}`} />

            <div className="space-y-5">
                <div>
                    <h1 className="text-lg font-semibold tracking-tight">
                        Hafalan {student.name}
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Progres target hafalan dan riwayat setoran ananda.
                    </p>
                </div>

                <div className="grid grid-cols-3 gap-2">
                    {TABS.map((t) => (
                        <Button
                            key={t.key}
                            type="button"
                            variant={tab === t.key ? 'default' : 'outline'}
                            className="min-h-11"
                            onClick={() => setTab(t.key)}
                        >
                            {t.label}
                        </Button>
                    ))}
                </div>

                <div className="rounded-lg border border-border bg-card p-4 shadow-xs">
                    <div className="flex items-baseline justify-between gap-3">
                        <div>
                            <p className="text-xs font-medium text-muted-foreground">
                                Progres target{' '}
                                {TABS.find((t) => t.key === tab)?.label}
                            </p>
                            <p className="mt-1 text-2xl font-semibold text-primary tabular-nums">
                                {currentProgress.pct}%
                            </p>
                        </div>
                        <p className="text-sm text-muted-foreground tabular-nums">
                            {currentProgress.achieved} /{' '}
                            {currentProgress.target}{' '}
                            {tab === 'quran' ? 'ayat' : 'materi'}
                        </p>
                    </div>
                    <div className="mt-3 h-2 w-full overflow-hidden rounded-full bg-muted">
                        <div
                            className="h-full bg-primary transition-all"
                            style={{
                                width: `${Math.min(100, currentProgress.pct)}%`,
                            }}
                        />
                    </div>
                    {tab === 'quran' ? (
                        <p className="mt-2 text-xs text-muted-foreground">
                            Total ayat lulus ziyadah:{' '}
                            {progress.quran.total_memorized_ayahs} ayat
                        </p>
                    ) : null}
                </div>

                <section className="space-y-2.5">
                    <h2 className="text-sm font-semibold text-foreground">
                        Riwayat setoran
                    </h2>

                    {filteredRecords.length === 0 ? (
                        <EmptyState
                            icon={BookOpen}
                            title="Belum ada riwayat setoran"
                            description="Setoran hafalan yang dicatat guru akan muncul di sini."
                        />
                    ) : (
                        <div className="space-y-2">
                            {filteredRecords.map((rec) => (
                                <div
                                    key={rec.id}
                                    className="flex items-start justify-between gap-3 rounded-lg border border-border bg-card p-3.5 shadow-xs"
                                >
                                    <div className="min-w-0 space-y-1">
                                        <p className="font-medium text-foreground">
                                            {rec.summary}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            <DateText value={rec.recorded_on} />
                                            {rec.type ? ` · ${rec.type}` : ''}
                                            {rec.note ? ` · ${rec.note}` : ''}
                                        </p>
                                    </div>
                                    <div className="flex shrink-0 flex-col items-end gap-1">
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
        </GuardianLayout>
    );
}
