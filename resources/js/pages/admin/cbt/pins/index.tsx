import { Head, router } from '@inertiajs/react';
import { KeyRound, Printer } from 'lucide-react';
import { useState } from 'react';
import CbtStudentPinController from '@/actions/App/Http/Controllers/Admin/CbtStudentPinController';
import AdminDashboardController from '@/actions/App/Http/Controllers/Admin/DashboardController';
import { ConfirmDialog } from '@/components/app/confirm-dialog';
import type { DataTableColumn } from '@/components/app/data-table';
import { DataTable } from '@/components/app/data-table';
import { DateText } from '@/components/app/date-text';
import { PageHeader } from '@/components/app/page-header';
import { StatusBadge } from '@/components/app/status-badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
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

type StudentPinRow = {
    id: number;
    nis: string;
    name: string;
    has_pin: boolean;
    reset_at: string | null;
};

type GeneratedPin = {
    student_id: number;
    nis: string;
    name: string;
    pin: string;
};

type UnitOption = { id: number; code: string; name: string };
type ClassroomOption = { id: number; name: string; grade: string };

type Props = {
    canReset: boolean;
    filters: {
        unit_id: number | null;
        classroom_id: number | null;
    };
    activeYearLabel: string | null;
    unitOptions: UnitOption[];
    classroomOptions: ClassroomOption[];
    students: StudentPinRow[];
    generatedPins: GeneratedPin[] | null;
};

export default function CbtStudentPinsIndex({
    canReset,
    filters,
    activeYearLabel,
    unitOptions,
    classroomOptions,
    students,
    generatedPins,
}: Props) {
    const [resetTarget, setResetTarget] = useState<StudentPinRow | null>(null);
    const [resetClassroomOpen, setResetClassroomOpen] = useState(false);
    const [submitting, setSubmitting] = useState(false);
    const [pinsDialogOpen, setPinsDialogOpen] = useState(
        Boolean(generatedPins && generatedPins.length > 0),
    );
    const [shownPins, setShownPins] = useState<GeneratedPin[]>(
        generatedPins ?? [],
    );

    const selectedClassroom = classroomOptions.find(
        (c) => c.id === filters.classroom_id,
    );

    const visit = (changes: Partial<Props['filters']>): void => {
        const query = { ...filters, ...changes };
        const params = Object.fromEntries(
            Object.entries(query).filter(
                ([, value]) => value !== null && value !== undefined,
            ),
        );

        router.get(CbtStudentPinController.index.url(), params, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const handleResetStudent = (): void => {
        if (resetTarget === null) {
            return;
        }

        setSubmitting(true);
        router.post(
            CbtStudentPinController.resetStudent.url(resetTarget.id),
            {},
            {
                preserveScroll: true,
                onFinish: () => setSubmitting(false),
                onSuccess: (page) => {
                    setResetTarget(null);
                    const nextPins = (
                        page.props as unknown as {
                            generatedPins?: GeneratedPin[] | null;
                        }
                    ).generatedPins;
                    if (nextPins && nextPins.length > 0) {
                        setShownPins(nextPins);
                        setPinsDialogOpen(true);
                    }
                },
            },
        );
    };

    const handleResetClassroom = (): void => {
        if (filters.classroom_id === null) {
            return;
        }

        setSubmitting(true);
        router.post(
            CbtStudentPinController.resetClassroom.url(filters.classroom_id),
            {},
            {
                preserveScroll: true,
                onFinish: () => setSubmitting(false),
                onSuccess: (page) => {
                    setResetClassroomOpen(false);
                    const nextPins = (
                        page.props as unknown as {
                            generatedPins?: GeneratedPin[] | null;
                        }
                    ).generatedPins;
                    if (nextPins && nextPins.length > 0) {
                        setShownPins(nextPins);
                        setPinsDialogOpen(true);
                    }
                },
            },
        );
    };

    const columns: DataTableColumn<StudentPinRow>[] = [
        {
            key: 'nis',
            label: 'NIS',
            render: (row) => (
                <span className="font-mono text-xs tabular-nums">
                    {row.nis}
                </span>
            ),
        },
        {
            key: 'name',
            label: 'Nama siswa',
            render: (row) => <span className="font-medium">{row.name}</span>,
        },
        {
            key: 'status',
            label: 'Status PIN',
            align: 'center',
            render: (row) => (
                <StatusBadge variant={row.has_pin ? 'success' : 'muted'}>
                    {row.has_pin ? 'Sudah diatur' : 'Belum diatur'}
                </StatusBadge>
            ),
        },
        {
            key: 'reset_at',
            label: 'Terakhir dibuat',
            render: (row) =>
                row.reset_at ? (
                    <DateText value={row.reset_at} mode="datetime" />
                ) : (
                    <span className="text-muted-foreground">—</span>
                ),
        },
        ...(canReset
            ? [
                  {
                      key: 'actions',
                      label: 'Aksi',
                      align: 'center' as const,
                      render: (row: StudentPinRow) => (
                          <Tooltip>
                              <TooltipTrigger asChild>
                                  <Button
                                      type="button"
                                      variant="ghost"
                                      size="icon"
                                      aria-label={`Buat ulang PIN ${row.name}`}
                                      onClick={() => setResetTarget(row)}
                                  >
                                      <KeyRound
                                          className="size-4"
                                          strokeWidth={1.75}
                                      />
                                  </Button>
                              </TooltipTrigger>
                              <TooltipContent>Buat ulang PIN</TooltipContent>
                          </Tooltip>
                      ),
                  },
              ]
            : []),
    ];

    return (
        <StaffLayout
            breadcrumbs={[
                { title: 'Beranda', href: AdminDashboardController.url() },
                {
                    title: 'E-Learning',
                    href: CbtStudentPinController.index.url(),
                },
                {
                    title: 'PIN ujian siswa',
                    href: CbtStudentPinController.index.url(),
                },
            ]}
        >
            <Head title="PIN ujian siswa" />

            <div className="space-y-6">
                <PageHeader
                    title="PIN ujian siswa"
                    description={
                        activeYearLabel
                            ? `Buat dan cetak PIN masuk ujian CBT siswa untuk tahun ajaran ${activeYearLabel}. PIN hanya ditampilkan sekali setelah dibuat.`
                            : 'Aktifkan tahun ajaran terlebih dahulu untuk mengelola PIN ujian siswa.'
                    }
                    actions={
                        canReset &&
                        selectedClassroom !== undefined &&
                        students.length > 0 ? (
                            <Button
                                type="button"
                                onClick={() => setResetClassroomOpen(true)}
                            >
                                <KeyRound
                                    className="size-4"
                                    strokeWidth={1.75}
                                />
                                Buat ulang PIN rombel
                            </Button>
                        ) : undefined
                    }
                />

                <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <Select
                        value={
                            filters.unit_id !== null
                                ? String(filters.unit_id)
                                : ''
                        }
                        onValueChange={(value) =>
                            visit({
                                unit_id: Number(value),
                                classroom_id: null,
                            })
                        }
                    >
                        <SelectTrigger
                            className="w-full sm:w-44"
                            aria-label="Pilih unit"
                        >
                            <SelectValue placeholder="Pilih unit" />
                        </SelectTrigger>
                        <SelectContent>
                            {unitOptions.map((option) => (
                                <SelectItem
                                    key={option.id}
                                    value={String(option.id)}
                                >
                                    {option.code} — {option.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

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
                            className="w-full sm:w-52"
                            aria-label="Pilih rombel"
                        >
                            <SelectValue placeholder="Pilih rombel" />
                        </SelectTrigger>
                        <SelectContent>
                            {classroomOptions.map((option) => (
                                <SelectItem
                                    key={option.id}
                                    value={String(option.id)}
                                >
                                    {option.name} (Tingkat {option.grade})
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                <DataTable
                    columns={columns}
                    data={students}
                    rowKey={(row) => row.id}
                    empty={{
                        title:
                            selectedClassroom === undefined
                                ? 'Belum ada rombel tersedia'
                                : 'Belum ada siswa di rombel ini',
                        description:
                            selectedClassroom === undefined
                                ? 'Pastikan Anda ditugaskan sebagai wali kelas atau pilih unit yang memiliki rombel aktif.'
                                : `Belum ada siswa aktif yang terdaftar pada rombel ${selectedClassroom.name}.`,
                    }}
                />
            </div>

            <ConfirmDialog
                open={resetTarget !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setResetTarget(null);
                    }
                }}
                title="Buat ulang PIN ujian siswa?"
                description={`PIN lama milik ${resetTarget?.name ?? ''} tidak lagi berlaku dan PIN baru hanya akan ditampilkan satu kali.`}
                confirmLabel="Buat ulang PIN"
                variant="default"
                loading={submitting}
                onConfirm={handleResetStudent}
            />

            <ConfirmDialog
                open={resetClassroomOpen}
                onOpenChange={setResetClassroomOpen}
                title={`Buat ulang PIN seluruh rombel ${selectedClassroom?.name ?? ''}?`}
                description={`Semua ${students.length} siswa aktif di rombel ini akan mendapatkan PIN baru untuk dicetak dan dibagikan. PIN lama tidak lagi berlaku.`}
                confirmLabel="Buat ulang & tampilkan"
                variant="default"
                loading={submitting}
                onConfirm={handleResetClassroom}
            />

            <Dialog open={pinsDialogOpen} onOpenChange={setPinsDialogOpen}>
                <DialogContent className="max-h-[85vh] overflow-auto sm:max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>
                            PIN ujian siswa (sekali tampil)
                        </DialogTitle>
                        <DialogDescription>
                            Catat atau cetak daftar PIN berikut. Demi keamanan,
                            PIN tidak dapat dilihat kembali setelah dialog ini
                            ditutup.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-2 sm:grid-cols-2">
                        {shownPins.map((item) => (
                            <div
                                key={item.student_id}
                                className="flex items-center justify-between rounded-md border border-border bg-muted/40 px-3 py-2 text-sm"
                            >
                                <div className="min-w-0">
                                    <p className="truncate font-medium">
                                        {item.name}
                                    </p>
                                    <p className="font-mono text-xs text-muted-foreground tabular-nums">
                                        NIS {item.nis}
                                    </p>
                                </div>
                                <span className="font-mono text-base font-semibold tracking-wider tabular-nums">
                                    {item.pin}
                                </span>
                            </div>
                        ))}
                    </div>

                    <DialogFooter className="gap-2 print:hidden">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => window.print()}
                        >
                            <Printer className="size-4" strokeWidth={1.75} />
                            Cetak
                        </Button>
                        <Button
                            type="button"
                            onClick={() => setPinsDialogOpen(false)}
                        >
                            Selesai
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </StaffLayout>
    );
}
