import { formatDate, formatDateTime, formatTime } from '@/lib/format';
import { cn } from '@/lib/utils';

type DateTextProps = {
    value: string | Date | null | undefined;
    mode?: 'date' | 'time' | 'datetime';
    fallback?: string;
    className?: string;
};

export function DateText({
    value,
    mode = 'date',
    fallback = '—',
    className,
}: DateTextProps) {
    if (value === null || value === undefined || value === '') {
        return (
            <span className={cn('text-muted-foreground', className)}>
                {fallback}
            </span>
        );
    }

    const formatted =
        mode === 'time'
            ? formatTime(value)
            : mode === 'datetime'
              ? formatDateTime(value)
              : formatDate(value);

    return (
        <span className={cn('whitespace-nowrap tabular-nums', className)}>
            {formatted}
        </span>
    );
}
