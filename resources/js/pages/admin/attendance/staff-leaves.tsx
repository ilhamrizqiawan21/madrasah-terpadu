import { Head, router } from '@inertiajs/react';
import { Check, ClipboardList, Plus, X } from 'lucide-react';
import { type FormEvent, type ReactNode, useState } from 'react';
import StaffLeaveRequestController from '@/actions/App/Http/Controllers/Admin/StaffLeaveRequestController';
import { DateInput } from '@/components/app/date-input';
import { DateText } from '@/components/app/date-text';
import { EmptyState } from '@/components/app/empty-state';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
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

type LeaveRow = {
    id: number;
    staff_name: string;
    type: 'leave' | 'sick' | 'duty';
    starts_on: string;
    ends_on: string;
    reason: string;
    status: 'pending' | 'approved' | 'rejected';
    decider_name: string | null;
    decided_at: string | null;
};

type Props = {
    leaves: { data: LeaveRow[] };
    canDecide: boolean;
    filters: { status: string };
};

const typeLabel: Record<string, string> = {
    leave: 'Izin / Cuti',
    sick: 'Sakit',
    duty: 'Tugas Luar',
};

const statusLabel: Record<string, string> = {
    pending: 'Menunggu',
    approved: 'Disetujui',
    rejected: 'Ditolak',
};

export default function StaffLeavesIndex({
    leaves,
    canDecide,
    filters,
}: Props) {
    const [open, setOpen] = useState(false);
    const [type, setType] = useState<'leave' | 'sick' | 'duty'>('leave');
    const [startsOn, setStartsOn] = useState('');
    const [endsOn, setEndsOn] = useState('');
    const [reason, setReason] = useState('');
    const [submitting, setSubmitting] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    const handleFilter = (status: string): void => {
        router.get(
            StaffLeaveRequestController.index.url({
                query: { status: status === 'all' ? undefined : status },
            }),
            {},
            { preserveState: true, preserveScroll: true },
        );
    };

    const handleSubmit = (e: FormEvent): void => {
        e.preventDefault();
        setSubmitting(true);
        router.post(
            StaffLeaveRequestController.store.url(),
            {
                type,
                starts_on: startsOn,
                ends_on: endsOn,
                reason,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setOpen(false);
                    setStartsOn('');
                    setEndsOn('');
                    setReason('');
                    setErrors({});
                },
                onError: (errs) => setErrors(errs),
                onFinish: () => setSubmitting(false),
            },
        );
    };

    const handleDecide = (
        id: number,
        decision: 'approved' | 'rejected',
    ): void => {
        router.patch(
            StaffLeaveRequestController.decide.url(id),
            { decision },
            { preserveScroll: true },
        );
    };

    return (
        <StaffLayout
            breadcrumbs={[
                {
                    title: 'Absensi',
                    href: StaffLeaveRequestController.index.url(),
                },
                {
                    title: 'Izin & Cuti Pegawai',
                    href: StaffLeaveRequestController.index.url(),
                },
            ]}
        >
            <Head title="Izin & Cuti Pegawai" />

            <div className="space-y-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-lg font-semibold tracking-tight">
                            Izin &amp; Cuti Pegawai
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Pengajuan izin, sakit, atau tugas luar pegawai.
                        </p>
                    </div>
                    <Button onClick={() => setOpen(true)}>
                        <Plus className="mr-1.5 h-4 w-4" />
                        Ajukan Izin
                    </Button>
                </div>

                <div className="flex items-center gap-3 rounded-lg border bg-card p-3">
                    <div className="w-48 space-y-1">
                        <Label className="text-xs">Status Pengajuan</Label>
                        <Select
                            value={filters.status || 'all'}
                            onValueChange={handleFilter}
                        >
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">
                                    Semua status
                                </SelectItem>
                                <SelectItem value="pending">
                                    Menunggu
                                </SelectItem>
                                <SelectItem value="approved">
                                    Disetujui
                                </SelectItem>
                                <SelectItem value="rejected">
                                    Ditolak
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>

                {leaves.data.length === 0 ? (
                    <EmptyState
                        icon={ClipboardList}
                        title="Belum ada pengajuan izin pegawai"
                        description="Klik Ajukan Izin untuk mencatat permohonan izin atau cuti."
                    />
                ) : (
                    <div className="overflow-x-auto rounded-lg border bg-card">
                        <table className="w-full text-sm">
                            <thead className="border-b bg-muted/40 text-left text-xs font-medium text-muted-foreground">
                                <tr>
                                    <th className="px-4 py-3">Pegawai</th>
                                    <th className="px-4 py-3">Jenis</th>
                                    <th className="px-4 py-3">
                                        Rentang Tanggal
                                    </th>
                                    <th className="px-4 py-3">Alasan</th>
                                    <th className="px-4 py-3">Status</th>
                                    {canDecide && (
                                        <th className="px-4 py-3 text-right">
                                            Aksi
                                        </th>
                                    )}
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {leaves.data.map((leave) => (
                                    <tr
                                        key={leave.id}
                                        className="hover:bg-muted/30"
                                    >
                                        <td className="px-4 py-3 font-medium">
                                            {leave.staff_name}
                                        </td>
                                        <td className="px-4 py-3">
                                            {typeLabel[leave.type] ??
                                                leave.type}
                                        </td>
                                        <td className="px-4 py-3 text-xs">
                                            <DateText value={leave.starts_on} />{' '}
                                            — <DateText value={leave.ends_on} />
                                        </td>
                                        <td className="px-4 py-3 text-xs text-muted-foreground">
                                            {leave.reason}
                                        </td>
                                        <td className="px-4 py-3">
                                            <Badge
                                                variant={
                                                    leave.status === 'approved'
                                                        ? 'default'
                                                        : leave.status ===
                                                            'rejected'
                                                          ? 'destructive'
                                                          : 'secondary'
                                                }
                                            >
                                                {statusLabel[leave.status] ??
                                                    leave.status}
                                            </Badge>
                                        </td>
                                        {canDecide && (
                                            <td className="px-4 py-3 text-right">
                                                {leave.status === 'pending' ? (
                                                    <div className="flex justify-end gap-1.5">
                                                        <Button
                                                            size="sm"
                                                            onClick={() =>
                                                                handleDecide(
                                                                    leave.id,
                                                                    'approved',
                                                                )
                                                            }
                                                        >
                                                            <Check className="mr-1 h-3.5 w-3.5" />
                                                            Setujui
                                                        </Button>
                                                        <Button
                                                            size="sm"
                                                            variant="outline"
                                                            onClick={() =>
                                                                handleDecide(
                                                                    leave.id,
                                                                    'rejected',
                                                                )
                                                            }
                                                        >
                                                            <X className="mr-1 h-3.5 w-3.5" />
                                                            Tolak
                                                        </Button>
                                                    </div>
                                                ) : (
                                                    <span className="text-xs text-muted-foreground">
                                                        {leave.decider_name ??
                                                            '—'}
                                                    </span>
                                                )}
                                            </td>
                                        )}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Ajukan Izin / Cuti Pegawai</DialogTitle>
                    </DialogHeader>
                    <form onSubmit={handleSubmit} className="space-y-4">
                        <div className="space-y-1.5">
                            <Label>Jenis Izin</Label>
                            <Select
                                value={type}
                                onValueChange={(v) =>
                                    setType(v as 'leave' | 'sick' | 'duty')
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="leave">
                                        Izin / Cuti
                                    </SelectItem>
                                    <SelectItem value="sick">Sakit</SelectItem>
                                    <SelectItem value="duty">
                                        Tugas Luar
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="grid grid-cols-2 gap-3">
                            <div className="space-y-1.5">
                                <Label>Tanggal Mulai</Label>
                                <DateInput
                                    value={startsOn}
                                    onChange={(e) =>
                                        setStartsOn(e.target.value)
                                    }
                                />
                                {errors.starts_on && (
                                    <p className="text-xs text-destructive">
                                        {errors.starts_on}
                                    </p>
                                )}
                            </div>
                            <div className="space-y-1.5">
                                <Label>Tanggal Selesai</Label>
                                <DateInput
                                    value={endsOn}
                                    onChange={(e) => setEndsOn(e.target.value)}
                                />
                                {errors.ends_on && (
                                    <p className="text-xs text-destructive">
                                        {errors.ends_on}
                                    </p>
                                )}
                            </div>
                        </div>

                        <div className="space-y-1.5">
                            <Label>Alasan</Label>
                            <Input
                                value={reason}
                                onChange={(e) => setReason(e.target.value)}
                                placeholder="Tuliskan alasan singkat..."
                            />
                            {errors.reason && (
                                <p className="text-xs text-destructive">
                                    {errors.reason}
                                </p>
                            )}
                        </div>

                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setOpen(false)}
                            >
                                Batal
                            </Button>
                            <Button type="submit" disabled={submitting}>
                                {submitting ? 'Mengirim...' : 'Kirim Pengajuan'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </StaffLayout>
    );
}

StaffLeavesIndex.layout = (page: ReactNode) => page;
