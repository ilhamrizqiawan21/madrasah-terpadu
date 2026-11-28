import { Head, router, useForm } from '@inertiajs/react';
import { FileText, Upload } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { useEffect, useRef, useState } from 'react';
import TeachingModuleController from '@/actions/App/Http/Controllers/Admin/TeachingModuleController';
import { ConfirmDialog } from '@/components/app/confirm-dialog';
import type { DataTableColumn } from '@/components/app/data-table';
import { DataTable } from '@/components/app/data-table';
import { FormDialog } from '@/components/app/form-dialog';
import { PageHeader } from '@/components/app/page-header';
import type { StatusBadgeVariant } from '@/components/app/status-badge';
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
type Status = 'pending' | 'approved' | 'revision';
type Row = {
    id: number;
    unit_code: string;
    week_number: number;
    title: string;
    teacher: string;
    is_mine: boolean;
    original_name: string;
    status: Status;
    review_notes: string | null;
    reviewer: string | null;
    uploaded_at: string | null;
};

type Props = {
    rows: PaginatedResource<Row>;
    filters: { search: string; unit: number | null; status: Status | null };
    unitOptions: Option[];
    statusOptions: Option[];
    canUpload: boolean;
    canReview: boolean;
};

const ALL = 'semua';
const BADGE: Record<Status, { variant: StatusBadgeVariant; label: string }> = {
    pending: { variant: 'attention', label: 'Menunggu review' },
    approved: { variant: 'success', label: 'Disetujui' },
    revision: { variant: 'warning', label: 'Perlu revisi' },
};

export default function TeachingModules({
    rows,
    filters,
    unitOptions,
    statusOptions,
    canUpload,
    canReview,
}: Props) {
    const [search, setSearch] = useState(filters.search);
    const [loading, setLoading] = useState(false);
    const [uploadOpen, setUploadOpen] = useState(false);
    const [reviewing, setReviewing] = useState<Row | null>(null);
    const [resubmitting, setResubmitting] = useState<Row | null>(null);
    const [deleting, setDeleting] = useState<Row | null>(null);
    const [deletingBusy, setDeletingBusy] = useState(false);
    const timer = useRef<number | undefined>(undefined);

    const uploadForm = useForm<{
        unit_id: string;
        week_number: string;
        title: string;
        file: File | null;
    }>({
        unit_id: unitOptions[0]?.value ?? '',
        week_number: '1',
        title: '',
        file: null,
    });
    const reviewForm = useForm<{
        status: 'approved' | 'revision';
        review_notes: string;
    }>({ status: 'approved', review_notes: '' });
    const resubmitForm = useForm<{ file: File | null }>({ file: null });

    useEffect(() => () => window.clearTimeout(timer.current), []);

    const visit = (
        changes: Record<string, string | number | null>,
        q = search,
    ) =>
        router.get(
            TeachingModuleController.index.url(),
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

    const submitUpload = (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        uploadForm.post(TeachingModuleController.store.url(), {
            preserveScroll: true,
            onSuccess: () => {
                uploadForm.reset();
                setUploadOpen(false);
            },
        });
    };

    const openReview = (row: Row) => {
        reviewForm.clearErrors();
        reviewForm.setData({
            status: row.status === 'revision' ? 'revision' : 'approved',
            review_notes: row.review_notes ?? '',
        });
        setReviewing(row);
    };

    const submitReview = (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        if (!reviewing) {
            return;
        }
        reviewForm.put(TeachingModuleController.review.url(reviewing.id), {
            preserveScroll: true,
            onSuccess: () => setReviewing(null),
        });
    };

    const submitResubmit = (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        if (!resubmitting) {
            return;
        }
        resubmitForm.post(
            TeachingModuleController.resubmit.url(resubmitting.id),
            {
                preserveScroll: true,
                onSuccess: () => {
                    resubmitForm.reset();
                    setResubmitting(null);
                },
            },
        );
    };

    const columns: DataTableColumn<Row>[] = [
        {
            key: 'week',
            label: 'Minggu ke',
            align: 'center',
            className: 'tabular-nums',
            render: (r) => r.week_number,
        },
        {
            key: 'title',
            label: 'Judul modul ajar',
            render: (r) => (
                <div>
                    <div className="font-medium">{r.title}</div>
                    <div className="text-xs text-muted-foreground @3xl:hidden">
                        {r.teacher}
                    </div>
                </div>
            ),
        },
        {
            key: 'teacher',
            label: 'Guru pengunggah',
            className: 'hidden @3xl:table-cell',
            render: (r) => r.teacher,
        },
        {
            key: 'file',
            label: 'Berkas',
            render: (r) => (
                <a
                    href={TeachingModuleController.download.url(r.id)}
                    className="inline-flex max-w-48 items-center gap-1 text-primary underline-offset-4 hover:underline"
                >
                    <FileText className="size-4 shrink-0" strokeWidth={1.75} />
                    <span className="truncate">{r.original_name}</span>
                </a>
            ),
        },
        {
            key: 'status',
            label: 'Status supervisi',
            render: (r) => (
                <div className="space-y-1">
                    <StatusBadge variant={BADGE[r.status].variant}>
                        {BADGE[r.status].label}
                    </StatusBadge>
                    {r.review_notes && (
                        <p className="max-w-64 text-xs text-muted-foreground">
                            {r.review_notes}
                        </p>
                    )}
                </div>
            ),
        },
        {
            key: 'actions',
            label: 'Aksi',
            align: 'center',
            render: (r) => (
                <div className="flex flex-wrap justify-center gap-2">
                    {canReview && (
                        <Button
                            size="sm"
                            variant={
                                r.status === 'pending' ? 'default' : 'outline'
                            }
                            onClick={() => openReview(r)}
                        >
                            Review
                        </Button>
                    )}
                    {r.is_mine && r.status === 'revision' && (
                        <Button
                            size="sm"
                            variant="soft"
                            onClick={() => setResubmitting(r)}
                        >
                            Unggah revisi
                        </Button>
                    )}
                    {r.status !== 'approved' && (r.is_mine || canReview) && (
                        <Button
                            size="sm"
                            variant="outline"
                            className="text-destructive"
                            onClick={() => setDeleting(r)}
                        >
                            Hapus
                        </Button>
                    )}
                </div>
            ),
        },
    ];

    const uploadButton = canUpload && (
        <Button type="button" onClick={() => setUploadOpen(true)}>
            <Upload className="size-4" strokeWidth={1.75} />
            Unggah modul ajar
        </Button>
    );

    return (
        <StaffLayout
            breadcrumbs={[
                { title: 'Akademik', href: '#' },
                {
                    title: 'Supervisi Modul Ajar',
                    href: TeachingModuleController.index.url(),
                },
            ]}
        >
            <Head title="Supervisi Modul Ajar" />

            <div className="@container space-y-4">
                <PageHeader
                    title="Supervisi Modul Ajar"
                    description={
                        canReview
                            ? 'Arsip modul ajar (RPP) mingguan guru. Tinjau, setujui, atau minta perbaikan.'
                            : 'Unggah modul ajar (RPP) mingguan Anda untuk ditinjau kepala sekolah.'
                    }
                    actions={uploadButton || undefined}
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
                        placeholder="Cari judul atau guru"
                        aria-label="Cari judul atau guru"
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
                    <Select
                        value={filters.status ?? ALL}
                        onValueChange={(v) =>
                            visit({ status: v === ALL ? null : v, page: 1 })
                        }
                    >
                        <SelectTrigger
                            className="w-full @lg:w-48"
                            aria-label="Saring status"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL}>Semua status</SelectItem>
                            {statusOptions.map((o) => (
                                <SelectItem key={o.value} value={o.value}>
                                    {o.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
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
                        title: 'Belum ada modul ajar',
                        description: canUpload
                            ? 'Unggah RPP mingguan (PDF atau DOCX, maks. 5 MB) untuk ditinjau.'
                            : 'Belum ada guru yang mengunggah modul ajar.',
                        action: uploadButton || undefined,
                    }}
                />
            </div>

            <FormDialog
                open={uploadOpen}
                onOpenChange={setUploadOpen}
                title="Unggah modul ajar (RPP)"
                onSubmit={submitUpload}
                processing={uploadForm.processing}
                submitLabel="Kirim ke kepala sekolah"
            >
                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="grid gap-2">
                        <Label htmlFor="tm-unit">Unit</Label>
                        <Select
                            value={uploadForm.data.unit_id || undefined}
                            onValueChange={(v) =>
                                uploadForm.setData('unit_id', v)
                            }
                        >
                            <SelectTrigger id="tm-unit">
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
                        <InputError message={uploadForm.errors.unit_id} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="tm-week">Minggu ke-</Label>
                        <Input
                            id="tm-week"
                            type="number"
                            inputMode="numeric"
                            min={1}
                            max={52}
                            value={uploadForm.data.week_number}
                            onChange={(e) =>
                                uploadForm.setData(
                                    'week_number',
                                    e.target.value,
                                )
                            }
                            required
                        />
                        <InputError message={uploadForm.errors.week_number} />
                    </div>
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="tm-title">
                            Judul dokumen modul ajar
                        </Label>
                        <Input
                            id="tm-title"
                            value={uploadForm.data.title}
                            onChange={(e) =>
                                uploadForm.setData('title', e.target.value)
                            }
                            placeholder="Mis. Modul Ajar Matematika Bab 3 Pecahan"
                            maxLength={150}
                            required
                        />
                        <InputError message={uploadForm.errors.title} />
                    </div>
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="tm-file">
                            Berkas RPP (PDF / DOCX, maks. 5 MB)
                        </Label>
                        <Input
                            id="tm-file"
                            type="file"
                            accept=".pdf,.doc,.docx"
                            onChange={(e) =>
                                uploadForm.setData(
                                    'file',
                                    e.target.files?.[0] ?? null,
                                )
                            }
                            required
                        />
                        <InputError message={uploadForm.errors.file} />
                    </div>
                </div>
            </FormDialog>

            <FormDialog
                open={reviewing !== null}
                onOpenChange={(o) => !o && setReviewing(null)}
                title="Verifikasi modul ajar"
                onSubmit={submitReview}
                processing={reviewForm.processing}
                submitLabel="Simpan keputusan"
            >
                {reviewing && (
                    <div className="rounded-md border border-border bg-muted/40 p-3 text-sm">
                        <div className="text-xs text-muted-foreground">
                            Dokumen ditinjau
                        </div>
                        <div className="font-medium">{reviewing.title}</div>
                        <div className="text-muted-foreground">
                            Minggu ke-{reviewing.week_number} ·{' '}
                            {reviewing.teacher} ·{' '}
                            <a
                                href={TeachingModuleController.download.url(
                                    reviewing.id,
                                )}
                                className="text-primary underline-offset-4 hover:underline"
                            >
                                Unduh berkas
                            </a>
                        </div>
                    </div>
                )}
                <div className="grid gap-2">
                    <Label htmlFor="tm-decision">
                        Keputusan status supervisi
                    </Label>
                    <Select
                        value={reviewForm.data.status}
                        onValueChange={(v) =>
                            reviewForm.setData(
                                'status',
                                v as 'approved' | 'revision',
                            )
                        }
                    >
                        <SelectTrigger id="tm-decision">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="approved">
                                Setujui dokumen
                            </SelectItem>
                            <SelectItem value="revision">
                                Minta perbaikan (revisi)
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="tm-notes">
                        Catatan evaluasi / masukan perbaikan
                        {reviewForm.data.status === 'approved' && ' (opsional)'}
                    </Label>
                    <Textarea
                        id="tm-notes"
                        rows={4}
                        value={reviewForm.data.review_notes}
                        onChange={(e) =>
                            reviewForm.setData('review_notes', e.target.value)
                        }
                        maxLength={2000}
                    />
                    <InputError message={reviewForm.errors.review_notes} />
                </div>
            </FormDialog>

            <FormDialog
                open={resubmitting !== null}
                onOpenChange={(o) => !o && setResubmitting(null)}
                title="Unggah berkas revisi"
                onSubmit={submitResubmit}
                processing={resubmitForm.processing}
                submitLabel="Kirim ulang"
            >
                {resubmitting?.review_notes && (
                    <div className="rounded-md border border-border bg-muted/40 p-3 text-sm">
                        <div className="text-xs text-muted-foreground">
                            Catatan dari peninjau
                        </div>
                        <p>{resubmitting.review_notes}</p>
                    </div>
                )}
                <div className="grid gap-2">
                    <Label htmlFor="tm-refile">
                        Berkas RPP perbaikan (PDF / DOCX, maks. 5 MB)
                    </Label>
                    <Input
                        id="tm-refile"
                        type="file"
                        accept=".pdf,.doc,.docx"
                        onChange={(e) =>
                            resubmitForm.setData(
                                'file',
                                e.target.files?.[0] ?? null,
                            )
                        }
                        required
                    />
                    <InputError message={resubmitForm.errors.file} />
                </div>
            </FormDialog>

            <ConfirmDialog
                open={deleting !== null}
                onOpenChange={(o) => !o && setDeleting(null)}
                title="Hapus modul ajar?"
                description={
                    deleting
                        ? `"${deleting.title}" dan berkasnya dihapus permanen.`
                        : ''
                }
                confirmLabel="Hapus"
                variant="destructive"
                loading={deletingBusy}
                onConfirm={() =>
                    deleting &&
                    router.delete(
                        TeachingModuleController.destroy.url(deleting.id),
                        {
                            preserveScroll: true,
                            onStart: () => setDeletingBusy(true),
                            onFinish: () => setDeletingBusy(false),
                            onSuccess: () => setDeleting(null),
                        },
                    )
                }
            />
        </StaffLayout>
    );
}

TeachingModules.layout = (page: ReactNode) => page;
