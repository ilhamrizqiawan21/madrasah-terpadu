import { Head, router, useForm } from '@inertiajs/react';
import { CalendarPlus, Printer } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { useEffect, useRef, useState } from 'react';
import RoomBookingController from '@/actions/App/Http/Controllers/Admin/RoomBookingController';
import { ConfirmDialog } from '@/components/app/confirm-dialog';
import type { DataTableColumn } from '@/components/app/data-table';
import { DataTable } from '@/components/app/data-table';
import { DateInput } from '@/components/app/date-input';
import { DateText } from '@/components/app/date-text';
import { FormDialog } from '@/components/app/form-dialog';
import { PageHeader } from '@/components/app/page-header';
import { TimeInput } from '@/components/app/time-input';
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
import type { PaginatedResource } from '@/types';

type Option = { value: string; label: string };
type RoomOption = Option & { unit_id: string };
type Row = {
    id: number;
    room: string;
    building: string;
    used_on: string;
    starts_at: string;
    ends_at: string;
    purpose: string | null;
    user: string;
    can_cancel: boolean;
};

type Props = {
    rows: PaginatedResource<Row>;
    filters: { search: string; unit: number | null; date: string | null };
    today: string;
    unitOptions: Option[];
    roomOptions: RoomOption[];
};

const ALL = 'semua';
const dot = (t: string) => t.replace(':', '.');

export default function RoomBookings({
    rows,
    filters,
    today,
    unitOptions,
    roomOptions,
}: Props) {
    const [search, setSearch] = useState(filters.search);
    const [loading, setLoading] = useState(false);
    const [open, setOpen] = useState(false);
    const [cancelling, setCancelling] = useState<Row | null>(null);
    const [busy, setBusy] = useState(false);
    const [unit, setUnit] = useState(unitOptions[0]?.value ?? '');
    const timer = useRef<number | undefined>(undefined);
    const form = useForm({
        room_id: '',
        used_on: today,
        starts_at: '07:30',
        ends_at: '09:00',
        purpose: '',
    });

    useEffect(() => () => window.clearTimeout(timer.current), []);

    const visit = (
        changes: Record<string, string | number | null>,
        q = search,
    ) =>
        router.get(
            RoomBookingController.index.url(),
            Object.fromEntries(
                Object.entries({ ...filters, search: q, ...changes }).filter(
                    ([, v]) => v !== null && String(v) !== '',
                ),
            ),
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                onStart: () => setLoading(true),
                onFinish: () => setLoading(false),
            },
        );

    const submit = (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        form.post(RoomBookingController.store.url(), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setOpen(false);
            },
        });
    };

    const rooms = roomOptions.filter((r) => r.unit_id === unit);

    const columns: DataTableColumn<Row>[] = [
        {
            key: 'room',
            label: 'Ruangan / lab',
            render: (r) => (
                <div>
                    <div className="font-medium">{r.room}</div>
                    <div className="text-xs text-muted-foreground">
                        {r.building}
                    </div>
                </div>
            ),
        },
        {
            key: 'date',
            label: 'Tanggal',
            className: 'whitespace-nowrap',
            render: (r) => <DateText value={r.used_on} />,
        },
        {
            key: 'time',
            label: 'Waktu',
            className: 'whitespace-nowrap tabular-nums',
            render: (r) => `${dot(r.starts_at)}–${dot(r.ends_at)}`,
        },
        {
            key: 'user',
            label: 'Guru pemesan',
            render: (r) => (
                <div>
                    <div>{r.user}</div>
                    {r.purpose && (
                        <div className="text-xs text-muted-foreground">
                            {r.purpose}
                        </div>
                    )}
                </div>
            ),
        },
        {
            key: 'actions',
            label: 'Aksi',
            align: 'center',
            className: 'print:hidden',
            render: (r) =>
                r.can_cancel ? (
                    <Button
                        size="sm"
                        variant="outline"
                        className="text-destructive"
                        onClick={() => setCancelling(r)}
                    >
                        Batalkan
                    </Button>
                ) : null,
        },
    ];

    const bookButton = (
        <Button type="button" onClick={() => setOpen(true)}>
            <CalendarPlus className="size-4" strokeWidth={1.75} />
            Booking ruangan
        </Button>
    );

    return (
        <StaffLayout
            breadcrumbs={[
                { title: 'Akademik', href: '#' },
                {
                    title: 'Peminjaman Ruangan',
                    href: RoomBookingController.index.url(),
                },
            ]}
        >
            <Head title="Peminjaman Ruangan" />

            <div className="@container space-y-4">
                <PageHeader
                    title="Peminjaman Ruangan & Lab"
                    description="Jadwal booking ruangan dan laboratorium. Tanpa saringan tanggal, yang tampil booking hari ini dan sesudahnya."
                    actions={
                        <>
                            <Button
                                type="button"
                                variant="soft"
                                onClick={() => window.print()}
                            >
                                <Printer
                                    className="size-4"
                                    strokeWidth={1.75}
                                />
                                Cetak jadwal booking
                            </Button>
                            {bookButton}
                        </>
                    }
                />

                <div className="flex flex-wrap gap-2 print:hidden">
                    <Input
                        type="search"
                        value={search}
                        onChange={(e) => {
                            const v = e.target.value;
                            setSearch(v);
                            window.clearTimeout(timer.current);
                            timer.current = window.setTimeout(
                                () => visit({ page: 1 }, v),
                                300,
                            );
                        }}
                        placeholder="Cari ruangan atau guru pemesan"
                        aria-label="Cari ruangan atau guru pemesan"
                        className="w-full @3xl:w-72"
                    />
                    <Select
                        value={
                            filters.unit === null ? ALL : String(filters.unit)
                        }
                        onValueChange={(v) =>
                            visit({ unit: v === ALL ? null : v, page: 1 })
                        }
                    >
                        <SelectTrigger
                            className="w-full @lg:w-56"
                            aria-label="Saring unit"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL}>Semua unit</SelectItem>
                            {unitOptions.map((o) => (
                                <SelectItem key={o.value} value={o.value}>
                                    {o.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <DateInput
                        value={filters.date ?? ''}
                        onChange={(e) =>
                            visit({ date: e.target.value || null, page: 1 })
                        }
                        aria-label="Saring tanggal"
                        className="w-full @lg:w-44"
                    />
                    {filters.date && (
                        <Button
                            type="button"
                            variant="ghost"
                            onClick={() => visit({ date: null, page: 1 })}
                        >
                            Semua tanggal
                        </Button>
                    )}
                </div>

                <DataTable
                    columns={columns}
                    data={rows.data}
                    rowKey={(r) => r.id}
                    loading={loading}
                    pagination={{
                        currentPage: rows.meta.current_page,
                        lastPage: rows.meta.last_page,
                        perPage: rows.meta.per_page,
                        total: rows.meta.total,
                        from: rows.meta.from,
                        to: rows.meta.to,
                    }}
                    onPageChange={(page) => visit({ page })}
                    empty={{
                        title: 'Belum ada booking',
                        description:
                            'Belum ada ruangan atau lab yang dibooking untuk saringan ini.',
                        action: bookButton,
                    }}
                />
            </div>

            <FormDialog
                open={open}
                onOpenChange={setOpen}
                title="Booking ruangan / lab"
                onSubmit={submit}
                processing={form.processing}
                submitLabel="Booking"
            >
                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="grid gap-2">
                        <Label htmlFor="rb-unit">Unit</Label>
                        <Select
                            value={unit || undefined}
                            onValueChange={(v) => {
                                setUnit(v);
                                form.setData('room_id', '');
                            }}
                        >
                            <SelectTrigger id="rb-unit">
                                <SelectValue placeholder="Pilih" />
                            </SelectTrigger>
                            <SelectContent>
                                {unitOptions.map((o) => (
                                    <SelectItem key={o.value} value={o.value}>
                                        {o.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="rb-date">Tanggal penggunaan</Label>
                        <DateInput
                            id="rb-date"
                            value={form.data.used_on}
                            onChange={(e) =>
                                form.setData('used_on', e.target.value)
                            }
                            required
                        />
                        <InputError message={form.errors.used_on} />
                    </div>
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="rb-room">Ruangan / fasilitas</Label>
                        <Select
                            value={form.data.room_id || undefined}
                            onValueChange={(v) => form.setData('room_id', v)}
                        >
                            <SelectTrigger id="rb-room">
                                <SelectValue
                                    placeholder={
                                        rooms.length === 0
                                            ? 'Belum ada ruangan di unit ini'
                                            : 'Pilih ruangan'
                                    }
                                />
                            </SelectTrigger>
                            <SelectContent>
                                {rooms.map((o) => (
                                    <SelectItem key={o.value} value={o.value}>
                                        {o.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={form.errors.room_id} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="rb-start">Jam mulai</Label>
                        <TimeInput
                            id="rb-start"
                            value={form.data.starts_at}
                            onChange={(v) => form.setData('starts_at', v)}
                            required
                        />
                        <InputError message={form.errors.starts_at} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="rb-end">Jam selesai</Label>
                        <TimeInput
                            id="rb-end"
                            value={form.data.ends_at}
                            onChange={(v) => form.setData('ends_at', v)}
                            required
                        />
                        <InputError message={form.errors.ends_at} />
                    </div>
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="rb-purpose">Keperluan (opsional)</Label>
                        <Input
                            id="rb-purpose"
                            value={form.data.purpose}
                            onChange={(e) =>
                                form.setData('purpose', e.target.value)
                            }
                            placeholder="Mis. Praktikum IPA kelas 5-A"
                            maxLength={150}
                        />
                        <InputError message={form.errors.purpose} />
                    </div>
                </div>
            </FormDialog>

            <ConfirmDialog
                open={cancelling !== null}
                onOpenChange={(o) => !o && setCancelling(null)}
                title="Batalkan booking?"
                description={
                    cancelling
                        ? `Booking ${cancelling.room} pukul ${dot(cancelling.starts_at)}–${dot(cancelling.ends_at)} dibatalkan.`
                        : ''
                }
                confirmLabel="Batalkan booking"
                variant="destructive"
                loading={busy}
                onConfirm={() =>
                    cancelling &&
                    router.delete(
                        RoomBookingController.destroy.url(cancelling.id),
                        {
                            preserveScroll: true,
                            onStart: () => setBusy(true),
                            onFinish: () => setBusy(false),
                            onSuccess: () => setCancelling(null),
                        },
                    )
                }
            />
        </StaffLayout>
    );
}

RoomBookings.layout = (page: ReactNode) => page;
