import { Link, usePage } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';
import { home } from '@/routes';
import type { AuthLayoutProps, Branding } from '@/types';

type SharedProps = {
    branding?: Branding;
};

export default function AuthSimpleLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    const { branding } = usePage<SharedProps>().props;
    const foundationName =
        branding?.foundationName ?? 'Yayasan Amal Jaya Berkah';
    const appName = branding?.appName ?? 'Madrasah Terpadu';

    return (
        <div className="bg-islamic-pattern-subtle flex min-h-svh flex-col items-center justify-center gap-6 bg-background p-6 md:p-10">
            <div className="w-full max-w-sm">
                <div className="flex flex-col gap-6">
                    <div className="flex flex-col items-center gap-3 text-center">
                        <Link
                            href={home()}
                            className="flex flex-col items-center gap-2 font-medium"
                        >
                            {branding?.logoUrl ? (
                                <img
                                    src={branding.logoUrl}
                                    alt={foundationName}
                                    width={44}
                                    height={44}
                                    className="size-11 rounded-md object-contain"
                                />
                            ) : (
                                <div className="flex size-11 items-center justify-center rounded-md bg-primary text-primary-foreground">
                                    <AppLogoIcon className="size-6 fill-current" />
                                </div>
                            )}
                            <span className="text-sm font-semibold text-foreground">
                                {foundationName}
                            </span>
                            <span className="text-xs text-muted-foreground">
                                {appName}
                            </span>
                        </Link>

                        <div className="space-y-1.5 pt-2">
                            <h1 className="text-xl font-semibold">{title}</h1>
                            <p className="text-sm text-muted-foreground">
                                {description}
                            </p>
                        </div>
                    </div>

                    <div className="rounded-lg border border-border bg-card p-6 shadow-xs">
                        {children}
                    </div>
                </div>
            </div>
        </div>
    );
}
