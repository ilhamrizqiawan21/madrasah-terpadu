import { Link, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import StaffLayout from '@/layouts/staff-layout';
import { cn } from '@/lib/utils';
import type { StaffNavGroup } from '@/types';

type SettingsShellProps = {
    title: string;
    description: string;
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
    children,
}: SettingsShellProps) {
    const page = usePage<{ navigation?: StaffNavGroup[] }>();
    const currentPath = page.url.split('?')[0];
    const items =
        page.props.navigation?.find((group) => group.key === 'pengaturan')
            ?.items ?? [];
    const current = items.find((item) => item.href === currentPath);

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
                        className="max-w-2xl min-w-0"
                    >
                        <header className="space-y-1 border-b border-border pb-4">
                            <h2
                                id="settings-section-title"
                                className="text-lg font-semibold text-foreground"
                            >
                                {title}
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                {description}
                            </p>
                        </header>
                        <div className="pt-6">{children}</div>
                    </section>
                </div>
            </div>
        </StaffLayout>
    );
}
