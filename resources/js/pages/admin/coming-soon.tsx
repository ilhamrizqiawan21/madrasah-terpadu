import { Head, Link } from '@inertiajs/react';
import { Hourglass } from 'lucide-react';
import { Button } from '@/components/ui/button';
import StaffLayout from '@/layouts/staff-layout';
import { dashboard } from '@/routes/admin';

type Props = {
    module: {
        label: string;
        group: string;
        description: string;
        plan: string;
    };
};

/** Tujuan menu bertanda "Segera" (ADR-028): menyebut apa yang akan ada dan kapan direncanakan. */
export default function ComingSoon({ module }: Props) {
    return (
        <StaffLayout
            breadcrumbs={[
                { title: module.group, href: dashboard() },
                { title: module.label, href: '' },
            ]}
        >
            <Head title={module.label} />

            <section className="mx-auto mt-6 max-w-xl overflow-hidden rounded-xl border border-border bg-card shadow-xs">
                <div className="bg-islamic-pattern-subtle flex items-center gap-4 border-b border-border bg-muted/50 px-6 py-5">
                    <span className="flex size-11 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                        <Hourglass className="size-5" strokeWidth={1.75} />
                    </span>
                    <div className="min-w-0">
                        <p className="text-xs text-muted-foreground">
                            {module.group}
                        </p>
                        <h1 className="text-lg font-semibold">
                            {module.label}
                        </h1>
                    </div>
                </div>
                <div className="space-y-4 px-6 py-5">
                    <p className="text-sm font-medium">
                        Modul ini sedang disiapkan dan belum bisa dipakai.
                    </p>
                    <p className="text-sm text-muted-foreground">
                        {module.description}
                    </p>
                    <dl className="rounded-lg border border-border bg-background/60 px-4 py-3 text-sm">
                        <dt className="text-xs text-muted-foreground">
                            Rencana
                        </dt>
                        <dd className="font-medium">{module.plan}</dd>
                    </dl>
                    <Button asChild variant="outline">
                        <Link href={dashboard()}>Kembali ke Beranda</Link>
                    </Button>
                </div>
            </section>
        </StaffLayout>
    );
}
