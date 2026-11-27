import { Link, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { useEffect, useRef } from 'react';
import StaffLayout from '@/layouts/staff-layout';
import { cn } from '@/lib/utils';
import type { StaffNavGroup } from '@/types';

type SettingsShellProps = {
    title: string;
    description: string;
    actions?: ReactNode;
    /** Untuk bagian berisi tabel; form tetap memakai lebar baca yang sempit. */
    wide?: boolean;
    children: ReactNode;
};

/**
 * Pola pengaturan (mockup 12): judul "Pengaturan", sub-navigasi kiri, satu bagian per halaman.
 * Sub-navigasi diambil dari grup menu "pengaturan" milik server, jadi hanya berisi
 * halaman yang sudah ada dan boleh dibuka pengguna.
 */
export function SettingsShell({
    title,
    description,
    actions,
    wide = false,
    children,
}: SettingsShellProps) {
    const page = usePage<{ navigation?: StaffNavGroup[] }>();
    const currentPath = page.url.split('?')[0];
    const items =
        page.props.navigation?.find((group) => group.key === 'pengaturan')
            ?.items ?? [];
    const current = items.find((item) => item.href === currentPath);
    const activeLink = useRef<HTMLAnchorElement>(null);

    // Di layar sempit sub-navigasi menjadi baris gulir; pastikan bagian aktif terlihat.
    useEffect(() => {
        activeLink.current?.scrollIntoView({
            block: 'nearest',
            inline: 'nearest',
        });
    }, [currentPath]);

    return (
        <StaffLayout
            breadcrumbs={[
                { title: 'Pengaturan', href: items[0]?.href ?? currentPath },
                { title: current?.label ?? title, href: currentPath },
            ]}
        >
            <div className="space-y-6">
                <h1 className="text-xl font-semibold tracking-tight text-foreground sm:text-2xl">
                    Pengaturan
                </h1>

                <div className="grid grid-cols-[minmax(0,1fr)] gap-6 lg:grid-cols-[13.5rem_minmax(0,1fr)] lg:gap-10">
                    <nav aria-label="Bagian pengaturan">
                        <ul className="-mx-1 flex gap-1 overflow-x-auto px-1 pb-1 lg:mx-0 lg:flex-col lg:overflow-visible lg:px-0 lg:pb-0">
                            {items.map((item) => {
                                const active = item.href === currentPath;

                                return (
                                    <li key={item.route} className="shrink-0">
                                        <Link
                                            ref={
                                                active ? activeLink : undefined
                                            }
                                            href={item.href}
                                            aria-current={
                                                active ? 'page' : undefined
                                            }
                                            className={cn(
                                                'block rounded-md px-3 py-2 text-sm whitespace-nowrap transition-colors duration-150 ease-out focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                                                active
                                                    ? 'bg-muted font-medium text-foreground'
                                                    : 'text-muted-foreground hover:bg-muted/60 hover:text-foreground',
                                            )}
                                        >
                                            {item.label}
                                        </Link>
                                    </li>
                                );
                            })}
                        </ul>
                    </nav>

                    <section
                        aria-labelledby="settings-section-title"
                        className={cn(
                            'min-w-0',
                            wide ? 'max-w-5xl' : 'max-w-2xl',
                        )}
                    >
                        <header className="flex flex-col gap-3 border-b border-border pb-4 sm:flex-row sm:items-start sm:justify-between">
                            <div className="space-y-1">
                                <h2
                                    id="settings-section-title"
                                    className="text-lg font-semibold text-foreground"
                                >
                                    {title}
                                </h2>
                                <p className="text-sm text-muted-foreground">
                                    {description}
                                </p>
                            </div>
                            {actions && (
                                <div className="flex shrink-0 gap-2">
                                    {actions}
                                </div>
                            )}
                        </header>
                        <div className="pt-6">{children}</div>
                    </section>
                </div>
            </div>
        </StaffLayout>
    );
}
