import { ShieldAlert } from 'lucide-react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

type NoAccessStateProps = {
    title?: string;
    description?: string;
    action?: ReactNode;
    className?: string;
};

export function NoAccessState({
    title = 'Belum memiliki akses',
    description = 'Akun Anda belum memiliki izin untuk membuka halaman ini. Hubungi operator yayasan atau administrator untuk menetapkan peran yang sesuai.',
    action,
    className,
}: NoAccessStateProps) {
    return (
        <div
            className={cn(
                'w-full max-w-md rounded-lg border border-border bg-card p-6 text-center',
                className,
            )}
        >
            <div className="mx-auto mb-4 flex size-11 items-center justify-center rounded-lg bg-muted text-muted-foreground">
                <ShieldAlert className="size-5" strokeWidth={1.75} />
            </div>
            <h1 className="text-lg font-semibold text-foreground">{title}</h1>
            <p className="mt-2 text-sm text-muted-foreground">{description}</p>
            {action && <div className="mt-6">{action}</div>}
        </div>
    );
}
