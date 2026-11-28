import { Head, Link, router, useForm } from '@inertiajs/react';
import { AlertTriangle, ShieldCheck } from 'lucide-react';
import { useState } from 'react';
import ExamDispensationController from '@/actions/App/Http/Controllers/Admin/ExamDispensationController';
import ExamEligibilityController from '@/actions/App/Http/Controllers/Admin/ExamEligibilityController';
import ExamPeriodController from '@/actions/App/Http/Controllers/Admin/ExamPeriodController';
import InvoiceController from '@/actions/App/Http/Controllers/Admin/InvoiceController';
import type { DataTableColumn } from '@/components/app/data-table';
import { DataTable } from '@/components/app/data-table';
import { DateText } from '@/components/app/date-text';
import { ExportButtons } from '@/components/app/export-buttons';
import { FormDialog } from '@/components/app/form-dialog';
import { MoneyText } from '@/components/app/money-text';
import { PageHeader } from '@/components/app/page-header';
import type { StatusBadgeVariant } from '@/components/app/status-badge';
import { StatusBadge } from '@/components/app/status-badge';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import StaffLayout from '@/layouts/staff-layout';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes/admin';

type EligibilityRow = {
    student_id: number;
    nis: string;
    name: string;
    classroom_id: number;
    classroom_name: string;
    status: 'eligible' | 'ineligible' | 'dispensed';
    status_label: string;
    blocking_count: number;
    blocking_amount: number;
    dispensation_id: number | null;
    dispensation_reason: string | null;
};

type Props = {
    period: {
        id: number;
        name: string;
        unit_code: string;
        unit_name: string;
        delivery: { value: 'cbt' | 'paper'; label: string };
        starts_on: string;
        ends_on: string;
        status: { value: string; label: string };
    };
    classrooms: { id: number; name: string }[];
    summary: {
        total: number;
        eligible: number;
        ineligible: number;
        dispensed: number;
    };
    rows: EligibilityRow[];
    filters: { classroom_id: string; status: string; search: string };
    canGrantDispensation: boolean;
};

const ALL_VALUE = 'semua';

const STATUS_BADGE: Record<EligibilityRow['status'], StatusBadgeVariant> = {
    eligible: 'success',
    ineligible: 'destructive',
    dispensed: 'warning',
};

export default function ExamEligibilityIndex({
    period,
    classrooms,
    summary,
    rows,
    filters,
    canGrantDispensation,
}: Props) {
    const [loading, setLoading] = useState(false);
    const [search, setSearch] = useState(filters.search);
    const [grantTarget, setGrantTarget] = useState<EligibilityRow | null>(null);

    const dispensationForm = useForm({
        student_id: '',
        reason: '',
    });

    function buildQuery(next: Partial<Props['filters']>) {
        const merged = {
            classroom_id: filters.classroom_id,
            status: filters.status,
            search,
            ...next,
        };
        const params: Record<string, string> = {};
        if (merged.classroom_id !== '' && merged.classroom_id !== ALL_VALUE) {
            params.classroom_id = merged.classroom_id;
        }
        if (merged.status !== '' && merged.status !== ALL_VALUE) {
            params.status = merged.status;
        }
        if (merged.search.trim() !== '') {
            params.search = merged.search.trim();
        }
        return params;
    }

    function visitFilters(next: Partial<Props['filters']>) {
        router.get(
            ExamEligibilityController.index({ examPeriod: period.id }),
            buildQuery(next),
            {
                preserveScroll: true,
                preserveState: true,
                onStart: () => setLoading(true),
                onFinish: () => setLoading(false),
            },
        );
    }

    const queryParams = buildQuery({});
    const pdfHref = ExamEligibilityController.exportPdf.url(
        { examPeriod: period.id },
        { query: queryParams },
    );
    const excelHref = ExamEligibilityController.exportExcel.url(
        { examPeriod: period.id },
        { query: queryParams },
    );

    const columns: DataTableColumn<EligibilityRow>[] = [
        {
            key: 'student',
            label: 'Siswa',
            render: (row) => (
                <div>
                    <div className="font-medium">{row.name}</div>
                    <div className="text-xs text-muted-foreground">
                        {row.nis} · {row.classroom_name}
                    </div>
                </div>
            ),
        },
        {
            key: 'status',
            label: 'Kelayakan',
            align: 'center',
            render: (row) => (
                <StatusBadge variant={STATUS_BADGE[row.status]}>
                    {row.status_label}
                </StatusBadge>
            ),
        },
        {
            key: 'blocking',
            label: 'Tagihan syarat tertunggak',
            align: 'right',
            render: (row) =>
                row.blocking_count > 0 ? (
                    <div className="text-right">
                        <div className="font-medium text-destructive">
                            <MoneyText amount={row.blocking_amount} />
                        </div>
                        <div className="text-xs text-muted-foreground">
                            {row.blocking_count} tagihan
                        </div>
                    </div>
                ) : (
                    <span className="text-muted-foreground">—</span>
                ),
        },
        {
            key: 'note',
            label: 'Keterangan',
            render: (row) => (
                <span className="text-xs text-muted-foreground">
                    {row.dispensation_reason ?? '—'}
                </span>
            ),
        },
        ...(canGrantDispensation
            ? [
                  {
                      key: 'actions',
                      label: 'Aksi',
                      align: 'center' as const,
                      render: (row: EligibilityRow) => (
                          <div className="inline-flex items-center gap-1">
                              <Button size="sm" variant="ghost" asChild>
                                  <Link
                                      href={InvoiceController.studentCard({
                                          student: row.student_id,
                                      })}
                                  >
                                      Tagihan
                                  </Link>
                              </Button>
                              {row.status === 'ineligible' && (
                                  <Button
                                      type="button"
                                      size="sm"
                                      variant="outline"
                                      onClick={() => {
                                          dispensationForm.clearErrors();
                                          dispensationForm.setData({
                                              student_id: String(
                                                  row.student_id,
                                              ),
                                              reason: '',
                                          });
                                          setGrantTarget(row);
                                      }}
                                  >
                                      <ShieldCheck className="size-3.5" />
                                      Dispensasi
                                  </Button>
                              )}
                          </div>
                      ),
                  },
              ]
            : []),
    ];

    const tabs = [
        { key: '', label: 'Semua', count: summary.total },
        { key: 'ineligible', label: 'Belum layak', count: summary.ineligible },
        { key: 'dispensed', label: 'Dispensasi', count: summary.dispensed },
        { key: 'eligible', label: 'Layak', count: summary.eligible },
    ];

    return (
        <StaffLayout
            breadcrumbs={[
                { title: 'Beranda', href: dashboard() },
                {
                    title: 'Kelayakan ujian',
                    href: ExamPeriodController.index(),
                },
                {
                    title: period.name,
                    href: ExamEligibilityController.index({
                        examPeriod: period.id,
                    }),
                },
            ]}
        >
            <Head title={`Kelayakan — ${period.name}`} />

            <div className="space-y-6">
                <PageHeader
                    title={`Daftar kelayakan — ${period.name}`}
                    description={`${period.unit_code} · ${period.delivery.label} · ${period.status.label}`}
                    actions={
                        <div className="flex items-center gap-2">
                            <ExportButtons
                                pdfHref={pdfHref}
                                excelHref={excelHref}
                                disabled={rows.length === 0}
                            />
                        </div>
                    }
                />

                {period.delivery.value === 'paper' && (
                    <div
                        role="status"
                        className="flex items-start gap-3 rounded-xl border border-border bg-muted/40 px-4 py-3 text-sm"
                    >
                        <AlertTriangle className="mt-0.5 size-4 shrink-0 text-warning" />
                        <div>
                            <p className="font-medium">
                                Peringatan ujian kertas (
                                <DateText value={period.starts_on} /> –{' '}
                                <DateText value={period.ends_on} />)
                            </p>
                            <p className="text-xs text-muted-foreground">
                                Siswa yang belum layak tidak diblokir sistem
                                karena pelaksanaan kertas. Gunakan ekspor PDF
                                atau Excel sebagai daftar pemeriksaan panitia.
                            </p>
                        </div>
                    </div>
                )}

                <div className="flex flex-wrap items-center gap-2">
                    {tabs.map((tab) => {
                        const active = filters.status === tab.key;
                        return (
                            <button
                                key={tab.key || 'all'}
                                type="button"
                                onClick={() =>
                                    visitFilters({ status: tab.key })
                                }
                                aria-pressed={active}
                                className={cn(
                                    'inline-flex items-center gap-1.5 rounded-lg border px-3 py-1.5 text-xs font-medium transition-colors',
                                    active
                                        ? 'border-primary bg-primary text-primary-foreground'
                                        : 'border-border bg-card text-foreground hover:bg-muted/50',
                                )}
                            >
                                <span>{tab.label}</span>
                                <span
                                    className={cn(
                                        'tabular-nums',
                                        active
                                            ? 'text-primary-foreground/85'
                                            : 'text-muted-foreground',
                                    )}
                                >
                                    ({tab.count})
                                </span>
                            </button>
                        );
                    })}
                </div>

                <div className="flex flex-wrap items-center gap-3">
                    <Select
                        value={
                            filters.classroom_id === ''
                                ? ALL_VALUE
                                : filters.classroom_id
                        }
                        onValueChange={(value) =>
                            visitFilters({
                                classroom_id: value === ALL_VALUE ? '' : value,
                            })
                        }
                    >
                        <SelectTrigger className="w-48">
                            <SelectValue placeholder="Semua rombel" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL_VALUE}>
                                Semua rombel
                            </SelectItem>
                            {classrooms.map((c) => (
                                <SelectItem key={c.id} value={String(c.id)}>
                                    {c.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    <form
                        className="flex items-center gap-2"
                        onSubmit={(e) => {
                            e.preventDefault();
                            visitFilters({ search });
                        }}
                    >
                        <Input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Cari nama atau NIS..."
                            className="w-56"
                        />
                        <Button type="submit" variant="outline" size="sm">
                            Cari
                        </Button>
                    </form>
                </div>

                <DataTable
                    columns={columns}
                    data={rows}
                    rowKey={(row) => `${row.classroom_id}-${row.student_id}`}
                    loading={loading}
                    empty={{
                        title: 'Tidak ada siswa pada saringan ini',
                        description:
                            'Ubah saringan status atau rombel untuk melihat daftar siswa.',
                    }}
                />
            </div>

            {canGrantDispensation && (
                <FormDialog
                    open={grantTarget !== null}
                    onOpenChange={(open) => {
                        if (!open) {
                            setGrantTarget(null);
                        }
                    }}
                    title={
                        grantTarget
                            ? `Beri dispensasi — ${grantTarget.name}`
                            : 'Beri dispensasi'
                    }
                    onSubmit={(e) => {
                        e.preventDefault();
                        dispensationForm.post(
                            ExamDispensationController.store({
                                examPeriod: period.id,
                            }).url,
                            {
                                preserveScroll: true,
                                onSuccess: () => {
                                    dispensationForm.reset('reason');
                                    setGrantTarget(null);
                                },
                            },
                        );
                    }}
                    processing={dispensationForm.processing}
                    submitLabel="Beri dispensasi"
                >
                    <div className="space-y-3">
                        <div className="space-y-1.5">
                            <Label htmlFor="elig-disp-reason">
                                Alasan dispensasi
                            </Label>
                            <Input
                                id="elig-disp-reason"
                                value={dispensationForm.data.reason}
                                onChange={(e) =>
                                    dispensationForm.setData(
                                        'reason',
                                        e.target.value,
                                    )
                                }
                                placeholder="Mis. Surat pernyataan orang tua"
                            />
                            <InputError
                                message={dispensationForm.errors.reason}
                            />
                        </div>
                    </div>
                </FormDialog>
            )}
        </StaffLayout>
    );
}
