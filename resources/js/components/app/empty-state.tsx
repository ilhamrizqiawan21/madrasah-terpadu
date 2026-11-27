import type { LucideIcon } from 'lucide-react';
import { Inbox } from 'lucide-react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

type EmptyStateProps = {
    icon?: LucideIcon;
    title: string;
    description: string;
    action?: ReactNode;
    className?: string;
};

export function EmptyState({
    icon: Icon = Inbox,
    title,
    description,
    action,
    className,
}: EmptyStateProps) {
    return (
        <div
            className={cn(
                'flex flex-col items-center justify-center rounded-lg border border-dashed border-border bg-card px-6 py-10 text-center',
                className,
            )}
        >
            <div className="mb-3 flex size-10 items-center justify-center rounded-lg bg-muted text-muted-foreground">
                <Icon className="size-5" strokeWidth={1.75} />
            </div>
            <h2 className="text-base font-semibold text-foreground">{title}</h2>
            <p className="mt-1 max-w-md text-sm text-muted-foreground">
                {description}
            </p>
            {action && <div className="mt-4">{action}</div>}
        </div>
    );
}
