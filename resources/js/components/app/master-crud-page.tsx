import { Head, router, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { useEffect, useRef, useState } from 'react';
import type { DataTableColumn } from '@/components/app/data-table';
import { DataTable } from '@/components/app/data-table';
import { FormDialog } from '@/components/app/form-dialog';
import { PageHeader } from '@/components/app/page-header';
import { RowActions } from '@/components/app/row-actions';
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

export type MasterOption = { value: string; label: string };

export type MasterField = {
    key: string;
    label: string;
    type?: 'text' | 'number' | 'time' | 'select';
    options?: MasterOption[];
    required?: boolean;
    placeholder?: string;
    /** Lebar kolom form pada layar lebar (grid 2 kolom). */
    wide?: boolean;
};

export type MasterFilter = {
    key: string;
    label: string;
    allLabel: string;
    options: MasterOption[];
};

type Row = { id: number } & Record<string, unknown>;

type Props<T extends Row> = {
    title: string;
    description: string;
    group: string;
    itemNoun: string;
    rows: PaginatedResource<T>;
    columns: DataTableColumn<T>[];
    fields: MasterField[];
    filters: Record<string, string | number | null>;
    filterSelects?: MasterFilter[];
    searchPlaceholder: string;
    urls: {
        index: string;
        store: string;
        update: (id: number) => string;
        destroy: (id: number) => string;
    };
    toForm: (row: T) => Record<string, string>;
    defaults: Record<string, string>;
    emptyDescription: string;
};

const ALL = 'semua';

/**
 * Pola halaman master sederhana (daftar + filter + form dialog + hapus) untuk menu Akademik
 * hasil duplikasi aplikasi lama. Tabel menyesuaikan lebar area konten lewat container query.
 */
export function MasterCrudPage<T extends Row>({
    title,
    description,
    group,
    itemNoun,
    rows,
    columns,
    fields,
    filters,
    filterSelects = [],
    searchPlaceholder,
    urls,
    toForm,
    defaults,
    emptyDescription,
}: Props<T>) {
    const [search, setSearch] = useState(String(filters.search ?? ''));
    const [loading, setLoading] = useState(false);
    const [editing, setEditing] = useState<T | null>(null);
    const [open, setOpen] = useState(false);
    const [deletingId, setDeletingId] = useState<number | null>(null);
    const timer = useRef<number | undefined>(undefined);
    const form = useForm<Record<string, string>>(defaults);

    useEffect(() => () => window.clearTimeout(timer.current), []);

    const visit = (
        changes: Record<string, string | number | null>,
        q = search,
    ) => {
        const params = Object.fromEntries(
            Object.entries({ ...filters, search: q, ...changes }).filter(
                ([, v]) => v !== '' && v !== null && v !== undefined,
            ),
        );
        router.get(urls.index, params, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onStart: () => setLoading(true),
            onFinish: () => setLoading(false),
        });
    };

    const openCreate = () => {
        setEditing(null);
        form.clearErrors();
        form.setData(defaults);
        setOpen(true);
    };

    const openEdit = (row: T) => {
        setEditing(row);
        form.clearErrors();
        form.setData({ ...defaults, ...toForm(row) });
        setOpen(true);
    };

    const submit = (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        };

        if (editing) {
            form.put(urls.update(editing.id), options);
        } else {
            form.post(urls.store, options);
        }
    };

    const allColumns: DataTableColumn<T>[] = [
        ...columns,
        {
            key: 'actions',
            label: 'Aksi',
            align: 'center',
            render: (row) => (
                <RowActions
                    onEdit={() => openEdit(row)}
                    onDelete={() =>
                        router.delete(urls.destroy(row.id), {
                            preserveScroll: true,
                            onStart: () => setDeletingId(row.id),
                            onFinish: () => setDeletingId(null),
                        })
                    }
                    deleting={deletingId === row.id}
                    itemName={itemNoun}
                    deleteTitle={`Hapus ${itemNoun}?`}
                    deleteDescription={`Data ${itemNoun} ini dihapus permanen.`}
                />
            ),
        },
    ];

    const addButton = (
        <Button type="button" onClick={openCreate}>
            <Plus className="size-4" strokeWidth={1.75} />
            Tambah {itemNoun}
        </Button>
    );

    return (
        <StaffLayout
            breadcrumbs={[
                { title: group, href: '#' },
                { title, href: urls.index },
            ]}
        >
            <Head title={title} />

            <div className="@container space-y-4">
                <PageHeader
                    title={title}
                    description={description}
                    actions={addButton}
                />

                <div className="flex flex-wrap gap-2">
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
                        placeholder={searchPlaceholder}
                        aria-label={searchPlaceholder}
                        className="w-full @3xl:w-72"
                    />
                    {filterSelects.map((f) => (
                        <Select
                            key={f.key}
                            value={
                                filters[f.key] === null ||
                                filters[f.key] === undefined
                                    ? ALL
                                    : String(filters[f.key])
                            }
                            onValueChange={(v) =>
                                visit({
                                    [f.key]: v === ALL ? null : v,
                                    page: 1,
                                })
                            }
                        >
                            <SelectTrigger
                                className="w-full @lg:w-56"
                                aria-label={f.label}
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={ALL}>
                                    {f.allLabel}
                                </SelectItem>
                                {f.options.map((o) => (
                                    <SelectItem key={o.value} value={o.value}>
                                        {o.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    ))}
                </div>

                <DataTable
                    columns={allColumns}
                    data={rows.data}
                    rowKey={(row) => row.id}
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
                        title: `Belum ada ${itemNoun}`,
                        description: emptyDescription,
                        action: addButton,
                    }}
                />
            </div>

            <FormDialog
                open={open}
                onOpenChange={setOpen}
                title={editing ? `Ubah ${itemNoun}` : `Tambah ${itemNoun}`}
                onSubmit={submit}
                processing={form.processing}
                submitLabel="Simpan"
            >
                <div className="grid gap-4 sm:grid-cols-2">
                    {fields.map((field) => (
                        <div
                            key={field.key}
                            className={
                                field.wide
                                    ? 'grid gap-2 sm:col-span-2'
                                    : 'grid gap-2'
                            }
                        >
                            <Label htmlFor={`master-${field.key}`}>
                                {field.label}
                            </Label>
                            {field.type === 'select' ? (
                                <Select
                                    value={form.data[field.key] || undefined}
                                    onValueChange={(v) =>
                                        form.setData(field.key, v)
                                    }
                                >
                                    <SelectTrigger id={`master-${field.key}`}>
                                        <SelectValue placeholder="Pilih" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {(field.options ?? []).map((o) => (
                                            <SelectItem
                                                key={o.value}
                                                value={o.value}
                                            >
                                                {o.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            ) : (
                                <Input
                                    id={`master-${field.key}`}
                                    type={field.type ?? 'text'}
                                    inputMode={
                                        field.type === 'number'
                                            ? 'numeric'
                                            : undefined
                                    }
                                    value={form.data[field.key] ?? ''}
                                    onChange={(e) =>
                                        form.setData(field.key, e.target.value)
                                    }
                                    placeholder={field.placeholder}
                                    required={field.required}
                                    autoComplete="off"
                                />
                            )}
                            <InputError message={form.errors[field.key]} />
                        </div>
                    ))}
                </div>
            </FormDialog>
        </StaffLayout>
    );
}

export function masterLayout(page: ReactNode) {
    return page;
}
