import { Link, router, usePage } from '@inertiajs/react';
import { ChevronDown, Menu } from 'lucide-react';
import { useEffect, useState } from 'react';
import SchoolContextController from '@/actions/App/Http/Controllers/Admin/SchoolContextController';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { UserMenuContent } from '@/components/user-menu-content';
import { useInitials } from '@/hooks/use-initials';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes/admin';
import type {
    Auth,
    BreadcrumbItem,
    SchoolContext,
    StaffNavGroup,
} from '@/types';

type SharedProps = {
    name: string;
    auth: Auth;
    branding?: {
        appName: string;
        foundationName: string;
        logoUrl: string | null;
        primaryColor?: string | null;
    };
    navigation?: StaffNavGroup[];
    schoolContext?: SchoolContext | null;
};

export default function StaffLayout({
    breadcrumbs = [],
    children,
}: {
    breadcrumbs?: BreadcrumbItem[];
    children: React.ReactNode;
}) {
    const page = usePage<SharedProps>();
    const { auth, name, branding, navigation = [], schoolContext } = page.props;
    const currentPath = page.url.split('?')[0];
    const getInitials = useInitials();
    const [mobileOpen, setMobileOpen] = useState(false);

    useEffect(() => {
        const root = document.documentElement.style;
        if (branding?.primaryColor) {
            root.setProperty('--primary', branding.primaryColor);
            root.setProperty('--sidebar-primary', branding.primaryColor);
        } else {
            root.removeProperty('--primary');
            root.removeProperty('--sidebar-primary');
        }
    }, [branding?.primaryColor]);

    const isItemActive = (href: string): boolean =>
        currentPath === href || currentPath.startsWith(`${href}/`);

    const isGroupActive = (group: StaffNavGroup): boolean =>
        group.items.some((item) => isItemActive(item.href));

    const unitLabel = schoolContext?.unit
        ? `Unit ${schoolContext.unit}`
        : 'Belum ada unit';
    const yearLabel =
        schoolContext?.academicYear ?? 'Belum ada tahun ajaran aktif';

    const updateContext = (payload: {
        unit?: string;
        academic_year_id?: number;
    }): void => {
        router.post(SchoolContextController.url(), payload, {
            preserveScroll: true,
        });
    };

    return (
        <div className="min-h-screen bg-background text-foreground">
            <header className="sticky top-0 z-20 border-b border-primary-foreground/15 bg-primary text-primary-foreground shadow-xs">
                <div className="mx-auto flex h-14 max-w-7xl items-center justify-between gap-4 px-4 sm:px-6">
                    <div className="flex items-center gap-3">
                        <Sheet open={mobileOpen} onOpenChange={setMobileOpen}>
                            <SheetTrigger asChild>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    className="text-primary-foreground hover:bg-primary-foreground/15 hover:text-primary-foreground lg:hidden"
                                    aria-label="Buka menu navigasi"
                                    aria-expanded={mobileOpen}
                                >
                                    <Menu
                                        className="size-5"
                                        strokeWidth={1.75}
                                    />
                                </Button>
                            </SheetTrigger>
                            <SheetContent side="left" className="w-72 p-4">
                                <SheetHeader className="pb-4 text-left">
                                    <SheetTitle>{name}</SheetTitle>
                                    {schoolContext && (
                                        <p className="text-xs text-muted-foreground">
                                            {unitLabel} · {yearLabel}
                                        </p>
                                    )}
                                </SheetHeader>
                                <nav
                                    aria-label="Menu utama"
                                    className="space-y-4"
                                >
                                    {navigation.map((group) => (
                                        <div
                                            key={group.key}
                                            className="space-y-1"
                                        >
                                            <div className="px-2 text-xs font-medium text-muted-foreground">
                                                {group.label}
                                            </div>
                                            {group.items.map((item) => {
                                                const active = isItemActive(
                                                    item.href,
                                                );

                                                return (
                                                    <Link
                                                        key={item.route}
                                                        href={item.href}
                                                        onClick={() =>
                                                            setMobileOpen(false)
                                                        }
                                                        className={cn(
                                                            'block rounded-md px-3 py-2 text-sm transition-colors',
                                                            active
                                                                ? 'border-l-2 border-primary bg-muted font-semibold text-foreground'
                                                                : 'text-muted-foreground hover:bg-muted hover:text-foreground',
                                                        )}
                                                    >
                                                        {item.label}
                                                    </Link>
                                                );
                                            })}
                                        </div>
                                    ))}
                                </nav>
                            </SheetContent>
                        </Sheet>

                        <Link
                            href={dashboard()}
                            className="flex items-center gap-2.5"
                        >
                            {branding?.logoUrl ? (
                                <img
                                    src={branding.logoUrl}
                                    alt={branding.appName || name}
                                    className="size-7 rounded-md border border-primary-foreground/25 bg-card object-cover"
                                />
                            ) : (
                                <span className="flex size-7 items-center justify-center rounded-md border border-primary-foreground/20 bg-primary-foreground/15 text-xs font-semibold text-primary-foreground">
                                    MT
                                </span>
                            )}
                            <span className="hidden text-sm leading-tight sm:block">
                                <span className="block font-semibold text-primary-foreground">
                                    {branding?.appName || name}
                                </span>
                                {schoolContext && (
                                    <span className="block text-xs text-primary-foreground/75">
                                        {unitLabel}
                                    </span>
                                )}
                            </span>
                        </Link>
                    </div>

                    <nav
                        aria-label="Navigasi staf"
                        className="hidden items-center justify-center gap-1 lg:flex"
                    >
                        {navigation.map((group) => {
                            const active = isGroupActive(group);

                            return (
                                <DropdownMenu key={group.key}>
                                    <DropdownMenuTrigger asChild>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            className={cn(
                                                'gap-1 text-sm',
                                                active
                                                    ? 'bg-primary-foreground/15 font-semibold text-primary-foreground hover:bg-primary-foreground/20 hover:text-primary-foreground'
                                                    : 'font-medium text-primary-foreground/80 hover:bg-primary-foreground/10 hover:text-primary-foreground',
                                            )}
                                        >
                                            {group.label}
                                            <ChevronDown
                                                className="size-4 opacity-70"
                                                strokeWidth={1.75}
                                            />
                                        </Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="start">
                                        {group.items.map((item) => (
                                            <DropdownMenuItem
                                                key={item.route}
                                                asChild
                                            >
                                                <Link
                                                    href={item.href}
                                                    className={cn(
                                                        isItemActive(
                                                            item.href,
                                                        ) && 'font-semibold',
                                                    )}
                                                >
                                                    {item.label}
                                                </Link>
                                            </DropdownMenuItem>
                                        ))}
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            );
                        })}
                    </nav>

                    <div className="flex items-center gap-2">
                        {schoolContext && (
                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        className="gap-1.5 border border-primary-foreground/20 bg-primary-foreground/10 text-xs text-primary-foreground hover:bg-primary-foreground/20 hover:text-primary-foreground sm:text-sm"
                                    >
                                        <span
                                            aria-hidden
                                            className={cn(
                                                'size-2 rounded-full',
                                                schoolContext.unit === 'RA'
                                                    ? 'bg-accent-attention'
                                                    : schoolContext.unit ===
                                                        'TPQ'
                                                      ? 'bg-success'
                                                      : 'bg-primary-foreground/80',
                                            )}
                                        />
                                        <span>
                                            {schoolContext.unit ?? 'Unit'} ·{' '}
                                            {schoolContext.academicYear ??
                                                'Tahun ajaran'}
                                        </span>
                                        <ChevronDown
                                            className="size-3.5 opacity-80"
                                            strokeWidth={1.75}
                                        />
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end">
                                    <DropdownMenuLabel>Unit</DropdownMenuLabel>
                                    {schoolContext.units.length === 0 && (
                                        <DropdownMenuItem disabled>
                                            Belum ada unit aktif
                                        </DropdownMenuItem>
                                    )}
                                    {schoolContext.units.map((unit) => (
                                        <DropdownMenuItem
                                            key={unit}
                                            onClick={() =>
                                                updateContext({ unit })
                                            }
                                            className={cn(
                                                schoolContext.unit === unit &&
                                                    'font-semibold',
                                            )}
                                        >
                                            Unit {unit}
                                        </DropdownMenuItem>
                                    ))}
                                    <DropdownMenuSeparator />
                                    <DropdownMenuLabel>
                                        Tahun ajaran
                                    </DropdownMenuLabel>
                                    {schoolContext.academicYears.length ===
                                        0 && (
                                        <DropdownMenuItem disabled>
                                            Belum ada tahun ajaran
                                        </DropdownMenuItem>
                                    )}
                                    {schoolContext.academicYears.map((year) => (
                                        <DropdownMenuItem
                                            key={year.id}
                                            onClick={() =>
                                                updateContext({
                                                    academic_year_id: year.id,
                                                })
                                            }
                                            className={cn(
                                                schoolContext.academicYearId ===
                                                    year.id && 'font-semibold',
                                            )}
                                        >
                                            {year.label}
                                            {year.isActive && (
                                                <span className="ml-auto pl-3 text-xs text-muted-foreground">
                                                    aktif
                                                </span>
                                            )}
                                        </DropdownMenuItem>
                                    ))}
                                </DropdownMenuContent>
                            </DropdownMenu>
                        )}

                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    className="rounded-full"
                                    aria-label="Menu pengguna"
                                >
                                    <Avatar className="size-8">
                                        <AvatarImage
                                            src={auth.user?.avatar}
                                            alt={auth.user?.name}
                                        />
                                        <AvatarFallback className="bg-muted text-xs font-semibold text-foreground">
                                            {getInitials(auth.user?.name ?? '')}
                                        </AvatarFallback>
                                    </Avatar>
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent className="w-56" align="end">
                                {auth.user && (
                                    <UserMenuContent user={auth.user} />
                                )}
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                </div>
            </header>

            {breadcrumbs.length > 0 && (
                <div className="border-b border-border/70 bg-background">
                    <div className="mx-auto flex h-10 max-w-7xl items-center px-4 text-muted-foreground sm:px-6">
                        <Breadcrumbs breadcrumbs={breadcrumbs} />
                    </div>
                </div>
            )}

            <main className="mx-auto w-full max-w-7xl px-4 py-6 sm:px-6">
                {children}
            </main>
        </div>
    );
}
