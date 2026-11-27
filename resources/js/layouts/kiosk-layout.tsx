import { Link, router } from '@inertiajs/react';
import { ArrowLeft, LogOut, Wifi, WifiOff } from 'lucide-react';
import type { PropsWithChildren } from 'react';
import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import { logout } from '@/routes';
import { dashboard as adminDashboard } from '@/routes/admin';

type Props = PropsWithChildren<{
    schoolName: string;
    kioskTitle: string;
    operatorName: string;
    hasAdminAccess?: boolean;
}>;

export default function KioskLayout({
    children,
    schoolName,
    kioskTitle,
    operatorName,
    hasAdminAccess = false,
}: Props) {
    const [online, setOnline] = useState<boolean>(() =>
        typeof navigator === 'undefined' ? true : navigator.onLine,
    );

    useEffect(() => {
        const handleOnline = (): void => setOnline(true);
        const handleOffline = (): void => setOnline(false);

        window.addEventListener('online', handleOnline);
        window.addEventListener('offline', handleOffline);

        return () => {
            window.removeEventListener('online', handleOnline);
            window.removeEventListener('offline', handleOffline);
        };
    }, []);

    return (
        <div className="flex min-h-screen flex-col bg-background text-foreground">
            <header className="sticky top-0 z-20 border-b border-primary/20 bg-primary text-primary-foreground shadow-xs">
                <div className="mx-auto flex h-16 max-w-7xl items-center justify-between gap-3 px-4 sm:px-6">
                    <div className="flex min-w-0 items-center gap-3">
                        {hasAdminAccess && (
                            <Button
                                asChild
                                variant="ghost"
                                size="icon"
                                className="min-h-11 min-w-11 text-primary-foreground hover:bg-primary-foreground/15 hover:text-primary-foreground"
                            >
                                <Link
                                    href={adminDashboard()}
                                    aria-label="Kembali ke beranda staf"
                                >
                                    <ArrowLeft className="size-5" />
                                </Link>
                            </Button>
                        )}
                        <div className="min-w-0">
                            <p className="truncate text-xs font-medium text-primary-foreground/80">
                                {schoolName}
                            </p>
                            <h1 className="truncate text-base font-semibold tracking-tight sm:text-lg">
                                {kioskTitle}
                            </h1>
                        </div>
                    </div>

                    <div className="flex items-center gap-3">
                        <span
                            role="status"
                            aria-live="polite"
                            className={`inline-flex min-h-9 items-center gap-1.5 rounded-full px-3 text-xs font-medium ${
                                online
                                    ? 'bg-primary-foreground/15 text-primary-foreground'
                                    : 'bg-destructive text-destructive-foreground'
                            }`}
                        >
                            {online ? (
                                <>
                                    <Wifi
                                        className="size-3.5"
                                        aria-hidden="true"
                                    />
                                    <span>Terhubung</span>
                                </>
                            ) : (
                                <>
                                    <WifiOff
                                        className="size-3.5"
                                        aria-hidden="true"
                                    />
                                    <span>Terputus</span>
                                </>
                            )}
                        </span>

                        <span className="hidden text-sm font-medium text-primary-foreground/90 sm:inline">
                            {operatorName}
                        </span>

                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            className="min-h-11 px-3 text-primary-foreground hover:bg-primary-foreground/15 hover:text-primary-foreground"
                            onClick={() => router.post(logout())}
                        >
                            <LogOut className="mr-1.5 size-4" />
                            <span>Keluar</span>
                        </Button>
                    </div>
                </div>
            </header>

            <main className="mx-auto w-full max-w-7xl flex-1 p-4 sm:p-6">
                {children}
            </main>
        </div>
    );
}
