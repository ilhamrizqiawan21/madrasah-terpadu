import { AlertCircle } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

type ErrorStateProps = {
    title?: string;
    description: string;
    onRetry?: () => void;
    retryLabel?: string;
    className?: string;
};

export function ErrorState({
    title = 'Gagal memuat data',
    description,
    onRetry,
    retryLabel = 'Coba lagi',
    className,
}: ErrorStateProps) {
    return (
        <div
            role="alert"
            className={cn(
                'flex flex-col items-center justify-center rounded-lg border border-destructive/30 bg-card px-6 py-10 text-center',
                className,
            )}
        >
            <div className="mb-3 flex size-10 items-center justify-center rounded-lg bg-destructive/15 text-destructive">
                <AlertCircle className="size-5" strokeWidth={1.75} />
            </div>
            <h2 className="text-base font-semibold text-foreground">{title}</h2>
            <p className="mt-1 max-w-md text-sm text-muted-foreground">
                {description}
            </p>
            {onRetry && (
                <Button
                    type="button"
                    variant="outline"
                    onClick={onRetry}
                    className="mt-4"
                >
                    {retryLabel}
                </Button>
            )}
        </div>
    );
}
