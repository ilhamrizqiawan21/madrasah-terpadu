import { Head, router, useForm } from '@inertiajs/react';
import { CalendarPlus, Printer } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { useEffect, useRef, useState } from 'react';
import MeetingController from '@/actions/App/Http/Controllers/Admin/MeetingController';
import type { DataTableColumn } from '@/components/app/data-table';
import { DataTable } from '@/components/app/data-table';
import { DateInput } from '@/components/app/date-input';
import { DateText } from '@/components/app/date-text';
import { FormDialog } from '@/components/app/form-dialog';
import { PageHeader } from '@/components/app/page-header';
import { RowActions } from '@/components/app/row-actions';
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
import { Textarea } from '@/components/ui/textarea';
import StaffLayout from '@/layouts/staff-layout';
import type { PaginatedResource } from '@/types';

type Option = { value: string; label: string };

type MeetingRow = {
    id: number;
    title: string;
    held_on: string;
    unit_id: number | null;
    unit_name: string | null;
    audience: string;
    audience_label: string;
    minutes: string | null;
};

type Filters = {
    search: string;
    unit: number | null;
    sort: string;
    direction: 'asc' | 'desc';
};

type Props = {
    meetings: PaginatedResource<MeetingRow>;
    filters: Filters;
    unitOptions: Option[];
    audienceOptions: Option[];
};

type MeetingForm = {
    title: string;
    held_on: string;
    unit_id: string;
    audience: string;
    minutes: string;
};

const ALL = 'semua';

function today(): string {
    return new Date().toLocaleDateString('sv-SE', {
        timeZone: 'Asia/Makassar',
    });
}

export default function MeetingsIndex({
    meetings,
    filters,
    unitOptions,
    audienceOptions,
}: Props) {
    const [search, setSearch] = useState(filters.search);
    const [loading, setLoading] = useState(false);
    const [editing, setEditing] = useState<MeetingRow | null>(null);
    const [formOpen, setFormOpen] = useState(false);
    const [deletingId, setDeletingId] = useState<number | null>(null);
    const timer = useRef<number | undefined>(undefined);

    const form = useForm<MeetingForm>({
        title: '',
        held_on: today(),
        unit_id: ALL,
        audience: audienceOptions[0]?.value ?? 'all_staff',
        minutes: '',
    });

    useEffect(() => () => window.clearTimeout(timer.current), []);

    const visit = (
        changes: Partial<Filters> & { page?: number },
        searchValue = search,
    ) => {
        const query = { ...filters, search: searchValue, ...changes };
        const params = Object.fromEntries(
            Object.entries(query).filter(
                ([, v]) => v !== '' && v !== null && v !== undefined,
            ),
        );

        router.get(MeetingController.index.url(), params, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['meetings', 'filters'],
            onStart: () => setLoading(true),
            onFinish: () => setLoading(false),
        });
    };

    const openCreate = () => {
        setEditing(null);
        form.clearErrors();
        form.setData({
            title: '',
            held_on: today(),
            unit_id: filters.unit !== null ? String(filters.unit) : ALL,
            audience: audienceOptions[0]?.value ?? 'all_staff',
            minutes: '',
        });
        setFormOpen(true);
    };

    const openEdit = (row: MeetingRow) => {
        setEditing(row);
        form.clearErrors();
        form.setData({
            title: row.title,
            held_on: row.held_on,
            unit_id: row.unit_id !== null ? String(row.unit_id) : ALL,
            audience: row.audience,
            minutes: row.minutes ?? '',
        });
        setFormOpen(true);
    };

    const submit = (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        form.transform((data) => ({
            ...data,
            unit_id: data.unit_id === ALL ? null : data.unit_id,
        }));
        const options = {
            preserveScroll: true,
            onSuccess: () => setFormOpen(false),
        };

        if (editing) {
            form.put(MeetingController.update.url(editing.id), options);
        } else {
            form.post(MeetingController.store.url(), options);
        }
    };

    const destroy = (row: MeetingRow) => {
        router.delete(MeetingController.destroy.url(row.id), {
            preserveScroll: true,
            onStart: () => setDeletingId(row.id),
            onFinish: () => setDeletingId(null),
        });
    };

    const columns: DataTableColumn<MeetingRow>[] = [
        {
            key: 'held_on',
            label: 'Tanggal pelaksanaan',
            sortable: true,
            className: 'whitespace-nowrap',
            render: (row) => <DateText value={row.held_on} />,
        },
        {
            key: 'title',
            label: 'Agenda / topik rapat',
            sortable: true,
            render: (row) => (
                <div className="min-w-48">
                    <div className="font-medium">{row.title}</div>
                    <div className="text-xs text-muted-foreground">
                        {row.unit_name ?? 'Yayasan (semua unit)'}
                        <span className="@3xl:hidden">
                            {' '}
                            · {row.audience_label}
                        </span>
                    </div>
                </div>
            ),
        },
        {
            key: 'audience',
            label: 'Sasaran peserta',
            className: 'hidden @3xl:table-cell',
            render: (row) => row.audience_label,
        },
        {
            key: 'minutes',
            label: 'Status notulen',
            align: 'center',
            render: (row) =>
                row.minutes ? (
                    <StatusBadge variant="success">Sudah dicatat</StatusBadge>
                ) : (
                    <StatusBadge variant="muted">Belum ada notulen</StatusBadge>
                ),
        },
        {
            key: 'actions',
            label: 'Aksi',
            align: 'center',
            render: (row) => (
                <RowActions
                    onEdit={() => openEdit(row)}
                    onDelete={() => destroy(row)}
                    deleting={deletingId === row.id}
                    itemName={row.title}
                    deleteTitle="Hapus agenda rapat?"
                    deleteDescription={`Agenda "${row.title}" beserta notulennya dihapus.`}
                />
            ),
        },
    ];

    return (
        <StaffLayout
            breadcrumbs={[
                { title: 'Pengguna', href: '#' },
                {
                    title: 'Undangan Rapat',
                    href: MeetingController.index.url(),
                },
            ]}
        >
            <Head title="Undangan Rapat" />

            <div className="@container space-y-4">
                <PageHeader
                    title="Undangan Rapat"
                    description="Jadwal rapat dinas guru dan staf beserta notulen hasil keputusan."
                    actions={
                        <>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => window.print()}
                            >
                                <Printer
                                    className="size-4"
                                    strokeWidth={1.75}
                                />
                                Cetak jadwal
                            </Button>
                            <Button type="button" onClick={openCreate}>
                                <CalendarPlus
                                    className="size-4"
                                    strokeWidth={1.75}
                                />
                                Buat agenda rapat
                            </Button>
                        </>
                    }
                />

                <div className="flex flex-wrap gap-2 print:hidden">
                    <Input
                        type="search"
                        value={search}
                        onChange={(e) => {
                            const value = e.target.value;
                            setSearch(value);
                            window.clearTimeout(timer.current);
                            timer.current = window.setTimeout(
                                () => visit({ page: 1 }, value),
                                300,
                            );
                        }}
                        placeholder="Cari topik agenda"
                        aria-label="Cari agenda rapat"
                        className="w-full @3xl:w-72"
                    />
                    <Select
                        value={
                            filters.unit === null ? ALL : String(filters.unit)
                        }
                        onValueChange={(v) =>
                            visit({
                                unit: v === ALL ? null : Number(v),
                                page: 1,
                            })
                        }
                    >
                        <SelectTrigger
                            className="w-full @lg:w-52"
                            aria-label="Saring unit"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL}>Semua unit</SelectItem>
                            {unitOptions.map((u) => (
                                <SelectItem key={u.value} value={u.value}>
                                    {u.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                <DataTable
                    columns={columns}
                    data={meetings.data}
                    rowKey={(row) => row.id}
                    loading={loading}
                    sort={{ key: filters.sort, direction: filters.direction }}
                    onSortChange={(key, direction) =>
                        visit({ sort: key, direction, page: 1 })
                    }
                    pagination={{
                        currentPage: meetings.meta.current_page,
                        lastPage: meetings.meta.last_page,
                        perPage: meetings.meta.per_page,
                        total: meetings.meta.total,
                        from: meetings.meta.from,
                        to: meetings.meta.to,
                    }}
                    onPageChange={(page) => visit({ page })}
                    empty={{
                        title: 'Belum ada agenda rapat',
                        description:
                            'Jadwalkan rapat dinas agar guru dan staf mengetahui agendanya.',
                        action: (
                            <Button type="button" onClick={openCreate}>
                                <CalendarPlus
                                    className="size-4"
                                    strokeWidth={1.75}
                                />
                                Buat agenda rapat
                            </Button>
                        ),
                    }}
                />
            </div>

            <FormDialog
                open={formOpen}
                onOpenChange={setFormOpen}
                title={
                    editing ? 'Ubah agenda & notulen' : 'Jadwalkan agenda rapat'
                }
                onSubmit={submit}
                processing={form.processing}
                submitLabel="Simpan agenda"
                contentClassName="sm:max-w-2xl"
            >
                <FormField
                    label="Agenda / topik pembahasan"
                    htmlFor="meeting-title"
                    error={form.errors.title}
                >
                    <Input
                        id="meeting-title"
                        value={form.data.title}
                        onChange={(e) => form.setData('title', e.target.value)}
                        placeholder="Mis. Rapat evaluasi kurikulum"
                        required
                    />
                </FormField>
                <div className="grid gap-4 sm:grid-cols-3">
                    <FormField
                        label="Tanggal pelaksanaan"
                        htmlFor="meeting-date"
                        error={form.errors.held_on}
                    >
                        <DateInput
                            id="meeting-date"
                            value={form.data.held_on}
                            onChange={(e) =>
                                form.setData('held_on', e.target.value)
                            }
                        />
                    </FormField>
                    <FormField
                        label="Unit"
                        htmlFor="meeting-unit"
                        error={form.errors.unit_id}
                    >
                        <Select
                            value={form.data.unit_id}
                            onValueChange={(v) => form.setData('unit_id', v)}
                        >
                            <SelectTrigger id="meeting-unit">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={ALL}>
                                    Yayasan (semua unit)
                                </SelectItem>
                                {unitOptions.map((u) => (
                                    <SelectItem key={u.value} value={u.value}>
                                        {u.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </FormField>
                    <FormField
                        label="Sasaran peserta"
                        htmlFor="meeting-audience"
                        error={form.errors.audience}
                    >
                        <Select
                            value={form.data.audience}
                            onValueChange={(v) => form.setData('audience', v)}
                        >
                            <SelectTrigger id="meeting-audience">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {audienceOptions.map((a) => (
                                    <SelectItem key={a.value} value={a.value}>
                                        {a.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </FormField>
                </div>
                <FormField
                    label="Notulen hasil keputusan (boleh diisi setelah rapat)"
                    htmlFor="meeting-minutes"
                    error={form.errors.minutes}
                >
                    <Textarea
                        id="meeting-minutes"
                        value={form.data.minutes}
                        onChange={(e) =>
                            form.setData('minutes', e.target.value)
                        }
                        rows={8}
                        placeholder={'1. Keputusan pertama\n2. Keputusan kedua'}
                    />
                </FormField>
            </FormDialog>
        </StaffLayout>
    );
}

MeetingsIndex.layout = (page: ReactNode) => page;

function FormField({
    label,
    htmlFor,
    error,
    children,
}: {
    label: string;
    htmlFor: string;
    error?: string;
    children: ReactNode;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={htmlFor}>{label}</Label>
            {children}
            <InputError message={error} />
        </div>
    );
}
