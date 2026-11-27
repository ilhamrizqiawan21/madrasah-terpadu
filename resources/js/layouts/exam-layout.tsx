import { router, usePage } from '@inertiajs/react';
import { LogOut } from 'lucide-react';
import type { ReactNode } from 'react';
import ExamAuthController from '@/actions/App/Http/Controllers/Exam/ExamAuthController';
import { Button } from '@/components/ui/button';

type BrandingProps = {
    appName: string;
    foundationName: string;
    logoUrl: string | null;
};

type ExamLayoutProps = {
    studentName: string;
    studentNis: string;
    classroomName?: string | null;
    children: ReactNode;
};

export default function ExamLayout({
    studentName,
    studentNis,
    classroomName,
    children,
}: ExamLayoutProps) {
    const { branding } = usePage<{ branding: BrandingProps }>().props;

    const logout = (): void => {
        router.post(ExamAuthController.destroy.url());
    };

    return (
        <div className="min-h-screen bg-background text-foreground">
            <header className="border-b border-border bg-card">
                <div className="mx-auto flex max-w-5xl items-center justify-between gap-4 px-4 py-3 sm:px-6">
                    <div className="min-w-0">
                        <p className="truncate text-sm font-semibold">
                            {branding.appName} — Ruang Ujian
                        </p>
                        <p className="truncate text-xs text-muted-foreground">
                            {studentName} · NIS{' '}
                            <span className="tabular-nums">{studentNis}</span>
                            {classroomName ? ` · ${classroomName}` : ''}
                        </p>
                    </div>

                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={logout}
                        className="shrink-0"
                    >
                        <LogOut className="size-4" strokeWidth={1.75} />
                        Keluar
                    </Button>
                </div>
            </header>

            <main className="mx-auto max-w-5xl px-4 py-6 sm:px-6">
                {children}
            </main>
        </div>
    );
}
