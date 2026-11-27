import { Head, router, useForm } from '@inertiajs/react';
import { Clock, LockOpen, OctagonX } from 'lucide-react';
import type { FormEvent } from 'react';
import { useEffect, useState } from 'react';
import CbtExamController from '@/actions/App/Http/Controllers/Admin/CbtExamController';
import CbtMonitorController from '@/actions/App/Http/Controllers/Admin/CbtMonitorController';
import AdminDashboardController from '@/actions/App/Http/Controllers/Admin/DashboardController';
import { ConfirmDialog } from '@/components/app/confirm-dialog';
import type { DataTableColumn } from '@/components/app/data-table';
import { DataTable } from '@/components/app/data-table';
import { DateText } from '@/components/app/date-text';
import { FormDialog } from '@/components/app/form-dialog';
import { PageHeader } from '@/components/app/page-header';
import type { StatusBadgeVariant } from '@/components/app/status-badge';
import { StatusBadge } from '@/components/app/status-badge';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import StaffLayout from '@/layouts/staff-layout';

type StudentMonitorRow = {
    student_id: number;
    nis: string;
    name: string;
    classroom: string;
    session_id: number | null;
    status: string;
    status_label: string;
    answered_count: number;
    violations_count: number;
    device_type: string | null;
    extra_minutes: number;
    last_seen_at: string | null;
};

type MonitorSnapshot = {
    total_questions: number;
    summary: {
        not_started: number;
        in_progress: number;
        locked: number;
        finished: number;
    };
    students: StudentMonitorRow[];
};

type Props = {
    exam: {
        id: number;
        title: string;
        subject: string;
        status_label: string;
    };
    canControl: boolean;
    initialData: MonitorSnapshot;
};

const statusVariant: Record<string, StatusBadgeVariant> = {
    not_started: 'muted',
    in_progress: 'default',
    locked: 'destructive',
    submitted: 'success',
    auto_closed: 'success',
    force_closed: 'warning',
};

export default function CbtMonitorIndex({
    exam,
    canControl,
    initialData,
}: Props) {
    const [data, setData] = useState<MonitorSnapshot>(initialData);
    const [extraTimeTarget, setExtraTimeTarget] =
        useState<StudentMonitorRow | null>(null);
    const [forceCloseTarget, setForceCloseTarget] =
        useState<StudentMonitorRow | null>(null);
    const [acting, setActing] = useState(false);

    const extraTimeForm = useForm({ minutes: '10' });

    useEffect(() => {
        const timer = window.setInterval(async () => {
            try {
                const res = await fetch(
                    CbtMonitorController.data.url(exam.id),
                    {
                        headers: { Accept: 'application/json' },
                    },
                );
                if (res.ok) {
                    const next = (await res.json()) as MonitorSnapshot;
                    setData(next);
                }
            } catch {
                // Abaikan gagal polling sesaat.
            }
        }, 5000);

        return () => window.clearInterval(timer);
    }, [exam.id]);

    const handleUnlock = (row: StudentMonitorRow): void => {
        if (row.session_id === null) {
            return;
        }

        router.post(
            CbtMonitorController.unlock.url([exam.id, row.session_id]),
            {},
            { preserveScroll: true },
        );
    };

    const submitExtraTime = (event: FormEvent): void => {
        event.preventDefault();
        if (extraTimeTarget?.session_id === null || !extraTimeTarget) {
            return;
        }

        extraTimeForm.transform((d) => ({ minutes: Number(d.minutes) }));
        extraTimeForm.post(
            CbtMonitorController.extraTime.url([
                exam.id,
                extraTimeTarget.session_id!,
            ]),
            {
                preserveScroll: true,
                onSuccess: () => setExtraTimeTarget(null),
            },
        );
    };

    const confirmForceClose = (): void => {
        if (forceCloseTarget?.session_id === null || !forceCloseTarget) {
            return;
        }

        setActing(true);
        router.post(
            CbtMonitorController.forceClose.url([
                exam.id,
                forceCloseTarget.session_id!,
            ]),
            {},
            {
                preserveScroll: true,
                onFinish: () => setActing(false),
                onSuccess: () => setForceCloseTarget(null),
            },
        );
    };

    const columns: DataTableColumn<StudentMonitorRow>[] = [
        {
            key: 'student',
            label: 'Siswa',
            render: (row) => (
                <div>
                    <p className="font-medium">{row.name}</p>
                    <p className="font-mono text-xs text-muted-foreground tabular-nums">
                        {row.nis} · {row.classroom}
                    </p>
                </div>
            ),
        },
        {
            key: 'status',
            label: 'Status',
            align: 'center',
            render: (row) => (
                <StatusBadge variant={statusVariant[row.status] ?? 'muted'}>
                    {row.status_label}
                </StatusBadge>
            ),
        },
        {
            key: 'progress',
            label: 'Progres',
            align: 'right',
            render: (row) => (
                <span className="tabular-nums">
                    {row.answered_count}/{data.total_questions}
                </span>
            ),
        },
        {
            key: 'violations',
            label: 'Pelanggaran',
            align: 'right',
            render: (row) => (
                <span className="tabular-nums">{row.violations_count}</span>
            ),
        },
        {
            key: 'device',
            label: 'Perangkat',
            align: 'center',
            render: (row) => row.device_type ?? '—',
        },
        {
            key: 'last_seen',
            label: 'Terakhir terlihat',
            render: (row) =>
                row.last_seen_at ? (
                    <DateText value={row.last_seen_at} mode="datetime" />
                ) : (
                    <span className="text-muted-foreground">—</span>
                ),
        },
        ...(canControl
            ? [
                  {
                      key: 'actions',
                      label: 'Aksi',
                      align: 'center' as const,
                      render: (row: StudentMonitorRow) =>
                          row.session_id !== null ? (
                              <div className="inline-flex items-center gap-1">
                                  {row.status === 'locked' && (
                                      <Tooltip>
                                          <TooltipTrigger asChild>
                                              <Button
                                                  type="button"
                                                  variant="ghost"
                                                  size="icon"
                                                  aria-label={`Buka kunci ${row.name}`}
                                                  onClick={() =>
                                                      handleUnlock(row)
                                                  }
                                              >
                                                  <LockOpen
                                                      className="size-4 text-primary"
                                                      strokeWidth={1.75}
                                                  />
                                              </Button>
                                          </TooltipTrigger>
                                          <TooltipContent>
                                              Buka kunci
                                          </TooltipContent>
                                      </Tooltip>
                                  )}
                                  <Tooltip>
                                      <TooltipTrigger asChild>
                                          <Button
                                              type="button"
                                              variant="ghost"
                                              size="icon"
                                              aria-label={`Tambah waktu ${row.name}`}
                                              onClick={() => {
                                                  extraTimeForm.setData(
                                                      'minutes',
                                                      '10',
                                                  );
                                                  setExtraTimeTarget(row);
                                              }}
                                          >
                                              <Clock
                                                  className="size-4"
                                                  strokeWidth={1.75}
                                              />
                                          </Button>
                                      </TooltipTrigger>
                                      <TooltipContent>
                                          Tambah waktu
                                      </TooltipContent>
                                  </Tooltip>
                                  {(row.status === 'in_progress' ||
                                      row.status === 'locked') && (
                                      <Tooltip>
                                          <TooltipTrigger asChild>
                                              <Button
                                                  type="button"
                                                  variant="ghost"
                                                  size="icon"
                                                  aria-label={`Paksa selesai ${row.name}`}
                                                  onClick={() =>
                                                      setForceCloseTarget(row)
                                                  }
                                              >
                                                  <OctagonX
                                                      className="size-4 text-destructive"
                                                      strokeWidth={1.75}
                                                  />
                                              </Button>
                                          </TooltipTrigger>
                                          <TooltipContent>
                                              Paksa selesai
                                          </TooltipContent>
                                      </Tooltip>
                                  )}
                              </div>
                          ) : (
                              <span className="text-muted-foreground">—</span>
                          ),
                  },
              ]
            : []),
    ];

    return (
        <StaffLayout
            breadcrumbs={[
                { title: 'Beranda', href: AdminDashboardController.url() },
                { title: 'Daftar ujian', href: CbtExamController.index.url() },
                {
                    title: 'Monitor pengawas',
                    href: CbtMonitorController.index.url(exam.id),
                },
            ]}
        >
            <Head title={`Monitor — ${exam.title}`} />

            <div className="space-y-6">
                <PageHeader
                    title={`Monitor pengawas — ${exam.title}`}
                    description={`${exam.subject} · Pembaruan otomatis setiap 5 detik (${exam.status_label})`}
                />

                <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <div className="rounded-lg border border-border bg-card p-3">
                        <p className="text-xs text-muted-foreground">
                            Belum mulai
                        </p>
                        <p className="text-xl font-semibold tabular-nums">
                            {data.summary.not_started}
                        </p>
                    </div>
                    <div className="rounded-lg border border-border bg-card p-3">
                        <p className="text-xs text-muted-foreground">
                            Mengerjakan
                        </p>
                        <p className="text-xl font-semibold tabular-nums">
                            {data.summary.in_progress}
                        </p>
                    </div>
                    <div className="rounded-lg border border-border bg-card p-3">
                        <p className="text-xs text-muted-foreground">
                            Terkunci
                        </p>
                        <p className="text-xl font-semibold text-destructive tabular-nums">
                            {data.summary.locked}
                        </p>
                    </div>
                    <div className="rounded-lg border border-border bg-card p-3">
                        <p className="text-xs text-muted-foreground">Selesai</p>
                        <p className="text-xl font-semibold text-success tabular-nums">
                            {data.summary.finished}
                        </p>
                    </div>
                </div>

                <DataTable
                    columns={columns}
                    data={data.students}
                    rowKey={(row) => row.student_id}
                    empty={{
                        title: 'Belum ada peserta terdaftar',
                        description:
                            'Pastikan rombel peserta ujian memiliki siswa aktif pada tahun ajaran berjalan.',
                    }}
                />
            </div>

            <FormDialog
                open={extraTimeTarget !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setExtraTimeTarget(null);
                    }
                }}
                title={`Tambah waktu — ${extraTimeTarget?.name ?? ''}`}
                processing={extraTimeForm.processing}
                submitLabel="Simpan tambahan"
                onSubmit={submitExtraTime}
            >
                <div className="space-y-1.5">
                    <Label htmlFor="extra-minutes">
                        Tambahan menit (1–120)
                    </Label>
                    <Input
                        id="extra-minutes"
                        type="number"
                        min={1}
                        max={120}
                        value={extraTimeForm.data.minutes}
                        onChange={(event) =>
                            extraTimeForm.setData('minutes', event.target.value)
                        }
                    />
                    <InputError message={extraTimeForm.errors.minutes} />
                </div>
            </FormDialog>

            <ConfirmDialog
                open={forceCloseTarget !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setForceCloseTarget(null);
                    }
                }}
                title={`Paksa selesaikan ujian ${forceCloseTarget?.name ?? ''}?`}
                description="Sesi akan langsung ditutup dan jawaban yang sudah masuk dijadwalkan untuk dinilai."
                confirmLabel="Paksa selesai"
                variant="destructive"
                loading={acting}
                onConfirm={confirmForceClose}
            />
        </StaffLayout>
    );
}
