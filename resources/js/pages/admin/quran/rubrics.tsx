import { Head, router } from '@inertiajs/react';
import { CheckCircle2, Lock } from 'lucide-react';
import type { ReactNode } from 'react';
import RubricController from '@/actions/App/Http/Controllers/Admin/RubricController';
import { DateText } from '@/components/app/date-text';
import { EmptyState } from '@/components/app/empty-state';
import { PageHeader } from '@/components/app/page-header';
import { StatusBadge } from '@/components/app/status-badge';
import { Button } from '@/components/ui/button';
import StaffLayout from '@/layouts/staff-layout';

type CriterionItem = {
    id: number;
    name: string;
    mode: 'direct' | 'deduction';
    weight: number;
    max_score: number;
    deduction_per_error: number;
};

type VersionItem = {
    id: number;
    rubric_id: number;
    rubric_name: string;
    version_number: number;
    pass_threshold: number;
    published_at: string | null;
    criteria: CriterionItem[];
};

type Props = {
    versions: VersionItem[];
};

export default function RubricsIndex({ versions }: Props) {
    function publishVersion(versionId: number) {
        router.post(
            RubricController.publish.url(versionId),
            {},
            { preserveScroll: true },
        );
    }

    return (
        <>
            <Head title="Rubrik hafalan" />
            <div className="space-y-6">
                <PageHeader
                    title="Rubrik penilaian hafalan"
                    description="Versi rubrik yang sudah diterbitkan bersifat tetap agar riwayat nilai konsisten."
                />

                {versions.length === 0 ? (
                    <EmptyState
                        title="Belum ada rubrik hafalan"
                        description="Jalankan seeder rubrik bawaan atau buat rubrik baru untuk memulai."
                    />
                ) : (
                    <div className="space-y-4">
                        {versions.map((v) => (
                            <div
                                key={v.id}
                                className="rounded-lg border border-border bg-card p-4 shadow-xs"
                            >
                                <div className="flex flex-wrap items-center justify-between gap-3 border-b border-border pb-3">
                                    <div>
                                        <div className="flex items-center gap-2">
                                            <h2 className="font-semibold text-foreground">
                                                {v.rubric_name} — Versi{' '}
                                                {v.version_number}
                                            </h2>
                                            <StatusBadge
                                                variant={
                                                    v.published_at
                                                        ? 'success'
                                                        : 'attention'
                                                }
                                            >
                                                {v.published_at ? (
                                                    <>
                                                        <Lock className="mr-1 h-3 w-3" />
                                                        Terbit
                                                    </>
                                                ) : (
                                                    'Draf'
                                                )}
                                            </StatusBadge>
                                        </div>
                                        <p className="text-xs text-muted-foreground">
                                            Ambang lulus: {v.pass_threshold}
                                            {v.published_at ? (
                                                <>
                                                    {' '}
                                                    · Diterbitkan{' '}
                                                    <DateText
                                                        value={v.published_at}
                                                    />
                                                </>
                                            ) : null}
                                        </p>
                                    </div>

                                    {!v.published_at ? (
                                        <Button
                                            type="button"
                                            size="sm"
                                            onClick={() => publishVersion(v.id)}
                                        >
                                            <CheckCircle2 className="mr-1.5 h-4 w-4" />
                                            Terbitkan versi
                                        </Button>
                                    ) : null}
                                </div>

                                <div className="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-3">
                                    {v.criteria.map((c) => (
                                        <div
                                            key={c.id}
                                            className="rounded-md border border-border bg-muted/30 p-3 text-sm"
                                        >
                                            <div className="font-medium text-foreground">
                                                {c.name}
                                            </div>
                                            <div className="mt-1 text-xs text-muted-foreground">
                                                Bobot {c.weight}% ·{' '}
                                                {c.mode === 'deduction'
                                                    ? `Potongan ${c.deduction_per_error}/salah`
                                                    : `Nilai langsung (maks ${c.max_score})`}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}

RubricsIndex.layout = (page: ReactNode) => (
    <StaffLayout
        breadcrumbs={[
            { title: 'Ibadah', href: '#' },
            { title: 'Rubrik hafalan', href: RubricController.index.url() },
        ]}
    >
        {page}
    </StaffLayout>
);
