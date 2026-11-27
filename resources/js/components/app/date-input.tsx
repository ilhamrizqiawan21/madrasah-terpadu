import { CalendarDays } from 'lucide-react';
import type { ComponentProps } from 'react';
import { useRef, useState } from 'react';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

type DateInputProps = Omit<
    ComponentProps<typeof Input>,
    'type' | 'value' | 'onChange' | 'inputMode' | 'maxLength'
> & {
    /** Nilai `YYYY-MM-DD`, atau teks mentah selama belum lengkap. */
    value: string;
    /** Bentuk sama dengan event input agar pemanggil `e.target.value` tetap berlaku. */
    onChange: (event: { target: { value: string } }) => void;
};

function toIso(raw: string): string | null {
    const match = /^(\d{1,2})[/.-](\d{1,2})[/.-](\d{4})$/.exec(raw.trim());

    if (!match) {
        return null;
    }

    const [, d, m, y] = match;
    const day = Number(d);
    const month = Number(m);
    const date = new Date(Number(y), month - 1, day);

    if (
        date.getFullYear() !== Number(y) ||
        date.getMonth() !== month - 1 ||
        date.getDate() !== day
    ) {
        return null;
    }

    return `${y}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
}

function toDisplay(value: string): string {
    const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value);

    return match ? `${match[3]}/${match[2]}/${match[1]}` : value;
}

/**
 * Tanggal berformat Indonesia `dd/mm/yyyy`. `<input type="date">` mengikuti locale browser
 * (mm/dd/yyyy di browser berbahasa Inggris), jadi tanggal diketik sebagai teks; tombol
 * kalender membuka pemilih bawaan browser. Ketikan yang belum valid diteruskan apa adanya
 * agar validasi server yang melaporkannya.
 */
export function DateInput({
    value,
    onChange,
    className,
    placeholder = 'hh/bb/tttt',
    disabled,
    min,
    max,
    ...props
}: DateInputProps) {
    const pickerRef = useRef<HTMLInputElement>(null);
    const [text, setText] = useState(() => toDisplay(value));
    const [syncedValue, setSyncedValue] = useState(value);

    if (value !== syncedValue) {
        setSyncedValue(value);

        if ((toIso(text) ?? text) !== value) {
            setText(toDisplay(value));
        }
    }

    const emit = (next: string): void => {
        setSyncedValue(next);
        onChange({ target: { value: next } });
    };

    return (
        <div className={cn('relative', className)}>
            <Input
                {...props}
                type="text"
                inputMode="numeric"
                autoComplete="off"
                maxLength={10}
                placeholder={placeholder}
                disabled={disabled}
                value={text}
                className="pr-9 tabular-nums"
                onChange={(event) => {
                    let next = event.target.value.replace(/[^\d/]/g, '');

                    // Sisipkan garis miring otomatis saat mengetik angka saja: 27112026 → 27/11/2026.
                    if (/^\d{3,8}$/.test(next)) {
                        next = [
                            next.slice(0, 2),
                            next.slice(2, 4),
                            next.slice(4),
                        ]
                            .filter(Boolean)
                            .join('/');
                    }

                    setText(next);
                    emit(next === '' ? '' : (toIso(next) ?? next));
                }}
            />
            <button
                type="button"
                tabIndex={-1}
                disabled={disabled}
                aria-hidden
                onClick={() => pickerRef.current?.showPicker?.()}
                className="absolute inset-y-0 right-0 flex w-9 items-center justify-center text-muted-foreground transition-colors duration-150 hover:text-foreground disabled:opacity-50"
            >
                <CalendarDays className="size-4" strokeWidth={1.75} />
            </button>
            <input
                ref={pickerRef}
                type="date"
                tabIndex={-1}
                aria-hidden
                className="pointer-events-none absolute right-0 bottom-0 h-0 w-0 opacity-0"
                value={/^\d{4}-\d{2}-\d{2}$/.test(value) ? value : ''}
                min={typeof min === 'string' ? min : undefined}
                max={typeof max === 'string' ? max : undefined}
                onChange={(event) => {
                    setText(toDisplay(event.target.value));
                    emit(event.target.value);
                }}
            />
        </div>
    );
}
