import { DateInput } from '@/components/app/date-input';
import { Head, router, useForm } from '@inertiajs/react';
import { Check, CheckCheck, X } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { useState } from 'react';
import HabitVerificationController from '@/actions/App/Http/Controllers/Admin/HabitVerificationController';
import { EmptyState } from '@/components/app/empty-state';
import { FormDialog } from '@/components/app/form-dialog';
import { PageHeader } from '@/components/app/page-header';
import { StatusBadge } from '@/components/app/status-badge';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import StaffLayout from '@/layouts/staff-layout';

type CellData = {
    log_id: number;
    done: boolean;
    verification: 'pending' | 'verified' | 'rejected';
    note: string | null;
} | null;

type StudentRow = {
    student_id: number;
    student_name: string;
    student_nis: string;
    cells: Record<number, CellData>;
};

type ClassroomOption = { id: number; label: string };
type ItemColumn = { id: number; name: string };

type Props = {
    classrooms: ClassroomOption[];
    selectedClassroomId: number | null;
    date: string;
    items: ItemColumn[];
    rows: StudentRow[];
};

export default function HabitVerifyIndex({
    classrooms,
    selectedClassroomId,
    date,
    items,
    rows,
}: Props) {
    const [rejectTarget, setRejectTarget] = useState<{
        logId: number;
        studentName: string;
        itemName: string;
    } | null>(null);

    const rejectForm = useForm<{ note: string }>({ note: '' });

    function visitFilters(next: { classroom_id?: number; date?: string }) {
        router.get(
            HabitVerificationController.index.url({
                query: {
                    classroom_id:
                        next.classroom_id ?? selectedClassroomId ?? undefined,
                    date: next.date ?? date,
                },
            }),
            {},
            { preserveState: false, replace: true },
        );
    }

    function verifyIds(logIds: number[]) {
        if (!selectedClassroomId || logIds.length === 0) {
            return;
        }

        router.post(
            HabitVerificationController.verifyBulk.url(),
            {
                classroom_id: selectedClassroomId,
                log_date: date,
                log_ids: logIds,
            },
            { preserveScroll: true },
        );
    }

    function allPendingIds(): number[] {
        const ids: number[] = [];
        for (const row of rows) {
            for (const item of items) {
                const cell = row.cells[item.id];
                if (cell && cell.done && cell.verification === 'pending') {
                    ids.push(cell.log_id);
                }
            }
        }
        return ids;
    }

    function pendingIdsForStudent(row: StudentRow): number[] {
        return items
            .map((item) => row.cells[item.id])
            .filter((cell): cell is NonNullable<CellData> =>
                Boolean(cell && cell.done && cell.verification === 'pending'),
            )
            .map((cell) => cell.log_id);
    }

    function pendingIdsForItem(itemId: number): number[] {
        return rows
            .map((row) => row.cells[itemId])
            .filter((cell): cell is NonNullable<CellData> =>
                Boolean(cell && cell.done && cell.verification === 'pending'),
            )
            .map((cell) => cell.log_id);
    }

    function submitReject(e: FormEvent<HTMLFormElement>) {
        e.preventDefault();
        if (!rejectTarget || !selectedClassroomId) {
            return;
        }

        router.post(
            HabitVerificationController.reject.url(rejectTarget.logId),
            {
                classroom_id: selectedClassroomId,
                note: rejectForm.data.note,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    rejectForm.reset();
                    setRejectTarget(null);
                },
                onError: (errors) =>
                    rejectForm.setError(errors as Record<'note', string>),
            },
        );
    }

    const totalPending = allPendingIds().length;

    return (
        <>
            <Head title="Verifikasi pembiasaan" />
            <div className="space-y-6">
                <PageHeader
                    title="Verifikasi pembiasaan"
                    description="Periksa dan verifikasi mutabaah harian siswa per rombel."
                    actions={
                        selectedClassroomId ? (
                            <Button
                                type="button"
                                disabled={totalPending === 0}
                                onClick={() => verifyIds(allPendingIds())}
                            >
                                <CheckCheck className="mr-2 h-4 w-4" />
                                Verifikasi semua ({totalPending})
                            </Button>
                        ) : null
                    }
                />

                <div className="flex flex-wrap items-center gap-3">
                    <Select
                        value={
                            selectedClassroomId
                                ? String(selectedClassroomId)
                                : ''
                        }
                        onValueChange={(v) =>
                            visitFilters({ classroom_id: Number(v) })
                        }
                    >
                        <SelectTrigger
                            className="w-[220px]"
                            aria-label="Pilih rombel"
                        >
                            <SelectValue placeholder="Pilih rombel" />
                        </SelectTrigger>
                        <SelectContent>
                            {classrooms.map((c) => (
                                <SelectItem key={c.id} value={String(c.id)}>
                                    {c.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    <DateInput
                        value={date}
                        onChange={(e) => visitFilters({ date: e.target.value })}
                        className="w-[170px]"
                        aria-label="Tanggal pembiasaan"
                    />
                </div>

                {!selectedClassroomId || rows.length === 0 ? (
                    <EmptyState
                        title="Belum ada data rombel"
                        description="Pilih rombel yang memiliki siswa aktif untuk memverifikasi pembiasaan."
                    />
                ) : (
                    <div className="overflow-x-auto rounded-lg border border-border bg-card shadow-xs">
                        <table className="w-full text-sm">
                            <thead className="border-b border-border bg-muted/40">
                                <tr>
                                    <th className="px-3 py-2.5 text-left font-medium text-muted-foreground">
                                        Siswa
                                    </th>
                                    {items.map((item) => {
                                        const itemPending = pendingIdsForItem(
                                            item.id,
                                        );

                                        return (
                                            <th
                                                key={item.id}
                                                className="px-3 py-2.5 text-center font-medium text-muted-foreground"
                                            >
                                                <div className="flex flex-col items-center gap-1">
                                                    <span className="whitespace-nowrap">
                                                        {item.name}
                                                    </span>
                                                    {itemPending.length > 0 ? (
                                                        <Button
                                                            type="button"
                                                            variant="ghost"
                                                            size="sm"
                                                            className="h-6 px-2 text-xs text-primary"
                                                            onClick={() =>
                                                                verifyIds(
                                                                    itemPending,
                                                                )
                                                            }
                                                        >
                                                            Verifikasi (
                                                            {itemPending.length}
                                                            )
                                                        </Button>
                                                    ) : null}
                                                </div>
                                            </th>
                                        );
                                    })}
                                    <th className="px-3 py-2.5 text-center font-medium text-muted-foreground">
                                        Aksi
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {rows.map((row) => {
                                    const studentPending =
                                        pendingIdsForStudent(row);

                                    return (
                                        <tr
                                            key={row.student_id}
                                            className="hover:bg-muted/25"
                                        >
                                            <td className="px-3 py-2.5">
                                                <div className="font-medium text-foreground">
                                                    {row.student_name}
                                                </div>
                                                <div className="text-xs text-muted-foreground">
                                                    NIS {row.student_nis}
                                                </div>
                                            </td>
                                            {items.map((item) => {
                                                const cell = row.cells[item.id];

                                                if (!cell || !cell.done) {
                                                    return (
                                                        <td
                                                            key={item.id}
                                                            className="px-3 py-2.5 text-center text-xs text-muted-foreground"
                                                        >
                                                            —
                                                        </td>
                                                    );
                                                }

                                                return (
                                                    <td
                                                        key={item.id}
                                                        className="px-3 py-2.5 text-center"
                                                    >
                                                        <div className="inline-flex items-center gap-1">
                                                            {cell.verification ===
                                                            'verified' ? (
                                                                <StatusBadge variant="success">
                                                                    <Check className="mr-1 h-3 w-3" />
                                                                    Sah
                                                                </StatusBadge>
                                                            ) : cell.verification ===
                                                              'rejected' ? (
                                                                <StatusBadge variant="destructive">
                                                                    Ditolak
                                                                </StatusBadge>
                                                            ) : (
                                                                <>
                                                                    <StatusBadge variant="attention">
                                                                        Menunggu
                                                                    </StatusBadge>
                                                                    <Tooltip>
                                                                        <TooltipTrigger
                                                                            asChild
                                                                        >
                                                                            <Button
                                                                                type="button"
                                                                                variant="ghost"
                                                                                size="icon"
                                                                                className="h-6 w-6"
                                                                                aria-label={`Tolak ${item.name} ${row.student_name}`}
                                                                                onClick={() => {
                                                                                    rejectForm.reset();
                                                                                    rejectForm.clearErrors();
                                                                                    setRejectTarget(
                                                                                        {
                                                                                            logId: cell.log_id,
                                                                                            studentName:
                                                                                                row.student_name,
                                                                                            itemName:
                                                                                                item.name,
                                                                                        },
                                                                                    );
                                                                                }}
                                                                            >
                                                                                <X className="h-3.5 w-3.5 text-destructive" />
                                                                            </Button>
                                                                        </TooltipTrigger>
                                                                        <TooltipContent>
                                                                            Tolak
                                                                            dengan
                                                                            catatan
                                                                        </TooltipContent>
                                                                    </Tooltip>
                                                                </>
                                                            )}
                                                        </div>
                                                    </td>
                                                );
                                            })}
                                            <td className="px-3 py-2.5 text-center">
                                                {studentPending.length > 0 ? (
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        size="sm"
                                                        onClick={() =>
                                                            verifyIds(
                                                                studentPending,
                                                            )
                                                        }
                                                    >
                                                        Verifikasi (
                                                        {studentPending.length})
                                                    </Button>
                                                ) : (
                                                    <span className="text-xs text-muted-foreground">
                                                        —
                                                    </span>
                                                )}
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>

            <FormDialog
                open={rejectTarget !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setRejectTarget(null);
                    }
                }}
                title="Tolak catatan pembiasaan"
                description={
                    rejectTarget
                        ? `${rejectTarget.studentName} — ${rejectTarget.itemName}`
                        : undefined
                }
                submitLabel="Tolak & beri tahu wali"
                onSubmit={submitReject}
                processing={rejectForm.processing}
            >
                <div className="space-y-2">
                    <Label htmlFor="reject-note">Catatan untuk wali</Label>
                    <Textarea
                        id="reject-note"
                        rows={3}
                        value={rejectForm.data.note}
                        onChange={(e) =>
                            rejectForm.setData('note', e.target.value)
                        }
                        placeholder="Jelaskan alasan penolakan..."
                        required
                    />
                    <InputError message={rejectForm.errors.note} />
                </div>
            </FormDialog>
        </>
    );
}

HabitVerifyIndex.layout = (page: ReactNode) => (
    <StaffLayout
        breadcrumbs={[
            { title: 'Ibadah', href: '#' },
            {
                title: 'Verifikasi pembiasaan',
                href: HabitVerificationController.index.url(),
            },
        ]}
    >
        {page}
    </StaffLayout>
);
