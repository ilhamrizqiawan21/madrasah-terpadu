import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Eye } from 'lucide-react';
import type { ReactNode } from 'react';
import InvoiceController from '@/actions/App/Http/Controllers/Admin/InvoiceController';
import { DateText } from '@/components/app/date-text';
import { EmptyState } from '@/components/app/empty-state';
import { MoneyText } from '@/components/app/money-text';
import { PageHeader } from '@/components/app/page-header';
import { StatusBadge } from '@/components/app/status-badge';
import { Button } from '@/components/ui/button';
import StaffLayout from '@/layouts/staff-layout';

type StudentInvoiceRow = {
    id: number;
    fee_type_name: string;
    description: string;
    due_date: string;
    amount: number;
    adjustment_total: number;
    paid_total: number;
    remaining: number;
    status: string;
    status_label: string;
    is_overdue: boolean;
};

type Props = {
    student: {
        id: number;
        nis: string;
        name: string;
        unit_name: string;
    };
    summary: {
        total_billed: number;
        total_paid: number;
        total_remaining: number;
    };
    invoices: StudentInvoiceRow[];
};

export default function StudentBillingCardPage({
    student,
    summary,
    invoices,
}: Props) {
    return (
        <StaffLayout
            breadcrumbs={[
                { title: 'Keuangan', href: InvoiceController.index.url() },
                { title: 'Tagihan', href: InvoiceController.index.url() },
                {
                    title: student.name,
                    href: InvoiceController.studentCard.url(student.id),
                },
            ]}
        >
            <Head title={`Kartu tagihan — ${student.name}`} />

            <div className="space-y-6">
                <PageHeader
                    title={`Kartu tagihan — ${student.name}`}
                    description={`NIS ${student.nis} · ${student.unit_name}`}
                    actions={
                        <Button variant="outline" asChild>
                            <Link href={InvoiceController.index.url()}>
                                <ArrowLeft
                                    className="size-4"
                                    strokeWidth={1.75}
                                />
                                Kembali ke daftar tagihan
                            </Link>
                        </Button>
                    }
                />

                <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <div className="rounded-lg border border-border bg-card p-4">
                        <div className="text-xs text-muted-foreground">
                            Total tagihan
                        </div>
                        <div className="mt-1 text-xl font-semibold">
                            <MoneyText amount={summary.total_billed} />
                        </div>
                    </div>
                    <div className="rounded-lg border border-border bg-card p-4">
                        <div className="text-xs text-muted-foreground">
                            Terbayar
                        </div>
                        <div className="mt-1 text-xl font-semibold text-primary">
                            <MoneyText amount={summary.total_paid} />
                        </div>
                    </div>
                    <div className="rounded-lg border border-border bg-card p-4">
                        <div className="text-xs text-muted-foreground">
                            Sisa tagihan
                        </div>
                        <div className="mt-1 text-xl font-semibold">
                            <MoneyText amount={summary.total_remaining} />
                        </div>
                    </div>
                </div>

                {invoices.length === 0 ? (
                    <EmptyState
                        title="Belum ada tagihan untuk siswa ini"
                        description="Tagihan yang diterbitkan untuk siswa ini akan tampil di sini."
                    />
                ) : (
                    <div className="overflow-x-auto rounded-lg border border-border bg-card shadow-xs">
                        <table className="w-full text-sm">
                            <thead className="border-b border-border bg-muted/50 text-xs text-muted-foreground">
                                <tr>
                                    <th className="px-3 py-2.5 text-left font-medium">
                                        Tagihan
                                    </th>
                                    <th className="px-3 py-2.5 text-left font-medium">
                                        Jatuh tempo
                                    </th>
                                    <th className="px-3 py-2.5 text-right font-medium">
                                        Tagihan
                                    </th>
                                    <th className="px-3 py-2.5 text-right font-medium">
                                        Terbayar
                                    </th>
                                    <th className="px-3 py-2.5 text-right font-medium">
                                        Sisa
                                    </th>
                                    <th className="px-3 py-2.5 text-center font-medium">
                                        Status
                                    </th>
                                    <th className="px-3 py-2.5 text-center font-medium">
                                        Detail
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {invoices.map((inv) => (
                                    <tr key={inv.id}>
                                        <td className="px-3 py-2">
                                            <div className="font-medium">
                                                {inv.description}
                                            </div>
                                            <div className="text-xs text-muted-foreground">
                                                {inv.fee_type_name}
                                            </div>
                                        </td>
                                        <td className="px-3 py-2">
                                            <DateText value={inv.due_date} />
                                        </td>
                                        <td className="px-3 py-2 text-right">
                                            <MoneyText
                                                amount={
                                                    inv.amount -
                                                    inv.adjustment_total
                                                }
                                            />
                                        </td>
                                        <td className="px-3 py-2 text-right">
                                            <MoneyText
                                                amount={inv.paid_total}
                                            />
                                        </td>
                                        <td className="px-3 py-2 text-right font-medium">
                                            <MoneyText amount={inv.remaining} />
                                        </td>
                                        <td className="px-3 py-2 text-center">
                                            <StatusBadge
                                                variant={
                                                    inv.is_overdue
                                                        ? 'warning'
                                                        : inv.status === 'paid'
                                                          ? 'success'
                                                          : 'muted'
                                                }
                                            >
                                                {inv.is_overdue
                                                    ? 'Menunggak'
                                                    : inv.status_label}
                                            </StatusBadge>
                                        </td>
                                        <td className="px-3 py-2 text-center">
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                asChild
                                                aria-label={`Lihat ${inv.description}`}
                                            >
                                                <Link
                                                    href={InvoiceController.show.url(
                                                        inv.id,
                                                    )}
                                                >
                                                    <Eye
                                                        className="size-4"
                                                        strokeWidth={1.75}
                                                    />
                                                </Link>
                                            </Button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </StaffLayout>
    );
}

StudentBillingCardPage.layout = (page: ReactNode) => page;
