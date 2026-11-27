import { Head, usePage } from '@inertiajs/react';
import { EmptyState } from '@/components/app/empty-state';
import type { GuardianContextData } from '@/layouts/guardian-layout';
import GuardianLayout from '@/layouts/guardian-layout';

export default function GuardianHome() {
    const { guardianContext } = usePage<{
        guardianContext?: GuardianContextData | null;
    }>().props;
    const child = guardianContext?.activeChild ?? null;

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

                    <EmptyState
                        title="Belum ada pembaruan hari ini"
                        description="Informasi presensi, perilaku, dan hafalan anak akan muncul di sini saat tercatat oleh sekolah."
                    />
                </div>
            )}
        </GuardianLayout>
    );
}
