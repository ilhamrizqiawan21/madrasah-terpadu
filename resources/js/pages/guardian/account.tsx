import { Head, Link, router, usePage } from '@inertiajs/react';
import { KeyRound, LogOut } from 'lucide-react';
import { Button } from '@/components/ui/button';
import type { GuardianContextData } from '@/layouts/guardian-layout';
import GuardianLayout from '@/layouts/guardian-layout';
import { logout } from '@/routes';
import { edit as editSecurity } from '@/routes/security';

type Props = {
    profile: {
        name: string;
        phone: string | null;
        relation: string | null;
    };
};

export default function GuardianAccount({ profile }: Props) {
    const { guardianContext } = usePage<{
        guardianContext?: GuardianContextData | null;
    }>().props;
    const linkedChildren = guardianContext?.children ?? [];

    return (
        <GuardianLayout title="Akun" requireChild={false}>
            <Head title="Akun" />

            <div className="space-y-5">
                <section className="rounded-lg border border-border bg-card p-4">
                    <div className="text-base font-semibold">
                        {profile.name}
                    </div>
                    <div className="mt-0.5 text-sm text-muted-foreground tabular-nums">
                        {profile.phone ?? '—'}
                        {profile.relation ? ` · ${profile.relation}` : ''}
                    </div>
                </section>

                <section className="space-y-2">
                    <h2 className="text-sm font-semibold">Anak terhubung</h2>
                    {linkedChildren.length === 0 ? (
                        <p className="rounded-lg border border-border bg-card p-4 text-sm text-muted-foreground">
                            Belum ada anak terhubung. Hubungi admin sekolah.
                        </p>
                    ) : (
                        <ul className="divide-y divide-border rounded-lg border border-border bg-card">
                            {linkedChildren.map((child) => (
                                <li
                                    key={child.id}
                                    className="flex items-center justify-between gap-2 px-4 py-3 text-sm"
                                >
                                    <span className="font-medium">
                                        {child.name}
                                    </span>
                                    <span className="text-muted-foreground">
                                        {child.unit}
                                        {child.classroom
                                            ? ` · ${child.classroom}`
                                            : ''}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>

                <section className="space-y-2">
                    <Button
                        variant="outline"
                        asChild
                        className="h-11 w-full justify-start"
                    >
                        <Link href={editSecurity()}>
                            <KeyRound className="size-4" strokeWidth={1.75} />
                            Ganti kata sandi
                        </Link>
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        className="h-11 w-full justify-start text-destructive hover:bg-destructive/10 hover:text-destructive"
                        onClick={() => router.post(logout.url())}
                    >
                        <LogOut className="size-4" strokeWidth={1.75} />
                        Keluar
                    </Button>
                </section>
            </div>
        </GuardianLayout>
    );
}
