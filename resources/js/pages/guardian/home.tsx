import { Head, Link } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import { EmptyState } from '@/components/app/empty-state';
import GuardianLayout from '@/layouts/guardian-layout';
import { cn } from '@/lib/utils';

type HomeCard = {
    key: string;
    priority: number;
    title: string;
    summary: string;
    tone: 'default' | 'attention' | 'success' | 'warning';
    href: string | null;
};

type Props = {
    home: {
        student: {
            id: number;
            nis: string;
            name: string;
            nickname: string | null;
            unit: string;
            classroom: string | null;
            status_label: string;
            photo_url: string | null;
        };
        cards: HomeCard[];
    } | null;
};

export default function GuardianHome({ home }: Props) {
    const child = home?.student ?? null;
    const cards = home?.cards ?? [];

    return (
        <GuardianLayout>
            <Head title="Beranda" />

            {child && (
                <div className="space-y-4">
                    <section className="bg-islamic-pattern rounded-lg bg-primary p-5 text-primary-foreground shadow-xs">
                        <div className="flex items-center gap-3.5">
                            {child.photo_url ? (
                                <img
                                    src={child.photo_url}
                                    alt={`Foto ${child.name}`}
                                    className="size-12 shrink-0 rounded-full border-2 border-primary-foreground/30 object-cover"
                                />
                            ) : (
                                <div className="flex size-12 shrink-0 items-center justify-center rounded-full border border-primary-foreground/25 bg-primary-foreground/15 text-base font-semibold">
                                    {child.name.slice(0, 1).toUpperCase()}
                                </div>
                            )}
                            <div className="min-w-0 flex-1">
                                <div className="text-xs text-primary-foreground/80">
                                    {child.unit}
                                    {child.classroom
                                        ? ` · ${child.classroom}`
                                        : ''}{' '}
                                    · NIS {child.nis}
                                </div>
                                <div className="truncate text-lg font-semibold">
                                    {child.name}
                                </div>
                            </div>
                        </div>
                    </section>

                    {cards.length === 0 ? (
                        <EmptyState
                            title="Belum ada pembaruan hari ini"
                            description="Informasi presensi, perilaku, dan hafalan anak akan muncul di sini saat tercatat oleh sekolah."
                        />
                    ) : (
                        <div className="space-y-3">
                            {cards.map((card) => {
                                const content = (
                                    <div
                                        className={cn(
                                            'flex items-center justify-between gap-3 rounded-lg border bg-card p-4 shadow-xs transition-colors',
                                            card.tone === 'attention'
                                                ? 'border-accent-attention'
                                                : 'border-border hover:border-primary/40',
                                        )}
                                    >
                                        <div className="min-w-0 space-y-0.5">
                                            <h2 className="text-sm font-semibold text-foreground">
                                                {card.title}
                                            </h2>
                                            <p className="text-sm text-muted-foreground">
                                                {card.summary}
                                            </p>
                                        </div>
                                        {card.href && (
                                            <ChevronRight className="size-5 shrink-0 text-muted-foreground" />
                                        )}
                                    </div>
                                );

                                return card.href ? (
                                    <Link
                                        key={card.key}
                                        href={card.href}
                                        className="block min-h-11"
                                    >
                                        {content}
                                    </Link>
                                ) : (
                                    <div key={card.key}>{content}</div>
                                );
                            })}
                        </div>
                    )}
                </div>
            )}
        </GuardianLayout>
    );
}
