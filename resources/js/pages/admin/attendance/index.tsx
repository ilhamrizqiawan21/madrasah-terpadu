import { DateInput } from '@/components/app/date-input';
import { Head, router, useForm } from '@inertiajs/react';
import { Pencil } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import AttendanceController from '@/actions/App/Http/Controllers/Admin/AttendanceController';
import type { DataTableColumn } from '@/components/app/data-table';
import { DataTable } from '@/components/app/data-table';
import { FormDialog } from '@/components/app/form-dialog';
import { PageHeader } from '@/components/app/page-header';
import { StatusBadge } from '@/components/app/status-badge';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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
import { dashboard } from '@/routes/admin';

type DailyRow = {
    student_id: number;
    nis: string;
    name: string;
    status: string;
    status_label: string;
    checked_in_at: string | null;
    checked_out_at: string | null;
    decided_by: string | null;
};

type MonthlyRow = {
    student_id: number;
    nis: string;
    name: string;
    present: number;
    late: number;
    excused: number;
    sick: number;
    absent: number;
};

type Props = {
    mode: 'daily' | 'monthly';
    filters: {
        classroom_id: number | null;
        date: string;
        month: string;
    };
    classrooms: { id: number; name: string }[];
    daily: {
        counts: {
            present: number;
            late: number;
            excused: number;
            sick: number;
            absent: number;
            not_yet: number;
        };
        rows: DailyRow[];
    };
    monthlyRows: MonthlyRow[];
    canCorrect: boolean;
    statusOptions: { value: string; label: string }[];
};

function badgeForStatus(
    status: string,
): 'success' | 'warning' | 'destructive' | 'muted' {
    switch (status) {
        case 'present':
            return 'success';
        case 'late':
        case 'excused':
        case 'sick':
            return 'warning';
        case 'absent':
            return 'destructive';
        default:
            return 'muted';
    }
}

export default function AdminAttendanceIndex({
    mode,
    filters,
    classrooms,
    daily,
    monthlyRows,
    canCorrect,
    statusOptions,
}: Props) {
    const [correctTarget, setCorrectTarget] = useState<DailyRow | null>(null);

    const form = useForm<{
        status: string;
        reason: string;
    }>({
        status: 'present',
        reason: '',
    });

    const visit = (
        changes: Partial<typeof filters & { mode: string }>,
    ): void => {
        router.get(
            AttendanceController.index.url(),
            {
                mode,
                classroom_id: filters.classroom_id ?? undefined,
                date: filters.date,
                month: filters.month,
                ...changes,
            },
            { preserveState: true, preserveScroll: true },
        );
    };

    const submitCorrection = (event: FormEvent): void => {
        event.preventDefault();

        if (!correctTarget) {
            return;
        }

        form.transform((data) => ({
            student_id: correctTarget.student_id,
            school_date: filters.date,
            status: data.status,
            reason: data.reason,
        }));

        form.post(AttendanceController.correct.url(), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setCorrectTarget(null);
            },
        });
    };

    const dailyColumns: DataTableColumn<DailyRow>[] = [
        {
            key: 'name',
            label: 'Siswa',
            render: (row) => (
                <div>
                    <p className="font-medium">{row.name}</p>
                    <p className="text-xs text-muted-foreground">
                        NIS {row.nis}
                    </p>
                </div>
            ),
        },
        {
            key: 'checked_in_at',
            label: 'Masuk',
            align: 'center',
            render: (row) =>
                row.checked_in_at ? `${row.checked_in_at} WITA` : '—',
        },
        {
            key: 'checked_out_at',
            label: 'Pulang',
            align: 'center',
            render: (row) =>
                row.checked_out_at ? `${row.checked_out_at} WITA` : '—',
        },
        {
            key: 'status',
            label: 'Status',
            align: 'center',
            render: (row) => (
                <StatusBadge variant={badgeForStatus(row.status)}>
                    {row.status_label}
                </StatusBadge>
            ),
        },
        ...(canCorrect
            ? [
                  {
                      key: 'actions',
                      label: 'Koreksi',
                      align: 'center' as const,
                      render: (row: DailyRow) => (
                          <Tooltip>
                              <TooltipTrigger asChild>
                                  <Button
                                      type="button"
                                      variant="ghost"
                                      size="icon"
                                      className="size-8"
                                      aria-label={`Koreksi presensi ${row.name}`}
                                      onClick={() => {
                                          form.setData(
                                              'status',
                                              row.status === 'not_yet'
                                                  ? 'present'
                                                  : row.status,
                                          );
                                          form.setData('reason', '');
                                          setCorrectTarget(row);
                                      }}
                                  >
                                      <Pencil className="size-4" />
                                  </Button>
                              </TooltipTrigger>
                              <TooltipContent>Koreksi status</TooltipContent>
                          </Tooltip>
                      ),
                  },
              ]
            : []),
    ];

    const monthlyColumns: DataTableColumn<MonthlyRow>[] = [
        {
            key: 'name',
            label: 'Siswa',
            render: (row) => (
                <div>
                    <p className="font-medium">{row.name}</p>
                    <p className="text-xs text-muted-foreground">
                        NIS {row.nis}
                    </p>
                </div>
            ),
        },
        {
            key: 'present',
            label: 'Hadir',
            align: 'right',
            render: (row) => row.present,
        },
        {
            key: 'late',
            label: 'Terlambat',
            align: 'right',
            render: (row) => row.late,
        },
        {
            key: 'excused',
            label: 'Izin',
            align: 'right',
            render: (row) => row.excused,
        },
        {
            key: 'sick',
            label: 'Sakit',
            align: 'right',
            render: (row) => row.sick,
        },
        {
            key: 'absent',
            label: 'Alpa',
            align: 'right',
            render: (row) => row.absent,
        },
    ];

    const summaryCards = [
        { label: 'Hadir', value: daily.counts.present },
        { label: 'Terlambat', value: daily.counts.late },
        { label: 'Izin', value: daily.counts.excused },
        { label: 'Sakit', value: daily.counts.sick },
        { label: 'Alpa', value: daily.counts.absent },
        { label: 'Belum tap', value: daily.counts.not_yet },
    ];

    return (
        <StaffLayout
            breadcrumbs={[
                { title: 'Beranda', href: dashboard().url },
                {
                    title: 'Rekap presensi',
                    href: AttendanceController.index.url(),
                },
            ]}
        >
            <Head title="Rekap presensi" />

            <div className="space-y-6">
                <PageHeader
                    title="Rekap presensi siswa"
                    description="Pantau kehadiran harian per rombel, koreksi manual oleh wali kelas, dan rekap bulanan."
                    actions={
                        <div className="flex flex-wrap items-center gap-2">
                            <Button
                                type="button"
                                variant={
                                    mode === 'daily' ? 'default' : 'outline'
                                }
                                onClick={() => visit({ mode: 'daily' })}
                            >
                                Harian
                            </Button>
                            <Button
                                type="button"
                                variant={
                                    mode === 'monthly' ? 'default' : 'outline'
                                }
                                onClick={() => visit({ mode: 'monthly' })}
                            >
                                Bulanan
                            </Button>
                        </div>
                    }
                />

                <div className="flex flex-wrap items-end gap-3">
                    <div className="grid gap-1.5">
                        <Label htmlFor="attendance-classroom">Rombel</Label>
                        <Select
                            value={
                                filters.classroom_id !== null
                                    ? String(filters.classroom_id)
                                    : ''
                            }
                            onValueChange={(value) =>
                                visit({ classroom_id: Number(value) })
                            }
                        >
                            <SelectTrigger
                                id="attendance-classroom"
                                className="w-52"
                            >
                                <SelectValue placeholder="Pilih rombel" />
                            </SelectTrigger>
                            <SelectContent>
                                {classrooms.map((c) => (
                                    <SelectItem key={c.id} value={String(c.id)}>
                                        {c.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    {mode === 'daily' ? (
                        <div className="grid gap-1.5">
                            <Label htmlFor="attendance-date">Tanggal</Label>
                            <DateInput
                                id="attendance-date"
                                className="w-44"
                                value={filters.date}
                                onChange={(e) =>
                                    visit({ date: e.target.value })
                                }
                            />
                        </div>
                    ) : (
                        <div className="grid gap-1.5">
                            <Label htmlFor="attendance-month">Bulan</Label>
                            <Input
                                id="attendance-month"
                                type="month"
                                className="w-44"
                                value={filters.month}
                                onChange={(e) =>
                                    visit({ month: e.target.value })
                                }
                            />
                        </div>
                    )}
                </div>

                {mode === 'daily' && (
                    <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                        {summaryCards.map((item) => (
                            <Card key={item.label} className="shadow-xs">
                                <CardContent className="p-4">
                                    <p className="text-xs text-muted-foreground">
                                        {item.label}
                                    </p>
                                    <p className="mt-1 text-2xl font-bold tabular-nums">
                                        {item.value}
                                    </p>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}

                {mode === 'daily' ? (
                    <DataTable
                        columns={dailyColumns}
                        data={daily.rows}
                        rowKey={(row) => row.student_id}
                        empty={{
                            title: 'Belum ada siswa di rombel ini',
                            description:
                                'Pilih rombel lain pada tahun ajaran aktif untuk melihat presensi harian.',
                        }}
                    />
                ) : (
                    <DataTable
                        columns={monthlyColumns}
                        data={monthlyRows}
                        rowKey={(row) => row.student_id}
                        empty={{
                            title: 'Belum ada siswa di rombel ini',
                            description:
                                'Pilih rombel lain pada tahun ajaran aktif untuk melihat rekap bulanan.',
                        }}
                    />
                )}
            </div>

            <FormDialog
                open={correctTarget !== null}
                onOpenChange={(open) => !open && setCorrectTarget(null)}
                title="Koreksi status presensi"
                description={
                    correctTarget
                        ? `${correctTarget.name} (NIS ${correctTarget.nis}) — ${filters.date}`
                        : ''
                }
                submitLabel="Simpan koreksi"
                processing={form.processing}
                onSubmit={submitCorrection}
            >
                <div className="grid gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="correct-status">Status baru</Label>
                        <Select
                            value={form.data.status}
                            onValueChange={(value) =>
                                form.setData('status', value)
                            }
                        >
                            <SelectTrigger id="correct-status">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {statusOptions.map((option) => (
                                    <SelectItem
                                        key={option.value}
                                        value={option.value}
                                    >
                                        {option.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={form.errors.status} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="correct-reason">
                            Alasan koreksi (wajib)
                        </Label>
                        <textarea
                            id="correct-reason"
                            rows={3}
                            value={form.data.reason}
                            onChange={(e) =>
                                form.setData('reason', e.target.value)
                            }
                            placeholder="Contoh: Siswa lupa membawa kartu QR saat masuk."
                            className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        />
                        <InputError message={form.errors.reason} />
                    </div>
                </div>
            </FormDialog>
        </StaffLayout>
    );
}
