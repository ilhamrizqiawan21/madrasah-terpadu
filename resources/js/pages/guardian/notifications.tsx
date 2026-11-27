import { Head, Link, router, usePage } from '@inertiajs/react';
import { Bell, CheckCheck } from 'lucide-react';
import NotificationInboxController from '@/actions/App/Http/Controllers/Guardian/NotificationInboxController';
import { DateText } from '@/components/app/date-text';
import { EmptyState } from '@/components/app/empty-state';
import { Button } from '@/components/ui/button';
import type { GuardianContextData } from '@/layouts/guardian-layout';
import GuardianLayout from '@/layouts/guardian-layout';
import { cn } from '@/lib/utils';
import type { PaginatedResource } from '@/types';

type NotificationRow = {
    id: string;
    title: string;
    body: string;
    url: string | null;
    category: string | null;
    read_at: string | null;
    created_at: string | null;
};

type Props = {
    notifications: PaginatedResource<NotificationRow>;
};

export default function GuardianNotifications({ notifications }: Props) {
    const { guardianContext } = usePage<{
        guardianContext?: GuardianContextData | null;
    }>().props;
    const unread = guardianContext?.unreadNotifications ?? 0;

    const markAllRead = (): void => {
        router.post(
            NotificationInboxController.markAllRead.url(),
            {},
            { preserveScroll: true },
        );
    };

    const markRead = (id: string): void => {
        router.patch(
            NotificationInboxController.markRead.url(id),
            {},
            { preserveScroll: true },
        );
    };

    const goToPage = (page: number): void => {
        router.get(
            NotificationInboxController.index.url(),
            { page },
            { preserveScroll: true },
        );
    };

    return (
        <GuardianLayout title="Kotak masuk" requireChild={false}>
            <Head title="Kotak masuk" />

            <div className="space-y-4">
                {unread > 0 && (
                    <div className="flex items-center justify-between gap-2">
                        <p className="text-sm text-muted-foreground tabular-nums">
                            {unread} belum dibaca
                        </p>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            className="min-h-10"
                            onClick={markAllRead}
                        >
                            <CheckCheck className="size-4" />
                            Tandai semua dibaca
                        </Button>
                    </div>
                )}

                {notifications.data.length === 0 ? (
                    <EmptyState
                        icon={Bell}
                        title="Belum ada notifikasi"
                        description="Pemberitahuan presensi, tagihan, kantin, perilaku, dan hafalan akan tersimpan di sini."
                    />
                ) : (
                    <ul className="space-y-2.5">
                        {notifications.data.map((item) => {
                            const isUnread = item.read_at === null;

                            return (
                                <li
                                    key={item.id}
                                    className={cn(
                                        'rounded-lg border bg-card p-4 shadow-xs transition-colors',
                                        isUnread
                                            ? 'border-primary/40'
                                            : 'border-border',
                                    )}
                                >
                                    <div className="flex items-start justify-between gap-3">
                                        <div className="min-w-0 flex-1 space-y-1">
                                            <div className="flex items-center gap-2">
                                                {isUnread && (
                                                    <span
                                                        aria-label="Belum dibaca"
                                                        className="size-2 shrink-0 rounded-full bg-primary"
                                                    />
                                                )}
                                                <h2
                                                    className={cn(
                                                        'text-sm',
                                                        isUnread
                                                            ? 'font-semibold text-foreground'
                                                            : 'font-medium text-foreground',
                                                    )}
                                                >
                                                    {item.title}
                                                </h2>
                                            </div>
                                            {item.body && (
                                                <p className="text-sm text-muted-foreground">
                                                    {item.body}
                                                </p>
                                            )}
                                            <div className="text-xs text-muted-foreground">
                                                <DateText
                                                    value={item.created_at}
                                                    mode="datetime"
                                                />
                                            </div>
                                        </div>
                                        <div className="flex shrink-0 flex-col items-end gap-1">
                                            {item.url && (
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    asChild
                                                    onClick={() => {
                                                        if (isUnread) {
                                                            markRead(item.id);
                                                        }
                                                    }}
                                                >
                                                    <Link href={item.url}>
                                                        Buka
                                                    </Link>
                                                </Button>
                                            )}
                                            {isUnread && !item.url && (
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() =>
                                                        markRead(item.id)
                                                    }
                                                >
                                                    Tandai dibaca
                                                </Button>
                                            )}
                                        </div>
                                    </div>
                                </li>
                            );
                        })}
                    </ul>
                )}

                {notifications.meta.last_page > 1 && (
                    <div className="flex items-center justify-between pt-2">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            className="min-h-11"
                            disabled={notifications.meta.current_page <= 1}
                            onClick={() =>
                                goToPage(notifications.meta.current_page - 1)
                            }
                        >
                            Sebelumnya
                        </Button>
                        <span className="text-xs text-muted-foreground tabular-nums">
                            Halaman {notifications.meta.current_page} dari{' '}
                            {notifications.meta.last_page}
                        </span>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            className="min-h-11"
                            disabled={
                                notifications.meta.current_page >=
                                notifications.meta.last_page
                            }
                            onClick={() =>
                                goToPage(notifications.meta.current_page + 1)
                            }
                        >
                            Berikutnya
                        </Button>
                    </div>
                )}
            </div>
        </GuardianLayout>
    );
}
