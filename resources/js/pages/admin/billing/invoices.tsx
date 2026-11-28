import { Head, Link, router } from '@inertiajs/react';
import { Plus, ReceiptText } from 'lucide-react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import BillingRunController from '@/actions/App/Http/Controllers/Admin/BillingRunController';
import InvoiceController from '@/actions/App/Http/Controllers/Admin/InvoiceController';
import type { DataTableColumn } from '@/components/app/data-table';
import { DataTable } from '@/components/app/data-table';
import { DateInput } from '@/components/app/date-input';
import { DateText } from '@/components/app/date-text';
import { ErrorState } from '@/components/app/error-state';
import { MoneyText } from '@/components/app/money-text';
import { PageHeader } from '@/components/app/page-header';
import { RowActions } from '@/components/app/row-actions';
import type { StatusBadgeVariant } from '@/components/app/status-badge';
import { StatusBadge } from '@/components/app/status-badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import StaffLayout from '@/layouts/staff-layout';
import type { PaginatedResource } from '@/types';

type InvoiceRow = {
    id: number;
    student_id: number;
    student_name: string;
    student_nis: string;
    unit_code: string;
    fee_type_name: string;
    description: string;
    period: string | null;
    due_date: string;
    amount: number;
    adjustment_total: number;
    paid_total: number;
    remaining: number;
    status: 'unpaid' | 'partial' | 'paid' | 'cancelled';
    status_label: string;
    is_overdue: boolean;
};

type Filters = {
    search: string;
    unit_id: string;
    classroom_id: string;
    fee_type_id: string;
    status: string;
    period: string;
    due_from: string;
    due_to: string;
};

type Props = {
    invoices: PaginatedResource<InvoiceRow>;
    summary: {
        total_billed: number;
        total_paid: number;
        total_remaining: number;
    };
    units: { id: number; code: string; name: string }[];
    classrooms: { id: number; unit_id: number; name: string }[];
    feeTypes: { id: number; name: string }[];
    statuses: { value: string; label: string }[];
    filters: Filters;
    canIssue: boolean;
};

const ALL_VALUE = 'semua';

function statusVariant(row: InvoiceRow): StatusBadgeVariant {
    if (row.is_overdue) {
        return 'warning';
    }
    if (row.status === 'paid') {
        return 'success';
    }
    if (row.status === 'partial') {
        return 'warning';
    }
    if (row.status === 'cancelled') {
        return 'muted';
    }

    return 'muted';
}

export default function InvoicesIndex({
    invoices,
    summary,
    units,
    classrooms,
    feeTypes,
    statuses,
    filters,
    canIssue,
}: Props) {
    const [search, setSearch] = useState(filters.search);
    const [loading, setLoading] = useState(false);
    const [loadFailed, setLoadFailed] = useState(false);

    function visitFilters(next: Partial<Filters>, page?: number) {
        const merged = {
            search,
            unit_id: filters.unit_id,
            classroom_id: filters.classroom_id,
            fee_type_id: filters.fee_type_id,
            status: filters.status,
            period: filters.period,
            due_from: filters.due_from,
            due_to: filters.due_to,
            ...next,
        };

        router.get(
            InvoiceController.index.url({
                query: {
                    search: merged.search || undefined,
                    unit_id: merged.unit_id || undefined,
                    classroom_id: merged.classroom_id || undefined,
                    fee_type_id: merged.fee_type_id || undefined,
                    status: merged.status || undefined,
                    period: merged.period || undefined,
                    due_from: merged.due_from || undefined,
                    due_to: merged.due_to || undefined,
                    page: page && page > 1 ? page : undefined,
                },
            }),
            {},
            {
                preserveState: true,
                replace: true,
                onStart: () => {
                    setLoading(true);
                    setLoadFailed(false);
                },
                onFinish: () => setLoading(false),
                onError: () => setLoadFailed(true),
            },
        );
    }

    const columns: DataTableColumn<InvoiceRow>[] = [
        {
            key: 'student',
            label: 'Siswa',
            render: (row) => (
                <div>
                    <Link
                        href={InvoiceController.studentCard.url(row.student_id)}
                        className="font-medium text-foreground hover:text-primary hover:underline"
                    >
                        {row.student_name}
                    </Link>
                    <div className="text-xs text-muted-foreground tabular-nums">
                        {row.student_nis} · {row.unit_code}
                    </div>
                </div>
            ),
        },
        {
            key: 'description',
            label: 'Tagihan',
            render: (row) => (
                <div>
                    <div className="font-medium text-foreground">
                        {row.description}
                    </div>
                    <div className="text-xs text-muted-foreground">
                        {row.fee_type_name}
                    </div>
                </div>
            ),
        },
        {
            key: 'due_date',
            label: 'Jatuh tempo',
            render: (row) => (
                <div className="space-y-0.5">
                    <DateText value={row.due_date} />
                    {row.is_overdue && (
                        <div className="text-xs font-medium text-destructive">
                            Menunggak
                        </div>
                    )}
                </div>
            ),
        },
        {
            key: 'amount',
            label: 'Tagihan',
            align: 'right',
            render: (row) => (
                <MoneyText amount={row.amount - row.adjustment_total} />
            ),
        },
        {
            key: 'remaining',
            label: 'Sisa',
            align: 'right',
            render: (row) => (
                <MoneyText amount={row.remaining} className="font-medium" />
            ),
        },
        {
            key: 'status',
            label: 'Status',
            align: 'center',
            render: (row) => (
                <StatusBadge variant={statusVariant(row)}>
                    {row.is_overdue ? 'Menunggak' : row.status_label}
                </StatusBadge>
            ),
        },
        {
            key: 'actions',
            label: 'Aksi',
            align: 'center',
            render: (row) => (
                <RowActions
                    itemName={row.description}
                    viewHref={InvoiceController.show.url(row.id)}
                >
                    <Tooltip>
                        <TooltipTrigger asChild>
                            <Button
                                variant="ghost"
                                size="icon"
                                asChild
                                aria-label={`Kartu tagihan ${row.student_name}`}
                            >
                                <Link
                                    href={InvoiceController.studentCard.url(
                                        row.student_id,
                                    )}
                                >
                                    <ReceiptText
                                        className="size-4"
                                        strokeWidth={1.75}
                                    />
                                </Link>
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent>Kartu tagihan siswa</TooltipContent>
                    </Tooltip>
                </RowActions>
            ),
        },
    ];

    const hasActiveFilters =
        filters.search !== '' ||
        filters.unit_id !== '' ||
        filters.classroom_id !== '' ||
        filters.fee_type_id !== '' ||
        filters.status !== '' ||
        filters.period !== '' ||
        filters.due_from !== '' ||
        filters.due_to !== '';

    return (
        <StaffLayout
            breadcrumbs={[
                {
                    title: 'Keuangan Siswa',
                    href: InvoiceController.index.url(),
                },
                { title: 'Tagihan', href: InvoiceController.index.url() },
            ]}
        >
            <Head title="Tagihan" />

            <div className="space-y-6">
                <PageHeader
                    title="Tagihan"
                    description="Daftar tagihan siswa, saringan tunggakan, dan kartu tagihan per siswa."
                    actions={
                        canIssue ? (
                            <Button asChild>
                                <Link href={BillingRunController.create.url()}>
                                    <Plus
                                        className="size-4"
                                        strokeWidth={1.75}
                                    />
                                    Terbitkan tagihan
                                </Link>
                            </Button>
                        ) : undefined
                    }
                />

                <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <div className="rounded-lg border border-border bg-card p-4">
                        <div className="text-xs text-muted-foreground">
                            Total tagihan (aktif)
                        </div>
                        <div className="mt-1 text-lg font-semibold">
                            <MoneyText amount={summary.total_billed} />
                        </div>
                    </div>
                    <div className="rounded-lg border border-border bg-card p-4">
                        <div className="text-xs text-muted-foreground">
                            Sudah terbayar
                        </div>
                        <div className="mt-1 text-lg font-semibold text-primary">
                            <MoneyText amount={summary.total_paid} />
                        </div>
                    </div>
                    <div className="rounded-lg border border-border bg-card p-4">
                        <div className="text-xs text-muted-foreground">
                            Sisa piutang
                        </div>
                        <div className="mt-1 text-lg font-semibold">
                            <MoneyText amount={summary.total_remaining} />
                        </div>
                    </div>
                </div>

                <div className="flex flex-wrap items-center gap-3">
                    <form
                        className="w-full sm:w-56"
                        onSubmit={(e) => {
                            e.preventDefault();
                            visitFilters({ search }, 1);
                        }}
                    >
                        <Input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Cari nama atau NIS..."
                            aria-label="Cari siswa"
                        />
                    </form>

                    <Select
                        value={filters.unit_id || ALL_VALUE}
                        onValueChange={(value) =>
                            visitFilters(
                                {
                                    unit_id: value === ALL_VALUE ? '' : value,
                                    classroom_id: '',
                                },
                                1,
                            )
                        }
                    >
                        <SelectTrigger
                            className="w-full sm:w-36"
                            aria-label="Filter unit"
                        >
                            <SelectValue placeholder="Semua unit" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL_VALUE}>
                                Semua unit
                            </SelectItem>
                            {units.map((u) => (
                                <SelectItem key={u.id} value={String(u.id)}>
                                    {u.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    <Select
                        value={filters.classroom_id || ALL_VALUE}
                        onValueChange={(value) =>
                            visitFilters(
                                {
                                    classroom_id:
                                        value === ALL_VALUE ? '' : value,
                                },
                                1,
                            )
                        }
                    >
                        <SelectTrigger
                            className="w-full sm:w-36"
                            aria-label="Filter rombel"
                        >
                            <SelectValue placeholder="Semua rombel" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL_VALUE}>
                                Semua rombel
                            </SelectItem>
                            {classrooms
                                .filter(
                                    (c) =>
                                        filters.unit_id === '' ||
                                        String(c.unit_id) === filters.unit_id,
                                )
                                .map((c) => (
                                    <SelectItem key={c.id} value={String(c.id)}>
                                        {c.name}
                                    </SelectItem>
                                ))}
                        </SelectContent>
                    </Select>

                    <Select
                        value={filters.fee_type_id || ALL_VALUE}
                        onValueChange={(value) =>
                            visitFilters(
                                {
                                    fee_type_id:
                                        value === ALL_VALUE ? '' : value,
                                },
                                1,
                            )
                        }
                    >
                        <SelectTrigger
                            className="w-full sm:w-44"
                            aria-label="Filter jenis tagihan"
                        >
                            <SelectValue placeholder="Semua jenis" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL_VALUE}>
                                Semua jenis
                            </SelectItem>
                            {feeTypes.map((ft) => (
                                <SelectItem key={ft.id} value={String(ft.id)}>
                                    {ft.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    <Select
                        value={filters.status || ALL_VALUE}
                        onValueChange={(value) =>
                            visitFilters(
                                { status: value === ALL_VALUE ? '' : value },
                                1,
                            )
                        }
                    >
                        <SelectTrigger
                            className="w-full sm:w-40"
                            aria-label="Filter status"
                        >
                            <SelectValue placeholder="Semua status" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL_VALUE}>
                                Semua status
                            </SelectItem>
                            {statuses.map((st) => (
                                <SelectItem key={st.value} value={st.value}>
                                    {st.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    <Input
                        type="month"
                        value={filters.period}
                        onChange={(e) =>
                            visitFilters({ period: e.target.value }, 1)
                        }
                        className="w-full sm:w-40"
                        aria-label="Filter periode"
                    />

                    <div className="w-full sm:w-40">
                        <DateInput
                            value={filters.due_from}
                            onChange={(e) =>
                                visitFilters({ due_from: e.target.value }, 1)
                            }
                            aria-label="Jatuh tempo dari"
                        />
                    </div>

                    <div className="w-full sm:w-40">
                        <DateInput
                            value={filters.due_to}
                            onChange={(e) =>
                                visitFilters({ due_to: e.target.value }, 1)
                            }
                            aria-label="Jatuh tempo sampai"
                        />
                    </div>

                    {hasActiveFilters && (
                        <Button
                            type="button"
                            variant="ghost"
                            onClick={() => {
                                setSearch('');
                                visitFilters(
                                    {
                                        search: '',
                                        unit_id: '',
                                        classroom_id: '',
                                        fee_type_id: '',
                                        status: '',
                                        period: '',
                                        due_from: '',
                                        due_to: '',
                                    },
                                    1,
                                );
                            }}
                        >
                            Atur ulang
                        </Button>
                    )}
                </div>

                {loadFailed ? (
                    <ErrorState
                        title="Gagal memuat daftar tagihan"
                        description="Periksa koneksi Anda lalu coba muat ulang."
                        onRetry={() => visitFilters({})}
                    />
                ) : (
                    <DataTable
                        columns={columns}
                        data={invoices.data}
                        rowKey={(row) => row.id}
                        loading={loading}
                        pagination={{
                            currentPage: invoices.meta.current_page,
                            lastPage: invoices.meta.last_page,
                            perPage: invoices.meta.per_page,
                            total: invoices.meta.total,
                            from: invoices.meta.from,
                            to: invoices.meta.to,
                        }}
                        onPageChange={(page) => visitFilters({}, page)}
                        empty={{
                            title: hasActiveFilters
                                ? 'Tidak ada tagihan yang cocok'
                                : 'Belum ada tagihan',
                            description: hasActiveFilters
                                ? 'Ubah filter atau kata kunci pencarian untuk melihat tagihan lainnya.'
                                : 'Terbitkan tagihan siswa melalui tombol Terbitkan tagihan di atas.',
                            action:
                                !hasActiveFilters && canIssue ? (
                                    <Button asChild>
                                        <Link
                                            href={BillingRunController.create.url()}
                                        >
                                            Terbitkan tagihan
                                        </Link>
                                    </Button>
                                ) : undefined,
                        }}
                    />
                )}
            </div>
        </StaffLayout>
    );
}

InvoicesIndex.layout = (page: ReactNode) => page;
