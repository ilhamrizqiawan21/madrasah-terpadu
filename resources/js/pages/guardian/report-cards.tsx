import { Head } from '@inertiajs/react';
import { Download, FileText } from 'lucide-react';
import type { ReactNode } from 'react';
import { DateText } from '@/components/app/date-text';
import { EmptyState } from '@/components/app/empty-state';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import GuardianLayout from '@/layouts/guardian-layout';

type GuardianReportCard = {
    id: number;
    academic_year_name: string;
    semester: string;
    semester_label: string;
    published_at: string | null;
    download_url: string;
};

type Props = {
    student: { id: number; name: string };
    reportCards: GuardianReportCard[];
};

export default function GuardianReportCards({ student, reportCards }: Props) {
    return (
        <GuardianLayout title="E-Rapor">
            <Head title={`E-Rapor — ${student.name}`} />

            <div className="space-y-4">
                <div>
                    <h1 className="text-lg font-semibold tracking-tight">
                        E-Rapor
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Rapor resmi {student.name} yang telah diterbitkan oleh
                        madrasah.
                    </p>
                </div>

                {reportCards.length === 0 ? (
                    <EmptyState
                        icon={FileText}
                        title="Belum ada e-rapor terbit"
                        description="Rapor akan tampil di sini setelah diterbitkan oleh wali kelas."
                    />
                ) : (
                    <div className="space-y-3">
                        {reportCards.map((card) => (
                            <div
                                key={card.id}
                                className="flex items-center justify-between gap-3 rounded-lg border bg-card p-4"
                            >
                                <div className="space-y-1">
                                    <div className="flex items-center gap-2">
                                        <span className="font-medium">
                                            Semester {card.semester_label}
                                        </span>
                                        <Badge variant="secondary">
                                            {card.academic_year_name}
                                        </Badge>
                                    </div>
                                    {card.published_at && (
                                        <p className="text-xs text-muted-foreground">
                                            Terbit:{' '}
                                            <DateText
                                                value={card.published_at}
                                            />
                                        </p>
                                    )}
                                </div>
                                <Button asChild size="sm">
                                    <a href={card.download_url}>
                                        <Download className="mr-1.5 h-4 w-4" />
                                        Unduh PDF
                                    </a>
                                </Button>
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </GuardianLayout>
    );
}

GuardianReportCards.layout = (page: ReactNode) => page;
