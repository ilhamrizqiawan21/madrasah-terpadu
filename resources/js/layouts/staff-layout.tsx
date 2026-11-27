import { Link, router, usePage } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import {
    BookOpen,
    CalendarCheck,
    ChartColumn,
    ChevronDown,
    ChevronRight,
    ChevronsUpDown,
    ClipboardCheck,
    HeartHandshake,
    House,
    Inbox,
    MoonStar,
    Package,
    Settings,
    Store,
    Users,
    Wallet,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import SchoolContextController from '@/actions/App/Http/Controllers/Admin/SchoolContextController';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarHeader,
    SidebarInset,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
    SidebarProvider,
    SidebarRail,
    SidebarTrigger,
    useSidebar,
} from '@/components/ui/sidebar';
import { UserMenuContent } from '@/components/user-menu-content';
import { useInitials } from '@/hooks/use-initials';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes/admin';
import type {
    Auth,
    BreadcrumbItem,
    SchoolContext,
    StaffNavGroup,
    StaffNavItem,
} from '@/types';

type SharedProps = {
    name: string;
    auth: Auth;
    sidebarOpen: boolean;
    branding?: {
        appName: string;
        foundationName: string;
        logoUrl: string | null;
        primaryColor?: string | null;
    };
    navigation?: StaffNavGroup[];
    schoolContext?: SchoolContext | null;
};

const groupIcons: Record<string, LucideIcon> = {
    users: Users,
    'book-open': BookOpen,
    'calendar-check': CalendarCheck,
    'heart-handshake': HeartHandshake,
    'moon-star': MoonStar,
    'clipboard-check': ClipboardCheck,
    wallet: Wallet,
    store: Store,
    package: Package,
    inbox: Inbox,
    'chart-column': ChartColumn,
    settings: Settings,
};

function usePathActive() {
    const page = usePage();
    const currentPath = page.url.split('?')[0];

    return (href: string): boolean => {
        const path = href.split('?')[0];

        return currentPath === path || currentPath.startsWith(`${path}/`);
    };
}

export default function StaffLayout({
    breadcrumbs = [],
    children,
}: {
    breadcrumbs?: BreadcrumbItem[];
    children: React.ReactNode;
}) {
    const { sidebarOpen, branding } = usePage<SharedProps>().props;

    useEffect(() => {
        const root = document.documentElement.style;

        if (branding?.primaryColor) {
            root.setProperty('--primary', branding.primaryColor);
        } else {
            root.removeProperty('--primary');
        }
    }, [branding?.primaryColor]);

    // Beranda tidak butuh jejak "Beranda" saja; halaman lain tetap memakai breadcrumb (DESIGN §3).
    const showBreadcrumbs = breadcrumbs.length > 1;

    return (
        <SidebarProvider defaultOpen={sidebarOpen}>
            <StaffSidebar />
            <SidebarInset className="min-w-0 bg-background">
                <header className="sticky top-0 z-20 flex h-14 shrink-0 items-center gap-3 border-b border-border bg-card/95 px-3 shadow-xs backdrop-blur-sm sm:px-5">
                    <SidebarTrigger
                        className="size-9"
                        aria-label="Buka atau ciutkan menu"
                    />
                    <div className="min-w-0 flex-1 text-sm">
                        {showBreadcrumbs ? (
                            <div className="truncate text-muted-foreground">
                                <Breadcrumbs breadcrumbs={breadcrumbs} />
                            </div>
                        ) : (
                            <span className="font-medium text-foreground">
                                {breadcrumbs[0]?.title ?? ''}
                            </span>
                        )}
                    </div>
                    <SchoolContextPicker />
                </header>

                <main className="mx-auto w-full max-w-7xl px-4 py-6 sm:px-6">
                    {children}
                </main>
            </SidebarInset>
        </SidebarProvider>
    );
}

function StaffSidebar() {
    const {
        name,
        branding,
        navigation = [],
        auth,
    } = usePage<SharedProps>().props;
    const isActive = usePathActive();
    const currentPath = usePage().url.split('?')[0];
    const appName = branding?.appName || name;
    const getInitials = useInitials();
    // Akordeon: satu grup terbuka; bawaan = grup yang berisi halaman aktif.
    const [openGroup, setOpenGroup] = useState<string | null>(
        () =>
            navigation.find((group) =>
                group.items.some((item) => isActive(item.href)),
            )?.key ?? null,
    );

    return (
        <Sidebar collapsible="icon" aria-label="Menu staf">
            <SidebarHeader className="border-b border-sidebar-border">
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            size="lg"
                            asChild
                            tooltip={appName}
                            className="hover:bg-sidebar-accent"
                        >
                            <Link href={dashboard()}>
                                {branding?.logoUrl ? (
                                    <img
                                        src={branding.logoUrl}
                                        alt=""
                                        className="size-8 shrink-0 rounded-md bg-card object-contain p-0.5"
                                    />
                                ) : (
                                    <span
                                        className="flex size-8 shrink-0 items-center justify-center rounded-md bg-sidebar-primary text-xs font-semibold text-sidebar-primary-foreground"
                                        aria-hidden
                                    >
                                        {getInitials(appName)}
                                    </span>
                                )}
                                <span className="grid min-w-0 leading-tight">
                                    <span className="truncate text-sm font-semibold">
                                        {appName}
                                    </span>
                                    {branding?.foundationName && (
                                        <span className="truncate text-xs text-sidebar-foreground/70">
                                            {branding.foundationName}
                                        </span>
                                    )}
                                </span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent className="bg-islamic-pattern sidebar-scroll">
                <SidebarGroup>
                    <SidebarMenu>
                        <SidebarMenuItem>
                            <SidebarMenuButton
                                asChild
                                tooltip="Beranda"
                                isActive={currentPath === dashboard().url}
                                className={activeButtonClass}
                            >
                                <Link href={dashboard()}>
                                    <House strokeWidth={1.75} />
                                    <span>Beranda</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                        {navigation.map((group) => (
                            <NavGroup
                                key={group.key}
                                group={group}
                                isActive={isActive}
                                open={openGroup === group.key}
                                onOpenChange={(open) =>
                                    setOpenGroup(open ? group.key : null)
                                }
                            />
                        ))}
                    </SidebarMenu>
                </SidebarGroup>
            </SidebarContent>

            <SidebarFooter className="border-t border-sidebar-border">
                {auth.user && <SidebarUser user={auth.user} />}
            </SidebarFooter>
            <SidebarRail />
        </Sidebar>
    );
}

/** Penanda aktif = latar + tebal + garis kiri, tidak hanya warna (antislop R-25). */
const activeButtonClass =
    'relative data-[active=true]:bg-sidebar-accent data-[active=true]:font-semibold data-[active=true]:before:absolute data-[active=true]:before:inset-y-1.5 data-[active=true]:before:left-0 data-[active=true]:before:w-[3px] data-[active=true]:before:rounded-full data-[active=true]:before:bg-sidebar-primary';

function NavGroup({
    group,
    isActive,
    open,
    onOpenChange,
}: {
    group: StaffNavGroup;
    isActive: (href: string) => boolean;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const { state, isMobile } = useSidebar();
    const Icon = groupIcons[group.icon ?? ''] ?? Package;
    const groupActive = group.items.some((item) => isActive(item.href));

    if (state === 'collapsed' && !isMobile) {
        return (
            <SidebarMenuItem>
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <SidebarMenuButton
                            tooltip={group.label}
                            isActive={groupActive}
                            className={activeButtonClass}
                        >
                            <Icon strokeWidth={1.75} />
                            <span>{group.label}</span>
                        </SidebarMenuButton>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent
                        side="right"
                        align="start"
                        className="min-w-56"
                    >
                        <DropdownMenuLabel>{group.label}</DropdownMenuLabel>
                        {group.items.map((item) => (
                            <DropdownMenuItem key={item.href} asChild>
                                <Link
                                    href={item.href}
                                    aria-current={
                                        isActive(item.href) ? 'page' : undefined
                                    }
                                    className={cn(
                                        'flex justify-between gap-3',
                                        isActive(item.href) && 'font-semibold',
                                        item.soon && 'text-muted-foreground',
                                    )}
                                >
                                    {item.label}
                                    {item.soon && <SoonTag />}
                                </Link>
                            </DropdownMenuItem>
                        ))}
                    </DropdownMenuContent>
                </DropdownMenu>
            </SidebarMenuItem>
        );
    }

    return (
        <Collapsible
            asChild
            open={open}
            onOpenChange={onOpenChange}
            className="group/collapsible"
        >
            <SidebarMenuItem>
                <CollapsibleTrigger asChild>
                    <SidebarMenuButton
                        tooltip={group.label}
                        isActive={groupActive}
                        className={cn(
                            activeButtonClass,
                            'data-[active=true]:before:hidden',
                        )}
                    >
                        <Icon strokeWidth={1.75} />
                        <span>{group.label}</span>
                        <ChevronRight
                            className="ml-auto transition-transform duration-200 ease-out group-data-[state=open]/collapsible:rotate-90 motion-reduce:transition-none"
                            strokeWidth={1.75}
                        />
                    </SidebarMenuButton>
                </CollapsibleTrigger>
                <CollapsibleContent className="overflow-hidden data-[state=closed]:animate-collapsible-up data-[state=open]:animate-collapsible-down">
                    <SidebarMenuSub className="border-sidebar-border">
                        {group.items.map((item) => (
                            <NavLeaf
                                key={item.href}
                                item={item}
                                active={isActive(item.href)}
                            />
                        ))}
                    </SidebarMenuSub>
                </CollapsibleContent>
            </SidebarMenuItem>
        </Collapsible>
    );
}

function NavLeaf({ item, active }: { item: StaffNavItem; active: boolean }) {
    const { setOpenMobile } = useSidebar();

    return (
        <SidebarMenuSubItem>
            <SidebarMenuSubButton
                asChild
                isActive={active}
                className={cn(
                    'h-8 text-sidebar-foreground/85 hover:text-sidebar-foreground data-[active=true]:bg-sidebar-accent data-[active=true]:font-semibold data-[active=true]:text-sidebar-foreground',
                    item.soon && 'text-sidebar-foreground/60',
                )}
            >
                <Link
                    href={item.href}
                    aria-current={active ? 'page' : undefined}
                    onClick={() => setOpenMobile(false)}
                >
                    <span className="truncate">{item.label}</span>
                    {item.soon && <SoonTag inverted />}
                </Link>
            </SidebarMenuSubButton>
        </SidebarMenuSubItem>
    );
}

function SoonTag({ inverted = false }: { inverted?: boolean }) {
    return (
        <span
            className={cn(
                'ml-auto shrink-0 rounded px-1.5 py-px text-[10px] font-medium',
                inverted
                    ? 'bg-sidebar-foreground/10 text-sidebar-foreground/70'
                    : 'bg-muted text-muted-foreground',
            )}
        >
            Segera
        </span>
    );
}

function SidebarUser({ user }: { user: NonNullable<Auth['user']> }) {
    const getInitials = useInitials();
    const { isMobile, state } = useSidebar();

    return (
        <SidebarMenu>
            <SidebarMenuItem>
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <SidebarMenuButton
                            size="lg"
                            tooltip={user.name}
                            className="hover:bg-sidebar-accent data-[state=open]:bg-sidebar-accent"
                        >
                            <Avatar className="size-8 rounded-md">
                                <AvatarImage src={user.avatar} alt="" />
                                <AvatarFallback className="rounded-md bg-sidebar-primary text-xs font-semibold text-sidebar-primary-foreground">
                                    {getInitials(user.name)}
                                </AvatarFallback>
                            </Avatar>
                            <span className="grid min-w-0 flex-1 text-left leading-tight">
                                <span className="truncate text-sm font-medium">
                                    {user.name}
                                </span>
                                <span className="truncate text-xs text-sidebar-foreground/70">
                                    Akun & keluar
                                </span>
                            </span>
                            <ChevronsUpDown
                                className="ml-auto size-4"
                                strokeWidth={1.75}
                            />
                        </SidebarMenuButton>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent
                        className="w-56"
                        side={
                            isMobile
                                ? 'top'
                                : state === 'collapsed'
                                  ? 'right'
                                  : 'top'
                        }
                        align="end"
                    >
                        <UserMenuContent user={user} />
                    </DropdownMenuContent>
                </DropdownMenu>
            </SidebarMenuItem>
        </SidebarMenu>
    );
}

function SchoolContextPicker() {
    const { schoolContext } = usePage<SharedProps>().props;

    if (!schoolContext) {
        return null;
    }

    const updateContext = (payload: {
        unit?: string;
        academic_year_id?: number;
    }): void => {
        router.post(SchoolContextController.url(), payload, {
            preserveScroll: true,
        });
    };

    const year = schoolContext.academicYear ?? 'Tahun ajaran';
    // Di layar sempit "2026/2027 Ganjil" dipendekkan menjadi "26/27".
    const shortYear = year.replace(/^20(\d{2})\/20(\d{2}).*$/, '$1/$2');

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="outline"
                    size="sm"
                    className="h-9 shrink-0 gap-1.5 bg-card text-sm"
                    aria-label={`Unit ${schoolContext.unit ?? '-'}, tahun ajaran ${year}. Ganti konteks`}
                >
                    <span className="font-semibold">
                        {schoolContext.unit ?? 'Unit'}
                    </span>
                    <span className="text-muted-foreground">·</span>
                    <span className="hidden sm:inline">{year}</span>
                    <span className="sm:hidden">{shortYear}</span>
                    <ChevronDown
                        className="size-3.5 opacity-70"
                        strokeWidth={1.75}
                    />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="min-w-56">
                <DropdownMenuLabel>Unit</DropdownMenuLabel>
                {schoolContext.units.length === 0 && (
                    <DropdownMenuItem disabled>
                        Belum ada unit aktif
                    </DropdownMenuItem>
                )}
                {schoolContext.units.map((unit) => (
                    <DropdownMenuItem
                        key={unit}
                        onClick={() => updateContext({ unit })}
                        className={cn(
                            schoolContext.unit === unit && 'font-semibold',
                        )}
                    >
                        Unit {unit}
                    </DropdownMenuItem>
                ))}
                <DropdownMenuSeparator />
                <DropdownMenuLabel>Tahun ajaran</DropdownMenuLabel>
                {schoolContext.academicYears.length === 0 && (
                    <DropdownMenuItem disabled>
                        Belum ada tahun ajaran
                    </DropdownMenuItem>
                )}
                {schoolContext.academicYears.map((item) => (
                    <DropdownMenuItem
                        key={item.id}
                        onClick={() =>
                            updateContext({ academic_year_id: item.id })
                        }
                        className={cn(
                            schoolContext.academicYearId === item.id &&
                                'font-semibold',
                        )}
                    >
                        {item.label}
                        {item.isActive && (
                            <span className="ml-auto pl-3 text-xs text-muted-foreground">
                                aktif
                            </span>
                        )}
                    </DropdownMenuItem>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
