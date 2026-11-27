import { Head } from '@inertiajs/react';
import { FileDown, Sparkles } from 'lucide-react';
import { DateText } from '@/components/app/date-text';
import { EmptyState } from '@/components/app/empty-state';
import { Button } from '@/components/ui/button';
import GuardianLayout from '@/layouts/guardian-layout';

type Report = {
    id: number;
    semester: string;
    classroom: string;
    approved_at: string | null;
    dimensions: {
        name: string;
        level: string | null;
        level_label: string;
        description: string | null;
    }[];
    pdf_url: string;
};

type Props = {
    student: { id: number; name: string; nickname: string | null };
    reports: Report[];
};

export default function GuardianCharacter({ student, reports }: Props) {
    const name = student.nickname ?? student.name;

    return (
        <GuardianLayout title="Penilaian karakter">
            <Head title={`Penilaian karakter — ${student.name}`} />

            <div className="space-y-4">
                <div>
                    <h1 className="text-lg font-semibold tracking-tight">
                        Penilaian karakter {name}
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Disusun wali kelas dari pembiasaan, kehadiran, dan
                        catatan perilaku, lalu disetujui kepala unit.
                    </p>
                </div>

                {reports.length === 0 ? (
                    <EmptyState
                        icon={Sparkles}
                        title="Belum ada penilaian karakter terbit"
                        description="Penilaian muncul di sini setelah wali kelas mengajukannya dan kepala unit menyetujui, biasanya di akhir semester."
                    />
                ) : (
                    reports.map((report) => (
                        <section
                            key={report.id}
                            className="overflow-hidden rounded-xl border border-border bg-card shadow-xs"
                        >
                            <div className="flex items-center justify-between gap-3 border-b border-border bg-muted/50 px-4 py-3">
                                <div className="min-w-0">
                                    <p className="text-sm font-semibold">
                                        Semester {report.semester}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        Rombel {report.classroom}
                                        {report.approved_at && (
                                            <>
                                                {' · '}disetujui{' '}
                                                <DateText
                                                    value={report.approved_at}
                                                />
                                            </>
                                        )}
                                    </p>
                                </div>
                            </div>

                            <ul className="divide-y divide-border">
                                {report.dimensions.map((dimension) => (
                                    <li
                                        key={dimension.name}
                                        className="space-y-1 px-4 py-3"
                                    >
                                        <div className="flex items-start justify-between gap-3">
                                            <p className="text-sm text-muted-foreground">
                                                {dimension.name}
                                            </p>
                                            <p className="shrink-0 text-right text-sm font-semibold text-primary">
                                                {dimension.level_label}
                                            </p>
                                        </div>
                                        {dimension.description && (
                                            <p className="text-[15px] leading-relaxed">
                                                {dimension.description}
                                            </p>
                                        )}
                                    </li>
                                ))}
                            </ul>

                            <div className="border-t border-border p-3">
                                <Button
                                    asChild
                                    variant="outline"
                                    className="min-h-11 w-full"
                                >
                                    <a
                                        href={report.pdf_url}
                                        target="_blank"
                                        rel="noopener"
                                    >
                                        <FileDown
                                            className="size-4"
                                            strokeWidth={1.75}
                                        />
                                        Unduh PDF
                                    </a>
                                </Button>
                            </div>
                        </section>
                    ))
                )}
            </div>
        </GuardianLayout>
    );
}
