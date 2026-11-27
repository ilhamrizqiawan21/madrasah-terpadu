import type { InertiaLinkProps } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';

export type BreadcrumbItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
};

export type NavItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon | null;
    isActive?: boolean;
};

export type StaffNavItem = {
    label: string;
    route: string;
    href: string;
    permission: string;
    /** Modul belum dibangun; membuka halaman "sedang disiapkan" (ADR-028). */
    soon?: boolean;
};

export type StaffNavGroup = {
    key: string;
    label: string;
    /** Nama ikon grup; dipetakan ke ikon Lucide di layout staf. */
    icon?: string;
    items: StaffNavItem[];
};

export type SchoolContext = {
    unit: string | null;
    units: string[];
    academicYear: string | null;
    academicYearId: number | null;
    academicYears: { id: number; label: string; isActive: boolean }[];
};
