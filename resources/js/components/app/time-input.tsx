import type { ComponentProps } from 'react';
import { useState } from 'react';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

type TimeInputProps = Omit<
    ComponentProps<typeof Input>,
    'type' | 'value' | 'onChange' | 'inputMode' | 'maxLength'
> & {
    /** Nilai `HH:MM` 24 jam, atau teks mentah selama belum lengkap. */
    value: string;
    onChange: (value: string) => void;
};

/**
 * Ubah ketikan (`0700`, `730`, `7.00`, `07:00`) menjadi `HH:MM` 24 jam.
 * Mengembalikan null bila belum lengkap atau di luar 00–23 / 00–59.
 */
function normalizeTime(raw: string): string | null {
    const text = raw.trim();
    const separated = /^(\d{1,2})[.:](\d{2})$/.exec(text);
    const digits = /^(\d{1,2})(\d{2})$/.exec(text);
    const match = separated ?? digits;

    if (!match) {
        return null;
    }

    const hours = Number(match[1]);
    const minutes = Number(match[2]);

    if (hours > 23 || minutes > 59) {
        return null;
    }

    return `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}`;
}

function toDisplay(value: string): string {
    return /^\d{2}:\d{2}$/.test(value) ? value.replace(':', '.') : value;
}

/**
 * Input jam 24 jam berformat `HH.MM`. `<input type="time">` mengikuti locale browser
 * (AM/PM di browser berbahasa Inggris), jadi jam diketik sebagai teks lalu dinormalisasi.
 * Ketikan yang belum valid diteruskan apa adanya agar validasi server yang melaporkannya.
 */
export function TimeInput({
    value,
    onChange,
    onBlur,
    className,
    placeholder = '07.00',
    ...props
}: TimeInputProps) {
    const [text, setText] = useState(() => toDisplay(value));
    const [syncedValue, setSyncedValue] = useState(value);

    // Nilai dari luar berubah (mis. dialog dibuka untuk baris lain): tampilkan nilai itu.
    if (value !== syncedValue) {
        setSyncedValue(value);

        if ((normalizeTime(text) ?? text) !== value) {
            setText(toDisplay(value));
        }
    }

    return (
        <Input
            {...props}
            type="text"
            inputMode="numeric"
            autoComplete="off"
            maxLength={5}
            placeholder={placeholder}
            value={text}
            onChange={(event) => {
                const next = event.target.value.replace(/[^\d.:]/g, '');
                const normalized = normalizeTime(next) ?? next;

                setText(next);
                setSyncedValue(normalized);
                onChange(normalized);
            }}
            onBlur={(event) => {
                const normalized = normalizeTime(text);

                if (normalized) {
                    setText(toDisplay(normalized));
                }

                onBlur?.(event);
            }}
            className={cn('tabular-nums', className)}
        />
    );
}
