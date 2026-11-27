import { Head, Link } from '@inertiajs/react';
import { ChevronRight, Download, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import { EmptyState } from '@/components/app/empty-state';
import { Button } from '@/components/ui/button';
import GuardianLayout from '@/layouts/guardian-layout';
import type { BeforeInstallPromptEvent } from '@/lib/pwa';
import {
    dismissInstallBanner,
    isInstallBannerDismissed,
    isIosSafari,
    isStandaloneMode,
} from '@/lib/pwa';
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

    const [installPrompt, setInstallPrompt] =
        useState<BeforeInstallPromptEvent | null>(null);
    const [showIosHint, setShowIosHint] = useState(false);

    useEffect(() => {
        if (isStandaloneMode() || isInstallBannerDismissed()) {
            return;
        }

        if (isIosSafari()) {
            setShowIosHint(true);

            return;
        }

        const handler = (event: Event): void => {
            event.preventDefault();
            setInstallPrompt(event as BeforeInstallPromptEvent);
        };

        window.addEventListener('beforeinstallprompt', handler);

        return () => window.removeEventListener('beforeinstallprompt', handler);
    }, []);

    const handleInstall = async (): Promise<void> => {
        if (!installPrompt) {
            return;
        }

        await installPrompt.prompt();
        await installPrompt.userChoice;
        dismissInstallBanner();
        setInstallPrompt(null);
    };

    const handleDismiss = (): void => {
        dismissInstallBanner();
        setInstallPrompt(null);
        setShowIosHint(false);
    };

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

                    {(installPrompt !== null || showIosHint) && (
                        <section className="flex items-start justify-between gap-3 rounded-lg border border-border bg-card p-4 shadow-xs">
                            <div className="space-y-1 text-sm">
                                <h2 className="font-semibold text-foreground">
                                    Pasang aplikasi di layar utama
                                </h2>
                                {installPrompt ? (
                                    <>
                                        <p className="text-muted-foreground">
                                            Buka portal wali lebih cepat seperti
                                            aplikasi di HP Anda.
                                        </p>
                                        <div className="pt-1">
                                            <Button
                                                type="button"
                                                size="sm"
                                                onClick={() => {
                                                    void handleInstall();
                                                }}
                                            >
                                                <Download className="size-4" />
                                                Pasang aplikasi
                                            </Button>
                                        </div>
                                    </>
                                ) : (
                                    <p className="text-muted-foreground">
                                        Ketuk tombol Bagikan di Safari lalu
                                        pilih Tambahkan ke Layar Utama.
                                    </p>
                                )}
                            </div>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                aria-label="Tutup ajakan pasang aplikasi"
                                onClick={handleDismiss}
                            >
                                <X className="size-4" />
                            </Button>
                        </section>
                    )}

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
