import { usePage } from '@inertiajs/react';
import type { SVGAttributes } from 'react';
import { useInitials } from '@/hooks/use-initials';
import type { Branding } from '@/types';

/**
 * Tanda cadangan saat sekolah belum mengunggah logo: inisial nama aplikasi
 * dari Pengaturan > Identitas sekolah, bukan logo pihak lain.
 */
export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    const { branding } = usePage<{ branding?: Branding }>().props;
    const initials = useInitials()(branding?.appName ?? 'Madrasah Terpadu');

    return (
        <svg
            {...props}
            viewBox="0 0 40 40"
            xmlns="http://www.w3.org/2000/svg"
            aria-hidden="true"
        >
            <text
                x="20"
                y="20"
                dominantBaseline="central"
                textAnchor="middle"
                fontSize={initials.length > 1 ? 18 : 22}
                fontWeight={600}
                fill="currentColor"
            >
                {initials}
            </text>
        </svg>
    );
}
