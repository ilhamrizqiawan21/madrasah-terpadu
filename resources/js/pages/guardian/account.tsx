import { Head, Link, router, usePage } from '@inertiajs/react';
import { Bell, BellOff, KeyRound, LogOut } from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import NotificationPreferenceController from '@/actions/App/Http/Controllers/Guardian/NotificationPreferenceController';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import type { GuardianContextData } from '@/layouts/guardian-layout';
import GuardianLayout from '@/layouts/guardian-layout';
import type { PushPermissionState } from '@/lib/push';
import {
    getCurrentPushSubscription,
    getPushPermission,
    subscribeToPush,
    unsubscribeFromPush,
} from '@/lib/push';
import { logout } from '@/routes';
import { edit as editSecurity } from '@/routes/security';

type PreferenceItem = {
    category: string;
    label: string;
    enabled: boolean;
    locked: boolean;
};

type Props = {
    profile: {
        name: string;
        phone: string | null;
        relation: string | null;
    };
    preferences: PreferenceItem[];
    vapidPublicKey: string | null;
};

export default function GuardianAccount({
    profile,
    preferences,
    vapidPublicKey,
}: Props) {
    const { guardianContext } = usePage<{
        guardianContext?: GuardianContextData | null;
    }>().props;
    const linkedChildren = guardianContext?.children ?? [];

    const [prefs, setPrefs] = useState<Record<string, boolean>>(() =>
        Object.fromEntries(preferences.map((p) => [p.category, p.enabled])),
    );
    const [savingPrefs, setSavingPrefs] = useState(false);

    const [permission, setPermission] = useState<PushPermissionState>(() =>
        getPushPermission(),
    );
    const [subscribed, setSubscribed] = useState(false);
    const [togglingPush, setTogglingPush] = useState(false);

    useEffect(() => {
        setPermission(getPushPermission());
        void getCurrentPushSubscription().then((sub) =>
            setSubscribed(sub !== null),
        );
    }, []);

    const toggleCategory = (category: string, enabled: boolean): void => {
        const next = { ...prefs, [category]: enabled };
        setPrefs(next);
        setSavingPrefs(true);

        router.put(
            NotificationPreferenceController.update.url(),
            { preferences: next },
            {
                preserveScroll: true,
                onFinish: () => setSavingPrefs(false),
            },
        );
    };

    const handleEnablePush = async (): Promise<void> => {
        if (!vapidPublicKey) {
            toast.error('Kunci notifikasi server belum dikonfigurasi.');

            return;
        }

        setTogglingPush(true);
        try {
            const ok = await subscribeToPush(vapidPublicKey);
            setPermission(getPushPermission());
            setSubscribed(ok);

            if (ok) {
                toast.success('Notifikasi perangkat diaktifkan.');
            } else {
                toast.error('Izin notifikasi belum diberikan.');
            }
        } finally {
            setTogglingPush(false);
        }
    };

    const handleDisablePush = async (): Promise<void> => {
        setTogglingPush(true);
        try {
            await unsubscribeFromPush();
            setSubscribed(false);
            toast.success('Notifikasi perangkat dinonaktifkan.');
        } finally {
            setTogglingPush(false);
        }
    };

    return (
        <GuardianLayout title="Akun" requireChild={false}>
            <Head title="Akun" />

            <div className="space-y-5">
                <section className="rounded-lg border border-border bg-card p-4 shadow-xs">
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
                        <p className="rounded-lg border border-border bg-card p-4 text-sm text-muted-foreground shadow-xs">
                            Belum ada anak terhubung. Hubungi admin sekolah.
                        </p>
                    ) : (
                        <ul className="divide-y divide-border rounded-lg border border-border bg-card shadow-xs">
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
                    <h2 className="text-sm font-semibold">Notifikasi</h2>

                    <div className="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-border bg-card p-4 text-sm shadow-xs">
                        <div>
                            <div className="font-medium">
                                Notifikasi perangkat ini
                            </div>
                            <div className="text-xs text-muted-foreground">
                                {permission === 'unsupported'
                                    ? 'Browser ini belum mendukung Web Push.'
                                    : permission === 'denied'
                                      ? 'Izin notifikasi diblokir di pengaturan browser.'
                                      : subscribed
                                        ? 'Aktif di perangkat ini.'
                                        : 'Belum diaktifkan di perangkat ini.'}
                            </div>
                        </div>

                        {permission !== 'unsupported' &&
                            permission !== 'denied' &&
                            (subscribed ? (
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    className="min-h-10"
                                    disabled={togglingPush}
                                    onClick={() => {
                                        void handleDisablePush();
                                    }}
                                >
                                    <BellOff className="size-4" />
                                    Nonaktifkan
                                </Button>
                            ) : (
                                <Button
                                    type="button"
                                    size="sm"
                                    className="min-h-10"
                                    disabled={togglingPush || !vapidPublicKey}
                                    onClick={() => {
                                        void handleEnablePush();
                                    }}
                                >
                                    <Bell className="size-4" />
                                    Aktifkan
                                </Button>
                            ))}
                    </div>

                    <ul className="divide-y divide-border rounded-lg border border-border bg-card shadow-xs">
                        {preferences.map((item) => {
                            const id = `notif-pref-${item.category}`;

                            return (
                                <li
                                    key={item.category}
                                    className="flex min-h-12 items-center justify-between gap-3 px-4 py-2.5 text-sm"
                                >
                                    <label
                                        htmlFor={id}
                                        className="flex-1 cursor-pointer font-medium"
                                    >
                                        {item.label}
                                    </label>
                                    <div className="flex items-center gap-2">
                                        {item.locked && (
                                            <span className="text-xs text-muted-foreground">
                                                selalu aktif
                                            </span>
                                        )}
                                        <Checkbox
                                            id={id}
                                            checked={
                                                item.locked ||
                                                Boolean(prefs[item.category])
                                            }
                                            disabled={
                                                item.locked || savingPrefs
                                            }
                                            onCheckedChange={(checked) =>
                                                toggleCategory(
                                                    item.category,
                                                    checked === true,
                                                )
                                            }
                                        />
                                    </div>
                                </li>
                            );
                        })}
                    </ul>
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
