import { Link } from '@inertiajs/react';
import type { InertiaLinkProps } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

type DashboardPanelProps = {
    title: string;
    meta?: ReactNode;
    action?: { label: string; href: NonNullable<InertiaLinkProps['href']> };
    tone?: 'default' | 'attention';
    className?: string;
    bodyClassName?: string;
    children: ReactNode;
};

/**
 * Panel beranda: kepala bagian berlatar muted di atas isi kartu, agar tiap
 * keputusan terbaca sebagai satu blok. `attention` hanya untuk yang menunggu tindakan.
 */
export function DashboardPanel({
    title,
    meta,
    action,
    tone = 'default',
    className,
    bodyClassName,
    children,
}: DashboardPanelProps) {
    return (
        <section
            className={cn(
                'overflow-hidden rounded-xl border bg-card shadow-xs',
                tone === 'attention'
                    ? 'border-accent-attention/70'
                    : 'border-border',
                className,
            )}
        >
            <header
                className={cn(
                    'flex items-center justify-between gap-3 border-b px-4 py-3 sm:px-5',
                    tone === 'attention'
                        ? 'border-accent-attention/40 bg-accent-attention/10'
                        : 'border-border bg-muted/60',
                )}
            >
                <div className="min-w-0">
                    <h2 className="text-sm font-semibold text-foreground">
                        {title}
                    </h2>
                    {meta && (
                        <p className="truncate text-xs text-muted-foreground">
                            {meta}
                        </p>
                    )}
                </div>
                {action && (
                    <Link
                        href={action.href}
                        className="inline-flex min-h-9 shrink-0 items-center rounded-md px-2.5 text-xs font-medium text-primary transition-colors duration-150 hover:bg-primary/10 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    >
                        {action.label}
                    </Link>
                )}
            </header>
            <div className={cn('px-4 py-4 sm:px-5', bodyClassName)}>
                {children}
            </div>
        </section>
    );
}
