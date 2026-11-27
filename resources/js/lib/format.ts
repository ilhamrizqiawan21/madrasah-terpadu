const WITA_TIMEZONE = 'Asia/Makassar';

const rupiahFormatter = new Intl.NumberFormat('id-ID', {
    maximumFractionDigits: 0,
});

const dateFormatter = new Intl.DateTimeFormat('id-ID', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    timeZone: WITA_TIMEZONE,
});

const timeFormatter = new Intl.DateTimeFormat('id-ID', {
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
    timeZone: WITA_TIMEZONE,
});

function toDate(value: string | Date): Date {
    return value instanceof Date ? value : new Date(value);
}

/**
 * Format nominal integer rupiah (mis. 1250000 -> "Rp 1.250.000", -50000 -> "-Rp 50.000").
 */
export function formatRupiah(amount: number): string {
    const prefix = amount < 0 ? '-Rp ' : 'Rp ';

    return `${prefix}${rupiahFormatter.format(Math.abs(Math.trunc(amount)))}`;
}

/**
 * Format tanggal dalam zona waktu WITA (mis. "26 Nov 2026").
 */
export function formatDate(value: string | Date): string {
    const date = toDate(value);

    if (Number.isNaN(date.getTime())) {
        return '—';
    }

    return dateFormatter.format(date);
}

/**
 * Format jam dalam zona waktu WITA (mis. "06.52").
 */
export function formatTime(value: string | Date): string {
    const date = toDate(value);

    if (Number.isNaN(date.getTime())) {
        return '—';
    }

    return timeFormatter.format(date).replace(':', '.');
}

/**
 * Format tanggal dan jam dalam zona waktu WITA (mis. "26 Nov 2026, 06.52").
 */
export function formatDateTime(value: string | Date): string {
    const date = toDate(value);

    if (Number.isNaN(date.getTime())) {
        return '—';
    }

    return `${formatDate(date)}, ${formatTime(date)}`;
}
