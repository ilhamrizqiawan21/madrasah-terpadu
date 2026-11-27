import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

export type StatusBadgeVariant =
    | 'default'
    | 'success'
    | 'warning'
    | 'destructive'
    | 'attention'
    | 'muted';

const variantClasses: Record<StatusBadgeVariant, string> = {
    default: 'border-primary/25 bg-primary/10 text-primary',
    success: 'border-success/25 bg-success/15 text-success',
    warning:
        'border-warning/30 bg-warning/15 text-warning-foreground dark:text-warning',
    destructive: 'border-destructive/25 bg-destructive/15 text-destructive',
    attention:
        'border-accent-attention/40 bg-accent-attention/20 text-accent-attention-foreground dark:text-accent-attention',
    muted: 'border-border bg-muted text-muted-foreground',
};

type StatusBadgeProps = {
    variant?: StatusBadgeVariant;
    children: ReactNode;
    className?: string;
};

export function StatusBadge({
    variant = 'muted',
    children,
    className,
}: StatusBadgeProps) {
    return (
        <span
            className={cn(
                'inline-flex items-center rounded-md border px-2 py-0.5 text-xs font-medium whitespace-nowrap',
                variantClasses[variant],
                className,
            )}
        >
            {children}
        </span>
    );
}
