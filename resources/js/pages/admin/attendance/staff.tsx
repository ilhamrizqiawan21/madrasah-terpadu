import { Head, router } from '@inertiajs/react';
import { CalendarCheck2, Pencil, Search } from 'lucide-react';
import { type FormEvent, type ReactNode, useState } from 'react';
import StaffAttendanceController from '@/actions/App/Http/Controllers/Admin/StaffAttendanceController';
import { DateInput } from '@/components/app/date-input';
import { EmptyState } from '@/components/app/empty-state';
import { TimeInput } from '@/components/app/time-input';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
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

type AttendanceItem = {
    id: number;
    status: 'present' | 'late' | 'leave' | 'sick' | 'absent';
    source: string;
    check_in_time: string | null;
    check_out_time: string | null;
    note: string | null;
};

type StaffRow = {
    user_id: number;
    name: string;
    username: string;
    attendance: AttendanceItem | null;
};

type Props = {
    rows: { data: StaffRow[] };
    filters: { work_date: string; search: string };
};

const statusLabel: Record<string, string> = {
    present: 'Hadir',
    late: 'Terlambat',
    leave: 'Izin',
    sick: 'Sakit',
    absent: 'Alpa',
};

export default function StaffAttendanceIndex({ rows, filters }: Props) {
    const [search, setSearch] = useState(filters.search);
    const [editing, setEditing] = useState<StaffRow | null>(null);
    const [status, setStatus] = useState('present');
    const [checkIn, setCheckIn] = useState('');
    const [checkOut, setCheckOut] = useState('');
    const [note, setNote] = useState('');
    const [saving, setSaving] = useState(false);

    const applyFilter = (nextDate?: string, nextSearch?: string): void => {
        router.get(
            StaffAttendanceController.index.url({
                query: {
                    work_date: nextDate ?? filters.work_date,
                    search: (nextSearch ?? search) || undefined,
                },
            }),
            {},
            { preserveState: true, preserveScroll: true },
        );
    };

    const openEdit = (row: StaffRow): void => {
        setEditing(row);
        setStatus(row.attendance?.status ?? 'present');
        setCheckIn(row.attendance?.check_in_time ?? '');
        setCheckOut(row.attendance?.check_out_time ?? '');
        setNote(row.attendance?.note ?? '');
    };

    const handleSave = (e: FormEvent): void => {
        e.preventDefault();
        if (!editing) {
            return;
        }
        setSaving(true);
        router.post(
            StaffAttendanceController.store.url(),
            {
                user_id: editing.user_id,
                work_date: filters.work_date,
                status,
                check_in_time: checkIn || null,
                check_out_time: checkOut || null,
                note: note || null,
            },
            {
                preserveScroll: true,
                onSuccess: () => setEditing(null),
                onFinish: () => setSaving(false),
            },
        );
    };

    return (
        <StaffLayout
            breadcrumbs={[
                {
                    title: 'Absensi',
                    href: StaffAttendanceController.index.url(),
                },
                {
                    title: 'Absensi Pegawai',
                    href: StaffAttendanceController.index.url(),
                },
            ]}
        >
            <Head title="Absensi Pegawai" />

            <div className="space-y-4">
                <div>
                    <h1 className="text-lg font-semibold tracking-tight">
                        Absensi Pegawai
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Rekap kehadiran harian guru dan staf madrasah.
                    </p>
                </div>

                <div className="flex flex-wrap items-end gap-3 rounded-lg border bg-card p-3">
                    <div className="w-44 space-y-1">
                        <Label className="text-xs">Tanggal</Label>
                        <DateInput
                            value={filters.work_date}
                            onChange={(e) => applyFilter(e.target.value)}
                        />
                    </div>

                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            applyFilter(undefined, search);
                        }}
                        className="flex min-w-[200px] flex-1 items-center gap-2"
                    >
                        <div className="relative flex-1">
                            <Search className="pointer-events-none absolute top-2.5 left-2.5 h-4 w-4 text-muted-foreground" />
                            <Input
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Cari nama atau username pegawai..."
                                className="pl-8"
                            />
                        </div>
                        <Button type="submit" variant="secondary">
                            Cari
                        </Button>
                    </form>
                </div>

                {rows.data.length === 0 ? (
                    <EmptyState
                        icon={CalendarCheck2}
                        title="Tidak ada data pegawai"
                        description="Belum ada akun pegawai yang cocok dengan pencarian."
                    />
                ) : (
                    <div className="overflow-x-auto rounded-lg border bg-card">
                        <table className="w-full text-sm">
                            <thead className="border-b bg-muted/40 text-left text-xs font-medium text-muted-foreground">
                                <tr>
                                    <th className="px-4 py-3">Pegawai</th>
                                    <th className="px-4 py-3">Status</th>
                                    <th className="px-4 py-3">Masuk</th>
                                    <th className="px-4 py-3">Pulang</th>
                                    <th className="px-4 py-3">Catatan</th>
                                    <th className="px-4 py-3 text-right">
                                        Aksi
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {rows.data.map((row) => (
                                    <tr
                                        key={row.user_id}
                                        className="hover:bg-muted/30"
                                    >
                                        <td className="px-4 py-3">
                                            <div className="font-medium">
                                                {row.name}
                                            </div>
                                            <div className="text-xs text-muted-foreground">
                                                @{row.username}
                                            </div>
                                        </td>
                                        <td className="px-4 py-3">
                                            {row.attendance ? (
                                                <Badge variant="secondary">
                                                    {statusLabel[
                                                        row.attendance.status
                                                    ] ?? row.attendance.status}
                                                </Badge>
                                            ) : (
                                                <span className="text-xs text-muted-foreground">
                                                    Belum tercatat
                                                </span>
                                            )}
                                        </td>
                                        <td className="px-4 py-3">
                                            {row.attendance?.check_in_time ??
                                                '—'}
                                        </td>
                                        <td className="px-4 py-3">
                                            {row.attendance?.check_out_time ??
                                                '—'}
                                        </td>
                                        <td className="px-4 py-3 text-xs text-muted-foreground">
                                            {row.attendance?.note ?? '—'}
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                onClick={() => openEdit(row)}
                                            >
                                                <Pencil className="mr-1.5 h-3.5 w-3.5" />
                                                Catat
                                            </Button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>

            <Dialog
                open={editing !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setEditing(null);
                    }
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Catat Presensi Pegawai</DialogTitle>
                        <DialogDescription>
                            {editing?.name} · {filters.work_date}
                        </DialogDescription>
                    </DialogHeader>
                    <form onSubmit={handleSave} className="space-y-4">
                        <div className="space-y-1.5">
                            <Label>Status Kehadiran</Label>
                            <Select value={status} onValueChange={setStatus}>
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="present">
                                        Hadir
                                    </SelectItem>
                                    <SelectItem value="late">
                                        Terlambat
                                    </SelectItem>
                                    <SelectItem value="leave">Izin</SelectItem>
                                    <SelectItem value="sick">Sakit</SelectItem>
                                    <SelectItem value="absent">Alpa</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="grid grid-cols-2 gap-3">
                            <div className="space-y-1.5">
                                <Label>Jam Masuk</Label>
                                <TimeInput
                                    value={checkIn}
                                    onChange={setCheckIn}
                                />
                            </div>
                            <div className="space-y-1.5">
                                <Label>Jam Pulang</Label>
                                <TimeInput
                                    value={checkOut}
                                    onChange={setCheckOut}
                                />
                            </div>
                        </div>

                        <div className="space-y-1.5">
                            <Label>Catatan</Label>
                            <Input
                                value={note}
                                onChange={(e) => setNote(e.target.value)}
                                placeholder="Opsional..."
                            />
                        </div>

                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setEditing(null)}
                            >
                                Batal
                            </Button>
                            <Button type="submit" disabled={saving}>
                                {saving ? 'Menyimpan...' : 'Simpan'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </StaffLayout>
    );
}

StaffAttendanceIndex.layout = (page: ReactNode) => page;
