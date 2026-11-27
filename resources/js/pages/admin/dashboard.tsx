import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowUpRight,
    Building2,
    GraduationCap,
    Upload,
    UserCheck,
    Users,
} from 'lucide-react';
import ClassroomController from '@/actions/App/Http/Controllers/Admin/ClassroomController';
import GuardianController from '@/actions/App/Http/Controllers/Admin/GuardianController';
import StudentController from '@/actions/App/Http/Controllers/Admin/StudentController';
import StudentImportController from '@/actions/App/Http/Controllers/Admin/StudentImportController';
import StudentMutationController from '@/actions/App/Http/Controllers/Admin/StudentMutationController';
import StudentPromotionController from '@/actions/App/Http/Controllers/Admin/StudentPromotionController';
import { DateText } from '@/components/app/date-text';
import { EmptyState } from '@/components/app/empty-state';
import type { StatusBadgeVariant } from '@/components/app/status-badge';
import { StatusBadge } from '@/components/app/status-badge';
import { Button } from '@/components/ui/button';
import StaffLayout from '@/layouts/staff-layout';
import { dashboard } from '@/routes/admin';
import type { Auth } from '@/types';

type MutationSummary = {
    id: number;
    type: { value: 'in' | 'out' | 'graduated'; label: string };
    mutated_on: string;
    reason: string;
    student_name: string;
    student_nis: string;
    unit_code: string;
};

type Props = {
    summary: {
        unit_code: string | null;
        unit_name: string | null;
        academic_year: string | null;
        active_students: number | null;
        active_classrooms: number | null;
        guardians: number | null;
    };
    recentMutations: MutationSummary[];
    permissions: {
        view_students: boolean;
        manage_students: boolean;
        manage_classrooms: boolean;
        manage_guardians: boolean;
        import_students: boolean;
    };
};

const mutationVariant: Record<
    MutationSummary['type']['value'],
    StatusBadgeVariant
> = {
    in: 'success',
    out: 'warning',
    graduated: 'default',
};

export default function AdminDashboard({
    summary,
    recentMutations,
    permissions,
}: Props) {
    const { auth } = usePage<{ auth: Auth }>().props;

    const cards = [
        ...(summary.active_students !== null
            ? [
                  {
                      key: 'students',
                      label: 'Siswa aktif',
                      value: summary.active_students,
                      href: StudentController.index.url(),
                      icon: Users,
                  },
              ]
            : []),
        ...(summary.active_classrooms !== null
            ? [
                  {
                      key: 'classrooms',
                      label: 'Rombel aktif',
                      value: summary.active_classrooms,
                      href: permissions.manage_classrooms
                          ? ClassroomController.index.url()
                          : StudentController.index.url(),
                      icon: Building2,
                  },
              ]
            : []),
        ...(summary.guardians !== null
            ? [
                  {
                      key: 'guardians',
                      label: 'Wali terhubung',
                      value: summary.guardians,
                      href: GuardianController.index.url(),
                      icon: UserCheck,
                  },
              ]
            : []),
    ];

    return (
        <StaffLayout breadcrumbs={[{ title: 'Beranda', href: dashboard() }]}>
            <Head title="Beranda Staf" />

            <div className="space-y-6">
                <section className="bg-islamic-pattern rounded-lg bg-primary p-5 text-primary-foreground shadow-xs sm:p-6">
                    <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div className="space-y-1">
                            <p className="text-xs font-medium text-primary-foreground/80">
                                {summary.unit_code
                                    ? `Unit ${summary.unit_code} · ${summary.unit_name ?? ''}`
                                    : 'Semua unit'}
                                {summary.academic_year
                                    ? ` — ${summary.academic_year}`
                                    : ''}
                            </p>
                            <h1 className="text-xl font-semibold tracking-tight sm:text-2xl">
                                Assalamu&apos;alaikum, {auth.user.name}
                            </h1>
                        </div>

                        <div className="flex flex-wrap gap-2">
                            {permissions.manage_students && (
                                <Button
                                    size="sm"
                                    variant="secondary"
                                    asChild
                                    className="bg-card text-foreground hover:bg-card/90"
                                >
                                    <Link href={StudentController.index()}>
                                        Kelola siswa
                                    </Link>
                                </Button>
                            )}
                            {permissions.import_students && (
                                <Button
                                    size="sm"
                                    variant="ghost"
                                    asChild
                                    className="border border-primary-foreground/25 bg-primary-foreground/10 text-primary-foreground hover:bg-primary-foreground/20 hover:text-primary-foreground"
                                >
                                    <Link
                                        href={StudentImportController.create()}
                                    >
                                        <Upload className="size-4" />
                                        Impor Excel
                                    </Link>
                                </Button>
                            )}
                            {permissions.manage_students && (
                                <Button
                                    size="sm"
                                    variant="ghost"
                                    asChild
                                    className="border border-primary-foreground/25 bg-primary-foreground/10 text-primary-foreground hover:bg-primary-foreground/20 hover:text-primary-foreground"
                                >
                                    <Link
                                        href={StudentPromotionController.index()}
                                    >
                                        <GraduationCap className="size-4" />
                                        Kenaikan kelas
                                    </Link>
                                </Button>
                            )}
                        </div>
                    </div>
                </section>

                {cards.length > 0 && (
                    <div className="grid gap-4 sm:grid-cols-3">
                        {cards.map((card) => {
                            const Icon = card.icon;

                            return (
                                <Link
                                    key={card.key}
                                    href={card.href}
                                    className="group flex items-center justify-between rounded-lg border border-border bg-card p-4 shadow-xs transition-colors hover:border-primary/40"
                                >
                                    <div className="space-y-1">
                                        <p className="text-sm text-muted-foreground">
                                            {card.label}
                                        </p>
                                        <p className="text-2xl font-semibold tabular-nums">
                                            {card.value.toLocaleString('id-ID')}
                                        </p>
                                    </div>
                                    <div className="flex size-10 items-center justify-center rounded-lg bg-primary/10 text-primary transition-colors group-hover:bg-primary group-hover:text-primary-foreground">
                                        <Icon
                                            className="size-5"
                                            strokeWidth={1.75}
                                        />
                                    </div>
                                </Link>
                            );
                        })}
                    </div>
                )}

                {permissions.manage_students && (
                    <section className="space-y-3">
                        <div className="flex items-center justify-between gap-2">
                            <h2 className="text-base font-semibold">
                                Mutasi siswa terbaru
                            </h2>
                            <Button variant="ghost" size="sm" asChild>
                                <Link href={StudentMutationController.index()}>
                                    Lihat semua
                                    <ArrowUpRight className="size-4" />
                                </Link>
                            </Button>
                        </div>

                        {recentMutations.length === 0 ? (
                            <EmptyState
                                title="Belum ada catatan mutasi"
                                description="Catatan mutasi masuk, keluar, dan kelulusan pada unit terpilih akan tampil di sini."
                            />
                        ) : (
                            <ul className="divide-y divide-border rounded-lg border border-border bg-card shadow-xs">
                                {recentMutations.map((item) => (
                                    <li
                                        key={item.id}
                                        className="flex flex-wrap items-center justify-between gap-2 px-4 py-3 text-sm"
                                    >
                                        <div>
                                            <div className="font-medium">
                                                {item.student_name}
                                            </div>
                                            <div className="text-xs text-muted-foreground">
                                                NIS {item.student_nis} ·{' '}
                                                {item.reason}
                                            </div>
                                        </div>
                                        <div className="flex items-center gap-3">
                                            <StatusBadge
                                                variant={
                                                    mutationVariant[
                                                        item.type.value
                                                    ]
                                                }
                                            >
                                                {item.type.label}
                                            </StatusBadge>
                                            <span className="text-xs text-muted-foreground">
                                                <DateText
                                                    value={item.mutated_on}
                                                />
                                            </span>
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </section>
                )}
            </div>
        </StaffLayout>
    );
}
