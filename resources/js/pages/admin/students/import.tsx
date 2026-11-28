import { Head, Link, router, useForm } from '@inertiajs/react';
import { Download, FileSpreadsheet, Upload } from 'lucide-react';
import type { ChangeEvent, FormEvent } from 'react';
import { useMemo, useRef, useState } from 'react';
import GuardianController from '@/actions/App/Http/Controllers/Admin/GuardianController';
import StudentController from '@/actions/App/Http/Controllers/Admin/StudentController';
import StudentImportController from '@/actions/App/Http/Controllers/Admin/StudentImportController';
import { ConfirmDialog } from '@/components/app/confirm-dialog';
import type { DataTableColumn } from '@/components/app/data-table';
import { DataTable } from '@/components/app/data-table';
import { EmptyState } from '@/components/app/empty-state';
import { ErrorState } from '@/components/app/error-state';
import { PageHeader } from '@/components/app/page-header';
import type { StatusBadgeVariant } from '@/components/app/status-badge';
import { StatusBadge } from '@/components/app/status-badge';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import StaffLayout from '@/layouts/staff-layout';
import { cn } from '@/lib/utils';

type Step = 'upload' | 'preview' | 'result';
type RowStatus = 'new' | 'update' | 'unchanged' | 'error';
type GuardianAction =
    | 'none'
    | 'new_account'
    | 'link_existing'
    | 'staff_account'
    | 'already_linked';

type PreviewRow = {
    row: number;
    status: RowStatus;
    nis: string;
    name: string;
    unit: string;
    classroom: string;
    guardian_name: string;
    guardian_phone: string;
    guardian_action: GuardianAction;
    changes: string[];
    errors: string[];
};

type Preview = {
    file_name: string;
    summary: Record<RowStatus | 'total', number>;
    rows: PreviewRow[];
};

type ImportResult = {
    created: number;
    updated: number;
    unchanged: number;
    failed: { row: number; error: string }[];
    new_guardians: { name: string; phone: string }[];
};

type Props = {
    step: Step;
    maxRows: number;
    maxKilobytes: number;
    columns: string[];
    preview?: Preview;
    result?: ImportResult;
};

const PER_PAGE = 50;

const steps: { key: Step; label: string }[] = [
    { key: 'upload', label: 'Unggah' },
    { key: 'preview', label: 'Pratinjau' },
    { key: 'result', label: 'Hasil' },
];

const statusBadge: Record<
    RowStatus,
    { label: string; variant: StatusBadgeVariant }
> = {
    new: { label: 'Baru', variant: 'success' },
    update: { label: 'Diubah', variant: 'default' },
    unchanged: { label: 'Tanpa perubahan', variant: 'muted' },
    error: { label: 'Galat', variant: 'destructive' },
};

const guardianNote: Record<GuardianAction, string | null> = {
    none: null,
    new_account: 'akun baru',
    link_existing: 'tautkan ke akun nomor ini',
    staff_account: 'akun staf juga jadi wali',
    already_linked: 'sudah tertaut',
};

const number = (value: number): string => value.toLocaleString('id-ID');

function Stepper({ current }: { current: Step }) {
    const currentIndex = steps.findIndex((step) => step.key === current);

    return (
        <ol
            aria-label="Langkah impor"
            className="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm"
        >
            {steps.map((step, index) => {
                const active = index === currentIndex;
                const done = index < currentIndex;

                return (
                    <li
                        key={step.key}
                        aria-current={active ? 'step' : undefined}
                        className={cn(
                            'flex items-center gap-2',
                            active
                                ? 'font-semibold text-foreground'
                                : 'text-muted-foreground',
                        )}
                    >
                        <span
                            className={cn(
                                'flex size-6 items-center justify-center rounded-md border text-xs tabular-nums',
                                active
                                    ? 'border-primary bg-primary text-primary-foreground'
                                    : done
                                      ? 'border-primary/40 text-primary'
                                      : 'border-border',
                            )}
                        >
                            {index + 1}
                        </span>
                        {step.label}
                    </li>
                );
            })}
        </ol>
    );
}

function UploadStep({
    maxRows,
    maxKilobytes,
    columns,
}: Pick<Props, 'maxRows' | 'maxKilobytes' | 'columns'>) {
    const form = useForm<{ file: File | null }>({ file: null });
    const fileInput = useRef<HTMLInputElement>(null);
    const [failed, setFailed] = useState(false);

    const choose = (event: ChangeEvent<HTMLInputElement>): void => {
        form.setData('file', event.target.files?.[0] ?? null);
        form.clearErrors('file');
    };

    const submit = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();
        setFailed(false);
        form.post(StudentImportController.store.url(), {
            forceFormData: true,
            onNetworkError: () => {
                setFailed(true);

                return false;
            },
            onHttpException: () => {
                setFailed(true);

                return false;
            },
        });
    };

    return (
        <div className="grid items-start gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,20rem)]">
            <form
                onSubmit={submit}
                className="space-y-4 rounded-lg border border-border bg-card p-4 sm:p-6"
            >
                <div className="space-y-1">
                    <h2 className="text-base font-semibold">
                        Unggah berkas siswa
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        Format .xlsx atau .csv, paling banyak {number(maxRows)}{' '}
                        baris dan {maxKilobytes / 1024} MB. Belum ada yang
                        disimpan sampai Anda menyetujui pratinjau.
                    </p>
                </div>

                <div className="flex flex-col gap-3 rounded-md border border-dashed border-border p-4 sm:flex-row sm:items-center">
                    <FileSpreadsheet
                        className="size-8 shrink-0 text-muted-foreground"
                        strokeWidth={1.5}
                        aria-hidden
                    />
                    <div className="min-w-0 flex-1 text-sm">
                        {form.data.file ? (
                            <>
                                <div className="truncate font-medium">
                                    {form.data.file.name}
                                </div>
                                <div className="text-muted-foreground tabular-nums">
                                    {number(
                                        Math.ceil(form.data.file.size / 1024),
                                    )}{' '}
                                    KB
                                </div>
                            </>
                        ) : (
                            <span className="text-muted-foreground">
                                Belum ada berkas dipilih.
                            </span>
                        )}
                    </div>
                    <input
                        ref={fileInput}
                        id="import-file"
                        type="file"
                        accept=".xlsx,.csv"
                        tabIndex={-1}
                        aria-hidden
                        className="sr-only"
                        onChange={choose}
                    />
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => fileInput.current?.click()}
                    >
                        Pilih berkas
                    </Button>
                </div>
                <InputError message={form.errors.file} />
                {failed && (
                    <p role="alert" className="text-sm text-destructive">
                        Berkas tidak terkirim. Periksa koneksi lalu coba lagi.
                    </p>
                )}

                <div className="flex flex-wrap justify-end gap-2">
                    <Button variant="outline" asChild>
                        <Link href={StudentController.index()}>Kembali</Link>
                    </Button>
                    <Button
                        type="submit"
                        disabled={!form.data.file || form.processing}
                    >
                        {form.processing && <Spinner />}
                        {form.processing ? 'Memeriksa…' : 'Periksa berkas'}
                    </Button>
                </div>
            </form>

            <aside className="space-y-3 rounded-lg border border-border bg-card p-4 text-sm sm:p-6">
                <h2 className="text-base font-semibold">Template</h2>
                <p className="text-muted-foreground">
                    NIS menjadi kunci: NIS baru ditambahkan, NIS terdaftar
                    diperbarui. Sel kosong tidak menghapus data lama. Unit dan
                    rombel harus sudah ada.
                </p>
                <p className="text-muted-foreground">
                    Kolom: {columns.join(', ')}. Penjelasan tiap kolom ada di
                    lembar "Petunjuk".
                </p>
                <Button variant="outline" asChild className="w-full">
                    <a href={StudentImportController.template.url()} download>
                        <Download className="size-4" strokeWidth={1.75} />
                        Unduh template
                    </a>
                </Button>
            </aside>
        </div>
    );
}

function PreviewStep({ preview }: { preview: Preview }) {
    const [onlyErrors, setOnlyErrors] = useState(false);
    const [page, setPage] = useState(1);
    const [confirming, setConfirming] = useState(false);
    const [saving, setSaving] = useState(false);
    const [cancelling, setCancelling] = useState(false);
    const [failed, setFailed] = useState(false);

    const { summary } = preview;
    const writable = summary.new + summary.update;

    const rows = useMemo(
        () =>
            onlyErrors
                ? preview.rows.filter((row) => row.status === 'error')
                : preview.rows,
        [onlyErrors, preview.rows],
    );
    const lastPage = Math.max(1, Math.ceil(rows.length / PER_PAGE));
    const currentPage = Math.min(page, lastPage);
    const pageRows = rows.slice(
        (currentPage - 1) * PER_PAGE,
        currentPage * PER_PAGE,
    );

    const failHandlers = {
        onNetworkError: () => {
            setFailed(true);

            return false;
        },
        onHttpException: () => {
            setFailed(true);

            return false;
        },
    };

    const commit = (): void => {
        router.post(
            StudentImportController.commit.url(),
            {},
            {
                onStart: () => {
                    setSaving(true);
                    setFailed(false);
                },
                onFinish: () => {
                    setSaving(false);
                    setConfirming(false);
                },
                ...failHandlers,
            },
        );
    };

    const cancel = (): void => {
        router.delete(StudentImportController.destroy.url(), {
            onStart: () => {
                setCancelling(true);
                setFailed(false);
            },
            onFinish: () => setCancelling(false),
            ...failHandlers,
        });
    };

    const filter = (errorsOnly: boolean): void => {
        setOnlyErrors(errorsOnly);
        setPage(1);
    };

    const columns: DataTableColumn<PreviewRow>[] = [
        {
            key: 'row',
            label: 'Baris',
            align: 'right',
            render: (row) => row.row,
        },
        {
            key: 'student',
            label: 'Siswa',
            render: (row) => (
                <div className="min-w-40">
                    <div className="font-medium text-foreground">
                        {row.name || (
                            <span className="text-muted-foreground">
                                Tanpa nama
                            </span>
                        )}
                    </div>
                    <div className="text-xs text-muted-foreground tabular-nums">
                        NIS {row.nis || '—'}
                    </div>
                </div>
            ),
        },
        {
            key: 'status',
            label: 'Status',
            align: 'center',
            render: (row) => (
                <StatusBadge variant={statusBadge[row.status].variant}>
                    {statusBadge[row.status].label}
                </StatusBadge>
            ),
        },
        {
            key: 'note',
            label: 'Keterangan',
            render: (row) => {
                if (row.errors.length > 0) {
                    return (
                        <ul className="min-w-56 space-y-0.5 text-destructive">
                            {row.errors.map((error) => (
                                <li key={error}>{error}</li>
                            ))}
                        </ul>
                    );
                }

                if (row.changes.length > 0) {
                    return (
                        <span className="text-muted-foreground">
                            Berubah: {row.changes.join(', ')}
                        </span>
                    );
                }

                return null;
            },
        },
        {
            key: 'classroom',
            label: 'Unit · Rombel',
            render: (row) => (
                <span className="whitespace-nowrap">
                    {row.unit || '—'}
                    {row.classroom ? ` · ${row.classroom}` : ''}
                </span>
            ),
        },
        {
            key: 'guardian',
            label: 'Wali',
            render: (row) =>
                row.guardian_name || row.guardian_phone ? (
                    <div className="min-w-36">
                        <div>{row.guardian_name || '—'}</div>
                        <div className="text-xs text-muted-foreground tabular-nums">
                            {row.guardian_phone || '—'}
                            {guardianNote[row.guardian_action] &&
                                ` · ${guardianNote[row.guardian_action]}`}
                        </div>
                    </div>
                ) : (
                    <span className="text-muted-foreground">Tanpa wali</span>
                ),
        },
    ];

    return (
        <div className="space-y-4">
            <div className="flex flex-col gap-3 rounded-lg border border-border bg-card p-4 sm:flex-row sm:items-center sm:justify-between">
                <div className="min-w-0 space-y-1 text-sm">
                    <div className="truncate font-medium">
                        {preview.file_name}
                    </div>
                    <p className="text-muted-foreground tabular-nums">
                        {number(summary.total)} baris: {number(summary.new)}{' '}
                        baru, {number(summary.update)} diubah,{' '}
                        {number(summary.unchanged)} tanpa perubahan,{' '}
                        <span
                            className={cn(
                                summary.error > 0 &&
                                    'font-medium text-destructive',
                            )}
                        >
                            {number(summary.error)} galat
                        </span>
                        .{' '}
                        {summary.error > 0 &&
                            'Baris galat dilewati saat disimpan.'}
                    </p>
                </div>
                <div className="flex flex-wrap gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        onClick={cancel}
                        disabled={cancelling || saving}
                    >
                        {cancelling && <Spinner />}
                        Batal
                    </Button>
                    <Button
                        type="button"
                        onClick={() => setConfirming(true)}
                        disabled={writable === 0 || saving || cancelling}
                    >
                        Simpan {number(writable)} baris
                    </Button>
                </div>
            </div>

            {failed && (
                <ErrorState
                    title="Permintaan gagal"
                    description="Server tidak bisa dihubungi atau sedang bermasalah. Berkas masih tersimpan; coba lagi."
                    onRetry={() => setFailed(false)}
                    retryLabel="Tutup"
                />
            )}

            {writable === 0 && summary.error === 0 && (
                <p className="text-sm text-muted-foreground">
                    Semua baris sama dengan data yang sudah ada. Tidak ada yang
                    perlu disimpan.
                </p>
            )}

            <div
                role="group"
                aria-label="Saring baris"
                className="flex flex-wrap gap-2"
            >
                <Button
                    type="button"
                    size="sm"
                    variant={onlyErrors ? 'outline' : 'secondary'}
                    aria-pressed={!onlyErrors}
                    onClick={() => filter(false)}
                >
                    Semua baris ({number(summary.total)})
                </Button>
                <Button
                    type="button"
                    size="sm"
                    variant={onlyErrors ? 'secondary' : 'outline'}
                    aria-pressed={onlyErrors}
                    onClick={() => filter(true)}
                >
                    Hanya galat ({number(summary.error)})
                </Button>
            </div>

            <DataTable
                columns={columns}
                data={pageRows}
                rowKey={(row) => row.row}
                loading={saving}
                pagination={{
                    currentPage,
                    lastPage,
                    perPage: PER_PAGE,
                    total: rows.length,
                    from:
                        rows.length === 0
                            ? null
                            : (currentPage - 1) * PER_PAGE + 1,
                    to: Math.min(currentPage * PER_PAGE, rows.length),
                }}
                onPageChange={setPage}
                empty={{
                    title: 'Tidak ada baris galat',
                    description:
                        'Semua baris bisa disimpan. Tampilkan semua baris untuk memeriksa ulang.',
                }}
            />

            <ConfirmDialog
                open={confirming}
                onOpenChange={setConfirming}
                title="Simpan hasil impor?"
                description={`${number(summary.new)} siswa baru ditambahkan dan ${number(summary.update)} siswa diperbarui.${summary.error > 0 ? ` ${number(summary.error)} baris galat dilewati.` : ''}`}
                confirmLabel="Simpan"
                variant="default"
                loading={saving}
                onConfirm={commit}
            />
        </div>
    );
}

function ResultStep({ result }: { result: ImportResult }) {
    const failedColumns: DataTableColumn<{ row: number; error: string }>[] = [
        {
            key: 'row',
            label: 'Baris',
            align: 'right',
            className: 'w-20',
            render: (item) => item.row,
        },
        {
            key: 'error',
            label: 'Alasan',
            render: (item) => item.error,
        },
    ];

    return (
        <div className="space-y-4">
            <div className="space-y-3 rounded-lg border border-border bg-card p-4 sm:p-6">
                <h2 className="text-base font-semibold">Impor selesai</h2>
                <dl className="grid grid-cols-2 gap-3 text-sm sm:grid-cols-4">
                    {[
                        ['Ditambahkan', result.created],
                        ['Diperbarui', result.updated],
                        ['Tanpa perubahan', result.unchanged],
                        ['Gagal', result.failed.length],
                    ].map(([label, value]) => (
                        <div key={label}>
                            <dt className="text-muted-foreground">{label}</dt>
                            <dd className="text-lg font-semibold tabular-nums">
                                {number(Number(value))}
                            </dd>
                        </div>
                    ))}
                </dl>
                <div className="flex flex-wrap gap-2 pt-1">
                    <Button asChild>
                        <Link href={StudentController.index()}>
                            Lihat daftar siswa
                        </Link>
                    </Button>
                    <Button variant="outline" asChild>
                        <Link href={StudentImportController.create()}>
                            <Upload className="size-4" strokeWidth={1.75} />
                            Impor berkas lain
                        </Link>
                    </Button>
                </div>
            </div>

            {result.new_guardians.length > 0 && (
                <section className="space-y-2 rounded-lg border border-border bg-card p-4 sm:p-6">
                    <h2 className="text-base font-semibold">
                        Akun wali baru ({number(result.new_guardians.length)})
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        Kata sandi awal tidak ditampilkan di sini. Untuk
                        mencetak surat akun, buka halaman Wali lalu pilih "Reset
                        kata sandi" pada akun berikut.
                    </p>
                    <ul className="divide-y divide-border text-sm">
                        {result.new_guardians.map((guardian) => (
                            <li
                                key={guardian.phone}
                                className="flex flex-wrap justify-between gap-2 py-2"
                            >
                                <span>{guardian.name}</span>
                                <span className="text-muted-foreground tabular-nums">
                                    {guardian.phone}
                                </span>
                            </li>
                        ))}
                    </ul>
                    <Button variant="outline" asChild>
                        <Link href={GuardianController.index()}>
                            Buka halaman Wali
                        </Link>
                    </Button>
                </section>
            )}

            <section className="space-y-2">
                <h2 className="text-base font-semibold">Baris gagal</h2>
                {result.failed.length === 0 ? (
                    <EmptyState
                        title="Tidak ada baris gagal"
                        description="Semua baris di berkas tersimpan atau sudah sama dengan data yang ada."
                    />
                ) : (
                    <>
                        <p className="text-sm text-muted-foreground">
                            Perbaiki baris berikut di berkas, lalu impor ulang.
                            Baris yang sudah masuk akan tercatat tanpa
                            perubahan.
                        </p>
                        <DataTable
                            columns={failedColumns}
                            data={result.failed}
                            rowKey={(item) => item.row}
                        />
                    </>
                )}
            </section>
        </div>
    );
}

export default function StudentImport({
    step,
    maxRows,
    maxKilobytes,
    columns,
    preview,
    result,
}: Props) {
    return (
        <StaffLayout
            breadcrumbs={[
                { title: 'Pengguna', href: StudentController.index() },
                { title: 'Siswa', href: StudentController.index() },
                {
                    title: 'Impor Excel',
                    href: StudentImportController.create(),
                },
            ]}
        >
            <Head title="Impor siswa" />

            <div className="space-y-4">
                <PageHeader
                    title="Impor siswa dan wali"
                    description="Tambah atau perbarui siswa beserta walinya dari berkas Excel."
                />

                <Stepper current={step} />

                {step === 'upload' && (
                    <UploadStep
                        maxRows={maxRows}
                        maxKilobytes={maxKilobytes}
                        columns={columns}
                    />
                )}
                {step === 'preview' && preview && (
                    <PreviewStep preview={preview} />
                )}
                {step === 'result' && result && <ResultStep result={result} />}
            </div>
        </StaffLayout>
    );
}
