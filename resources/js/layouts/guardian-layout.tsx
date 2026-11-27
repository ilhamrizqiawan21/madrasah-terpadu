import { Link, router, usePage } from '@inertiajs/react';
import { Bell, Clock, Home, ReceiptText, User } from 'lucide-react';
import type { ReactNode } from 'react';
import AccountController from '@/actions/App/Http/Controllers/Guardian/AccountController';
import BillingController from '@/actions/App/Http/Controllers/Guardian/BillingController';
import ChildContextController from '@/actions/App/Http/Controllers/Guardian/ChildContextController';
import HistoryController from '@/actions/App/Http/Controllers/Guardian/HistoryController';
import HomeController from '@/actions/App/Http/Controllers/Guardian/HomeController';
import { EmptyState } from '@/components/app/empty-state';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { cn } from '@/lib/utils';

export type GuardianChild = {
    id: number;
    nis: string;
    name: string;
    nickname: string | null;
    unit: string;
    classroom: string | null;
    photo_url: string | null;
};

export type GuardianContextData = {
    children: GuardianChild[];
    activeChild: GuardianChild | null;
    billingEnabled: boolean;
    unreadNotifications: number;
};

type SharedProps = {
    name: string;
    branding?: {
        appName: string;
        foundationName: string;
        logoUrl: string | null;
    };
    guardianContext?: GuardianContextData | null;
};

type GuardianLayoutProps = {
    title?: string;
    requireChild?: boolean;
    children: ReactNode;
};

export default function GuardianLayout({
    title,
    requireChild = true,
    children,
}: GuardianLayoutProps) {
    const page = usePage<SharedProps>();
    const { name, branding, guardianContext } = page.props;
    const currentPath = page.url.split('?')[0];

    const linkedChildren = guardianContext?.children ?? [];
    const activeChild = guardianContext?.activeChild ?? null;
    const billingEnabled = guardianContext?.billingEnabled ?? false;
    const unreadNotifications = guardianContext?.unreadNotifications ?? 0;

    const appName = branding?.appName || name;
    const schoolSubtitle = activeChild
        ? `${activeChild.unit} · ${appName}`
        : appName;

    const selectChild = (studentId: string): void => {
        router.post(
            ChildContextController.url(),
            { student_id: Number(studentId) },
            { preserveScroll: true },
        );
    };

    const navItems = [
        {
            key: 'home',
            label: 'Beranda',
            href: HomeController.url(),
            icon: Home,
            active: currentPath === '/wali',
        },
        ...(billingEnabled
            ? [
                  {
                      key: 'billing',
                      label: 'Tagihan',
                      href: BillingController.url(),
                      icon: ReceiptText,
                      active: currentPath.startsWith('/wali/tagihan'),
                  },
              ]
            : []),
        {
            key: 'history',
            label: 'Riwayat',
            href: HistoryController.url(),
            icon: Clock,
            active: currentPath.startsWith('/wali/riwayat'),
        },
        {
            key: 'account',
            label: 'Akun',
            href: AccountController.url(),
            icon: User,
            active: currentPath.startsWith('/wali/akun'),
        },
    ];

    return (
        <div className="min-h-screen bg-background text-foreground">
            <div className="mx-auto flex min-h-screen max-w-xl flex-col">
                <header className="sticky top-0 z-20 border-b border-border bg-card px-4 py-3">
                    <div className="flex items-center justify-between gap-3">
                        <div className="min-w-0 flex-1">
                            <div className="truncate text-xs text-muted-foreground">
                                {schoolSubtitle}
                            </div>

                            {title ? (
                                <h1 className="truncate text-base font-semibold">
                                    {title}
                                </h1>
                            ) : linkedChildren.length > 1 && activeChild ? (
                                <Select
                                    value={String(activeChild.id)}
                                    onValueChange={selectChild}
                                >
                                    <SelectTrigger
                                        aria-label="Pilih anak"
                                        className="mt-1 h-11 w-full max-w-xs border-border bg-background text-left text-base font-semibold"
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {linkedChildren.map((child) => (
                                            <SelectItem
                                                key={child.id}
                                                value={String(child.id)}
                                            >
                                                {child.name}
                                                {child.classroom
                                                    ? ` · ${child.classroom}`
                                                    : ''}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            ) : (
                                <h1 className="truncate text-base font-semibold">
                                    {activeChild?.name ?? appName}
                                </h1>
                            )}
                        </div>

                        <Link
                            href={AccountController.url()}
                            aria-label="Kotak masuk notifikasi"
                            className="relative flex size-11 shrink-0 items-center justify-center rounded-md text-foreground transition-colors hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        >
                            <Bell className="size-5" strokeWidth={1.75} />
                            {unreadNotifications > 0 && (
                                <span className="absolute top-1.5 right-1.5 flex min-w-4 items-center justify-center rounded-full bg-primary px-1 text-[10px] font-semibold text-primary-foreground tabular-nums">
                                    {unreadNotifications > 99
                                        ? '99+'
                                        : unreadNotifications}
                                </span>
                            )}
                        </Link>
                    </div>
                </header>

                <main className="flex-1 px-4 pt-4 pb-24">
                    {requireChild && linkedChildren.length === 0 ? (
                        <EmptyState
                            title="Belum ada anak terhubung"
                            description="Akun wali Anda belum ditautkan dengan data siswa. Hubungi admin sekolah untuk menautkan data anak Anda."
                        />
                    ) : (
                        children
                    )}
                </main>

                <nav
                    aria-label="Navigasi wali"
                    className="fixed inset-x-0 bottom-0 z-20 border-t border-border bg-card"
                >
                    <div
                        className={cn(
                            'mx-auto grid max-w-xl',
                            navItems.length === 4
                                ? 'grid-cols-4'
                                : 'grid-cols-3',
                        )}
                    >
                        {navItems.map((item) => {
                            const Icon = item.icon;

                            return (
                                <Link
                                    key={item.key}
                                    href={item.href}
                                    aria-current={
                                        item.active ? 'page' : undefined
                                    }
                                    className={cn(
                                        'relative flex min-h-14 flex-col items-center justify-center gap-1 px-2 py-2 text-xs transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                                        item.active
                                            ? 'font-semibold text-foreground'
                                            : 'text-muted-foreground hover:text-foreground',
                                    )}
                                >
                                    {item.active && (
                                        <span
                                            aria-hidden
                                            className="absolute top-0 h-0.5 w-8 rounded-full bg-primary"
                                        />
                                    )}
                                    <Icon
                                        className="size-5"
                                        strokeWidth={item.active ? 2 : 1.75}
                                    />
                                    <span>{item.label}</span>
                                </Link>
                            );
                        })}
                    </div>
                </nav>
            </div>
        </div>
    );
}
