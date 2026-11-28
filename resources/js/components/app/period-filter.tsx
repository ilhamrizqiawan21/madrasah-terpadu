import { DateInput } from '@/components/app/date-input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

export type PeriodValue = {
    period: string;
    from: string | null;
    to: string | null;
};

type Props = {
    value: PeriodValue;
    options: { value: string; label: string }[];
    onChange: (changes: Partial<PeriodValue>) => void;
};

/**
 * Pemilih periode keuangan (hari ini … rentang tanggal). Rentang tanggal memunculkan dua isian tanggal.
 * Pasangan server: App\Support\PeriodRange.
 */
export function PeriodFilter({ value, options, onChange }: Props) {
    return (
        <>
            <Select
                value={value.period}
                onValueChange={(v) => onChange({ period: v })}
            >
                <SelectTrigger className="w-full @lg:w-44" aria-label="Periode">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    {options.map((o) => (
                        <SelectItem key={o.value} value={o.value}>
                            {o.label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
            {value.period === 'custom' && (
                <>
                    <DateInput
                        value={value.from ?? ''}
                        onChange={(e) =>
                            onChange({ from: e.target.value || null })
                        }
                        aria-label="Dari tanggal"
                        className="w-full @lg:w-40"
                    />
                    <DateInput
                        value={value.to ?? ''}
                        onChange={(e) =>
                            onChange({ to: e.target.value || null })
                        }
                        aria-label="Sampai tanggal"
                        className="w-full @lg:w-40"
                    />
                </>
            )}
        </>
    );
}
