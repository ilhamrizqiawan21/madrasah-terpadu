import { Head, router, useForm } from '@inertiajs/react';
import { Plus, Power, PowerOff } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import UnitController from '@/actions/App/Http/Controllers/Admin/UnitController';
import { ConfirmDialog } from '@/components/app/confirm-dialog';
import type { DataTableColumn } from '@/components/app/data-table';
import { DataTable } from '@/components/app/data-table';
import { FormDialog } from '@/components/app/form-dialog';
import { RowActions } from '@/components/app/row-actions';
import { SettingsShell } from '@/components/app/settings-shell';
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

type AttendanceHours = {
    check_in_opens_at: string;
    late_after: string;
    absent_after: string;
    check_out_opens_at: string;
};

type Unit = {
    id: number;
    code: string;
    name: string;
    is_active: boolean;
    attendance: AttendanceHours;
};

type UnitForm = { code: string; name: string } & AttendanceHours;

type Props = {
    units: Unit[];
    defaultAttendance: AttendanceHours;
};

const HOUR_FIELDS: { key: keyof AttendanceHours; label: string }[] = [
    { key: 'check_in_opens_at', label: 'Jam masuk dibuka' },
    { key: 'late_after', label: 'Terlambat setelah' },
    { key: 'absent_after', label: 'Alpa setelah' },
    { key: 'check_out_opens_at', label: 'Pulang mulai' },
];

export default function UnitsSettings({ units, defaultAttendance }: Props) {
    const [editing, setEditing] = useState<Unit | null>(null);
    const [formOpen, setFormOpen] = useState(false);
    const [statusTarget, setStatusTarget] = useState<Unit | null>(null);
    const [acting, setActing] = useState(false);

    const form = useForm<UnitForm>({
        code: '',
        name: '',
        ...defaultAttendance,
    });

    const openCreate = (): void => {
        setEditing(null);
        form.clearErrors();
        form.setData({ code: '', name: '', ...defaultAttendance });
        setFormOpen(true);
    };

    const openEdit = (unit: Unit): void => {
        setEditing(unit);
        form.clearErrors();
        form.setData({ code: unit.code, name: unit.name, ...unit.attendance });
        setFormOpen(true);
    };

    const submit = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();

        const options = {
            preserveScroll: true,
            onSuccess: () => setFormOpen(false),
        };

        if (editing) {
            form.put(UnitController.update.url(editing.id), options);
        } else {
            form.post(UnitController.store.url(), options);
        }
    };

    const confirmStatus = (): void => {
        if (!statusTarget) {
            return;
        }

        router.patch(
            UnitController.updateStatus.url(statusTarget.id),
            { is_active: !statusTarget.is_active },
            {
                preserveScroll: true,
                onStart: () => setActing(true),
                onFinish: () => {
                    setActing(false);
                    setStatusTarget(null);
                },
            },
        );
    };

    const columns: DataTableColumn<Unit>[] = [
        {
            key: 'unit',
            label: 'Unit',
            render: (unit) => (
                <div className="min-w-36">
                    <div className="font-medium text-foreground">
                        {unit.code}
                    </div>
                    <div className="text-xs text-muted-foreground">
                        {unit.name}
                    </div>
                </div>
            ),
        },
        ...HOUR_FIELDS.map((field): DataTableColumn<Unit> => ({
            key: field.key,
            label: field.label,
            align: 'center',
            className: 'tabular-nums whitespace-nowrap',
            render: (unit) => unit.attendance[field.key].replace(':', '.'),
        })),
        {
            key: 'status',
            label: 'Status',
            align: 'center',
            render: (unit) =>
                unit.is_active ? (
                    <StatusBadge variant="success">Aktif</StatusBadge>
                ) : (
                    <StatusBadge variant="muted">Nonaktif</StatusBadge>
                ),
        },
        {
            key: 'actions',
            label: 'Aksi',
            align: 'center',
            render: (unit) => (
                <RowActions onEdit={() => openEdit(unit)} itemName={unit.code}>
                    <Tooltip>
                        <TooltipTrigger asChild>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                aria-label={`${unit.is_active ? 'Nonaktifkan' : 'Aktifkan'} ${unit.code}`}
                                onClick={() => setStatusTarget(unit)}
                                className={
                                    unit.is_active
                                        ? 'text-destructive hover:bg-destructive/10 hover:text-destructive'
                                        : undefined
                                }
                            >
                                {unit.is_active ? (
                                    <PowerOff
                                        className="size-4"
                                        strokeWidth={1.75}
                                    />
                                ) : (
                                    <Power
                                        className="size-4"
                                        strokeWidth={1.75}
                                    />
                                )}
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent>
                            {unit.is_active ? 'Nonaktifkan' : 'Aktifkan'}
                        </TooltipContent>
                    </Tooltip>
                </RowActions>
            ),
        },
    ];

    return (
        <SettingsShell
            title="Unit & jam presensi"
            description="Jam gerbang per unit. Dipakai untuk menentukan hadir, terlambat, dan alpa."
            wide
            actions={
                <Button type="button" onClick={openCreate}>
                    <Plus className="size-4" strokeWidth={1.75} />
                    Tambah unit
                </Button>
            }
        >
            <Head title="Unit & jam presensi" />

            <DataTable
                columns={columns}
                data={units}
                rowKey={(unit) => unit.id}
                empty={{
                    title: 'Belum ada unit',
                    description:
                        'Tambahkan unit (MI, RA, atau TPQ) agar rombel, siswa, dan presensi bisa diatur per unit.',
                    action: (
                        <Button type="button" onClick={openCreate}>
                            <Plus className="size-4" strokeWidth={1.75} />
                            Tambah unit
                        </Button>
                    ),
                }}
            />

            <FormDialog
                open={formOpen}
                onOpenChange={setFormOpen}
                title={editing ? `Ubah unit ${editing.code}` : 'Tambah unit'}
                onSubmit={submit}
                processing={form.processing}
            >
                <div className="grid gap-4 sm:grid-cols-[8rem_minmax(0,1fr)]">
                    <div className="grid content-start gap-2">
                        <Label htmlFor="unit-code">Kode</Label>
                        <Input
                            id="unit-code"
                            value={form.data.code}
                            onChange={(event) =>
                                form.setData('code', event.target.value)
                            }
                            maxLength={10}
                            autoComplete="off"
                            autoCapitalize="characters"
                            required
                            aria-invalid={Boolean(form.errors.code)}
                        />
                        <InputError message={form.errors.code} />
                    </div>
                    <div className="grid content-start gap-2">
                        <Label htmlFor="unit-name">Nama unit</Label>
                        <Input
                            id="unit-name"
                            value={form.data.name}
                            onChange={(event) =>
                                form.setData('name', event.target.value)
                            }
                            maxLength={100}
                            autoComplete="off"
                            required
                            aria-invalid={Boolean(form.errors.name)}
                        />
                        <InputError message={form.errors.name} />
                    </div>
                </div>

                <fieldset className="grid gap-2">
                    <legend className="mb-1 text-sm font-medium">
                        Jam presensi (WITA)
                    </legend>
                    <div className="grid grid-cols-2 items-start gap-4">
                        {HOUR_FIELDS.map((field) => {
                            const id = `unit-${field.key}`;

                            return (
                                <div key={field.key} className="grid gap-2">
                                    <Label
                                        htmlFor={id}
                                        className="font-normal text-muted-foreground"
                                    >
                                        {field.label}
                                    </Label>
                                    <Input
                                        id={id}
                                        type="time"
                                        step={60}
                                        value={form.data[field.key]}
                                        onChange={(event) =>
                                            form.setData(
                                                field.key,
                                                event.target.value,
                                            )
                                        }
                                        className="tabular-nums"
                                        required
                                        aria-invalid={Boolean(
                                            form.errors[field.key],
                                        )}
                                    />
                                    <InputError
                                        message={form.errors[field.key]}
                                    />
                                </div>
                            );
                        })}
                    </div>
                </fieldset>
            </FormDialog>

            <ConfirmDialog
                open={statusTarget !== null}
                onOpenChange={(open) => !open && setStatusTarget(null)}
                title={
                    statusTarget?.is_active
                        ? `Nonaktifkan unit ${statusTarget.code}?`
                        : `Aktifkan unit ${statusTarget?.code ?? ''}?`
                }
                description={
                    statusTarget?.is_active
                        ? 'Unit nonaktif tidak bisa dipilih untuk rombel dan siswa baru. Data lama tetap tersimpan.'
                        : 'Unit dapat dipilih kembali untuk rombel dan siswa.'
                }
                confirmLabel={
                    statusTarget?.is_active ? 'Nonaktifkan' : 'Aktifkan'
                }
                variant={statusTarget?.is_active ? 'destructive' : 'default'}
                loading={acting}
                onConfirm={confirmStatus}
            />
        </SettingsShell>
    );
}
