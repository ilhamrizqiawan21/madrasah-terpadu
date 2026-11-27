import { Head, router, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import SchoolCalendarController from '@/actions/App/Http/Controllers/Admin/SchoolCalendarController';
import { ConfirmDialog } from '@/components/app/confirm-dialog';
import type { DataTableColumn } from '@/components/app/data-table';
import { DataTable } from '@/components/app/data-table';
import { FormDialog } from '@/components/app/form-dialog';
import { PageHeader } from '@/components/app/page-header';
import { RowActions } from '@/components/app/row-actions';
import { StatusBadge } from '@/components/app/status-badge';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
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
import { formatDate } from '@/lib/format';
import { dashboard } from '@/routes/admin';
import type { PaginatedResource } from '@/types';

type HolidayItem = {
    id: number;
    unit_id: number | null;
    unit_label: string;
    holiday_date: string;
    title: string;
    is_day_off: boolean;
};

type UnitWeekend = {
    id: number;
    code: string;
    name: string;
    weekend_days: number[];
};

type Props = {
    holidays: PaginatedResource<HolidayItem>;
    units: UnitWeekend[];
    canManage: boolean;
};

const WEEKDAYS: { day: number; label: string }[] = [
    { day: 1, label: 'Sen' },
    { day: 2, label: 'Sel' },
    { day: 3, label: 'Rab' },
    { day: 4, label: 'Kam' },
    { day: 5, label: 'Jum' },
    { day: 6, label: 'Sab' },
    { day: 7, label: 'Min' },
];

export default function SchoolCalendarIndex({
    holidays,
    units,
    canManage,
}: Props) {
    const [dialogOpen, setDialogOpen] = useState(false);
    const [deleteTarget, setDeleteTarget] = useState<HolidayItem | null>(null);
    const [acting, setActing] = useState(false);

    const form = useForm<{
        unit_id: string;
        holiday_date: string;
        title: string;
        is_day_off: boolean;
    }>({
        unit_id: 'all',
        holiday_date: '',
        title: '',
        is_day_off: true,
    });

    const toggleWeekendDay = (
        unit: UnitWeekend,
        day: number,
        checked: boolean,
    ): void => {
        if (!canManage) {
            return;
        }

        const nextDays = checked
            ? [...new Set([...unit.weekend_days, day])].sort((a, b) => a - b)
            : unit.weekend_days.filter((d) => d !== day);

        router.put(
            SchoolCalendarController.updateWeekend.url(),
            {
                unit_id: unit.id,
                weekend_days: nextDays,
            },
            { preserveScroll: true },
        );
    };

    const submitHoliday = (event: FormEvent): void => {
        event.preventDefault();

        form.transform((data) => ({
            unit_id: data.unit_id === 'all' ? null : Number(data.unit_id),
            holiday_date: data.holiday_date,
            title: data.title,
            is_day_off: data.is_day_off,
        }));

        form.post(SchoolCalendarController.store.url(), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setDialogOpen(false);
            },
        });
    };

    const confirmDelete = (): void => {
        if (!deleteTarget) {
            return;
        }

        router.delete(SchoolCalendarController.destroy.url(deleteTarget.id), {
            preserveScroll: true,
            onStart: () => setActing(true),
            onFinish: () => {
                setActing(false);
                setDeleteTarget(null);
            },
        });
    };

    const columns: DataTableColumn<HolidayItem>[] = [
        {
            key: 'holiday_date',
            label: 'Tanggal',
            render: (item) => (
                <span className="font-medium">
                    {formatDate(item.holiday_date)}
                </span>
            ),
        },
        {
            key: 'title',
            label: 'Keterangan',
            render: (item) => item.title,
        },
        {
            key: 'unit_label',
            label: 'Unit',
            render: (item) => item.unit_label,
        },
        {
            key: 'is_day_off',
            label: 'Status',
            align: 'center',
            render: (item) => (
                <StatusBadge variant={item.is_day_off ? 'warning' : 'muted'}>
                    {item.is_day_off ? 'Libur presensi' : 'Acara sekolah'}
                </StatusBadge>
            ),
        },
        ...(canManage
            ? [
                  {
                      key: 'actions',
                      label: 'Aksi',
                      align: 'center' as const,
                      render: (item: HolidayItem) => (
                          <RowActions
                              itemName={item.title}
                              onDelete={() => setDeleteTarget(item)}
                          />
                      ),
                  },
              ]
            : []),
    ];

    return (
        <StaffLayout
            breadcrumbs={[
                { title: 'Beranda', href: dashboard().url },
                {
                    title: 'Kalender sekolah',
                    href: SchoolCalendarController.index.url(),
                },
            ]}
        >
            <Head title="Kalender sekolah" />

            <div className="space-y-6">
                <PageHeader
                    title="Kalender sekolah"
                    description="Atur hari libur per unit atau yayasan serta hari akhir pekan untuk presensi."
                    actions={
                        canManage ? (
                            <Button
                                type="button"
                                onClick={() => setDialogOpen(true)}
                            >
                                <Plus className="mr-1.5 size-4" />
                                Tambah tanggal
                            </Button>
                        ) : undefined
                    }
                />

                <Card className="shadow-xs">
                    <CardHeader className="pb-3">
                        <CardTitle className="text-base">
                            Hari akhir pekan per unit
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        {units.map((unit) => (
                            <div
                                key={unit.id}
                                className="flex flex-col gap-2 rounded-lg border border-border p-3 sm:flex-row sm:items-center sm:justify-between"
                            >
                                <div>
                                    <p className="font-medium">
                                        {unit.code} — {unit.name}
                                    </p>
                                </div>
                                <div className="flex flex-wrap gap-3">
                                    {WEEKDAYS.map(({ day, label }) => {
                                        const checked =
                                            unit.weekend_days.includes(day);
                                        const id = `weekend-${unit.id}-${day}`;

                                        return (
                                            <label
                                                key={day}
                                                htmlFor={id}
                                                className="inline-flex cursor-pointer items-center gap-1.5 text-sm"
                                            >
                                                <Checkbox
                                                    id={id}
                                                    checked={checked}
                                                    disabled={!canManage}
                                                    onCheckedChange={(value) =>
                                                        toggleWeekendDay(
                                                            unit,
                                                            day,
                                                            value === true,
                                                        )
                                                    }
                                                />
                                                <span>{label}</span>
                                            </label>
                                        );
                                    })}
                                </div>
                            </div>
                        ))}
                    </CardContent>
                </Card>

                <DataTable
                    columns={columns}
                    data={holidays.data}
                    rowKey={(item) => item.id}
                    pagination={{
                        currentPage: holidays.meta.current_page,
                        lastPage: holidays.meta.last_page,
                        perPage: holidays.meta.per_page,
                        total: holidays.meta.total,
                        from: holidays.meta.from,
                        to: holidays.meta.to,
                    }}
                    onPageChange={(page) =>
                        router.get(
                            SchoolCalendarController.index.url(),
                            { page },
                            { preserveState: true, preserveScroll: true },
                        )
                    }
                    empty={{
                        title: 'Belum ada tanggal libur atau acara',
                        description:
                            'Tambahkan hari libur nasional, cuti bersama, atau agenda khusus unit.',
                    }}
                />
            </div>

            <FormDialog
                open={dialogOpen}
                onOpenChange={setDialogOpen}
                title="Tambah tanggal kalender"
                description="Hari libur akan menonaktifkan scan masuk dan penandaan alpa otomatis."
                submitLabel="Simpan"
                processing={form.processing}
                onSubmit={submitHoliday}
            >
                <div className="grid gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="holiday-date">Tanggal</Label>
                        <Input
                            id="holiday-date"
                            type="date"
                            value={form.data.holiday_date}
                            onChange={(e) =>
                                form.setData('holiday_date', e.target.value)
                            }
                        />
                        <InputError message={form.errors.holiday_date} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="holiday-title">Keterangan</Label>
                        <Input
                            id="holiday-title"
                            value={form.data.title}
                            onChange={(e) =>
                                form.setData('title', e.target.value)
                            }
                            placeholder="Contoh: Libur Awal Ramadhan"
                        />
                        <InputError message={form.errors.title} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="holiday-unit">Berlaku untuk</Label>
                        <Select
                            value={form.data.unit_id}
                            onValueChange={(value) =>
                                form.setData('unit_id', value)
                            }
                        >
                            <SelectTrigger id="holiday-unit">
                                <SelectValue placeholder="Pilih unit" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">Semua unit</SelectItem>
                                {units.map((unit) => (
                                    <SelectItem
                                        key={unit.id}
                                        value={String(unit.id)}
                                    >
                                        {unit.code} — {unit.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={form.errors.unit_id} />
                    </div>

                    <label className="inline-flex items-center gap-2 text-sm">
                        <Checkbox
                            checked={form.data.is_day_off}
                            onCheckedChange={(checked) =>
                                form.setData('is_day_off', checked === true)
                            }
                        />
                        <span>
                            Liburkan presensi (scan ditolak &amp; tidak ada
                            alpa)
                        </span>
                    </label>
                </div>
            </FormDialog>

            <ConfirmDialog
                open={deleteTarget !== null}
                onOpenChange={(open) => !open && setDeleteTarget(null)}
                title="Hapus tanggal kalender?"
                description={`Tanggal ${deleteTarget ? formatDate(deleteTarget.holiday_date) : ''} (${deleteTarget?.title ?? ''}) akan dihapus dari kalender.`}
                confirmLabel="Hapus"
                variant="destructive"
                loading={acting}
                onConfirm={confirmDelete}
            />
        </StaffLayout>
    );
}
