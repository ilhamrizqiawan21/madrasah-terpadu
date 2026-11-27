import { router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

/**
 * Kotak cari nama/NIS siswa untuk daftar server. Mengubah parameter `search` pada URL halaman
 * saat ini (parameter lain dipertahankan, halaman kembali ke 1) dengan jeda 300 ms.
 */
export function StudentSearch({
    value,
    className,
}: {
    value: string;
    className?: string;
}) {
    const [text, setText] = useState(value);
    const first = useRef(true);

    useEffect(() => {
        if (first.current) {
            first.current = false;

            return;
        }

        const timer = window.setTimeout(() => {
            const params = new URLSearchParams(window.location.search);

            if (text.trim() === '') {
                params.delete('search');
            } else {
                params.set('search', text.trim());
            }

            params.delete('page');
            router.get(
                `${window.location.pathname}?${params.toString()}`,
                {},
                { preserveState: true, preserveScroll: true, replace: true },
            );
        }, 300);

        return () => window.clearTimeout(timer);
    }, [text]);

    return (
        <div className={cn('relative w-full sm:w-64', className)}>
            <Search
                className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground"
                strokeWidth={1.75}
            />
            <Input
                type="search"
                value={text}
                onChange={(event) => setText(event.target.value)}
                placeholder="Cari nama atau NIS"
                aria-label="Cari nama atau NIS siswa"
                className="pl-8"
            />
        </div>
    );
}
