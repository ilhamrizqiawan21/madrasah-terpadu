import { formatRupiah } from '@/lib/format';
import { cn } from '@/lib/utils';

type MoneyTextProps = {
    amount: number;
    className?: string;
};

export function MoneyText({ amount, className }: MoneyTextProps) {
    return (
        <span className={cn('whitespace-nowrap tabular-nums', className)}>
            {formatRupiah(amount)}
        </span>
    );
}
