import { Head, router } from '@inertiajs/react';
import { Download, Eye, Upload } from 'lucide-react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import OpeningBalanceController from '@/actions/App/Http/Controllers/Admin/OpeningBalanceController';
import { DateText } from '@/components/app/date-text';
import { EmptyState } from '@/components/app/empty-state';
import { ErrorState } from '@/components/app/error-state';
import { MoneyText } from '@/components/app/money-text';
import { PageHeader } from '@/components/app/page-header';
import { StatusBadge } from '@/components/app/status-badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import StaffLayout from '@/layouts/staff-layout';

type PreviewRow = {
    row: number;
    nis: string;
    student_name: string | null;
    description: string;
    amount: number;
    due_date: string;
    status: 'will_create' | 'already_exists' | 'error';
    error: string | null;
};

type PreviewResult = {
    rows: PreviewRow[];
    summary: {
        will_create: number;
        already_exists: number;
        error: number;
        total_amount: number;
    };
};

function getXsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : '';
}

export default function OpeningBalancesPage() {
    const [file, setFile] = useState<File | null>(null);
    const [preview, setPreview] = useState<PreviewResult | null>(null);
    const [loading, setLoading] = useState(false);
    const [submitting, setSubmitting] = useState(false);
    const [error, setError] = useState<string | null>(null);

    async function handlePreview() {
        if (!file) {
            return;
        }
        setLoading(true);
        setError(null);

        const body = new FormData();
        body.append('file', file);

        try {
            const res = await fetch(OpeningBalanceController.preview.url(), {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': getXsrfToken(),
                },
                body,
            });

            if (!res.ok) {
                const json = (await res.json().catch(() => null)) as {
                    message?: string;
                    errors?: Record<string, string[]>;
                } | null;
                setError(
                    json?.errors?.file?.[0] ??
                        json?.message ??
                        'Gagal membaca berkas Excel/CSV.',
                );
                setLoading(false);

                return;
            }

            setPreview((await res.json()) as PreviewResult);
        } catch {
            setError('Koneksi gagal saat mempratinjau berkas.');
        } finally {
            setLoading(false);
        }
    }

    function handleImport() {
        if (!file) {
            return;
        }
        setSubmitting(true);
        router.post(
            OpeningBalanceController.store.url(),
            { file },
            {
                forceFormData: true,
                onFinish: () => setSubmitting(false),
                onSuccess: () => {
                    setFile(null);
                    setPreview(null);
                },
            },
        );
    }

    return (
        <StaffLayout
            breadcrumbs={[
                {
                    title: 'Keuangan',
                    href: OpeningBalanceController.index.url(),
                },
                {
                    title: 'Saldo awal migrasi',
                    href: OpeningBalanceController.index.url(),
                },
            ]}
        >
            <Head title="Saldo awal migrasi" />

            <div className="space-y-6">
                <PageHeader
                    title="Saldo awal migrasi"
                    description="Impor tunggakan dari aplikasi lama (Excel/CSV) sebagai tagihan berjenis Saldo Awal."
                    actions={
                        <Button variant="outline" asChild>
                            <a href={OpeningBalanceController.template.url()}>
                                <Download
                                    className="size-4"
                                    strokeWidth={1.75}
                                />
                                Unduh template
                            </a>
                        </Button>
                    }
                />

                <div className="rounded-lg border border-border bg-card p-5 shadow-xs">
                    <div className="flex flex-col gap-4 sm:flex-row sm:items-end">
                        <div className="flex-1 space-y-1.5">
                            <Label htmlFor="opening-file">
                                Berkas Excel (.xlsx) atau CSV (maks 2 MB, 1.500
                                baris)
                            </Label>
                            <Input
                                id="opening-file"
                                type="file"
                                accept=".xlsx,.csv"
                                onChange={(e) => {
                                    setFile(e.target.files?.[0] ?? null);
                                    setPreview(null);
                                    setError(null);
                                }}
                            />
                        </div>
                        <Button
                            type="button"
                            onClick={handlePreview}
                            disabled={!file || loading}
                        >
                            <Eye className="size-4" strokeWidth={1.75} />
                            {loading ? 'Memeriksa...' : 'Pratinjau'}
                        </Button>
                    </div>
                    {error && (
                        <div className="mt-4">
                            <ErrorState
                                title="Berkas tidak dapat diproses"
                                description={error}
                                onRetry={handlePreview}
                            />
                        </div>
                    )}
                </div>

                {preview ? (
                    <div className="space-y-4">
                        <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                            <div className="rounded-lg border border-border bg-card p-4">
                                <div className="text-xs text-muted-foreground">
                                    Akan dibuat
                                </div>
                                <div className="mt-1 text-xl font-semibold tabular-nums">
                                    {preview.summary.will_create}
                                </div>
                            </div>
                            <div className="rounded-lg border border-border bg-card p-4">
                                <div className="text-xs text-muted-foreground">
                                    Sudah ada
                                </div>
                                <div className="mt-1 text-xl font-semibold tabular-nums">
                                    {preview.summary.already_exists}
                                </div>
                            </div>
                            <div className="rounded-lg border border-border bg-card p-4">
                                <div className="text-xs text-muted-foreground">
                                    Galat
                                </div>
                                <div className="mt-1 text-xl font-semibold text-destructive tabular-nums">
                                    {preview.summary.error}
                                </div>
                            </div>
                            <div className="rounded-lg border border-border bg-card p-4">
                                <div className="text-xs text-muted-foreground">
                                    Total nominal
                                </div>
                                <div className="mt-1 text-xl font-semibold">
                                    <MoneyText
                                        amount={preview.summary.total_amount}
                                    />
                                </div>
                            </div>
                        </div>

                        <div className="overflow-x-auto rounded-lg border border-border bg-card shadow-xs">
                            <table className="w-full text-sm">
                                <thead className="border-b border-border bg-muted/50 text-xs text-muted-foreground">
                                    <tr>
                                        <th className="px-3 py-2 text-left font-medium">
                                            Baris
                                        </th>
                                        <th className="px-3 py-2 text-left font-medium">
                                            NIS &amp; Siswa
                                        </th>
                                        <th className="px-3 py-2 text-left font-medium">
                                            Deskripsi
                                        </th>
                                        <th className="px-3 py-2 text-left font-medium">
                                            Jatuh tempo
                                        </th>
                                        <th className="px-3 py-2 text-right font-medium">
                                            Nominal
                                        </th>
                                        <th className="px-3 py-2 text-center font-medium">
                                            Status
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border">
                                    {preview.rows.map((r) => (
                                        <tr key={r.row}>
                                            <td className="px-3 py-2 tabular-nums">
                                                {r.row}
                                            </td>
                                            <td className="px-3 py-2">
                                                <div className="font-medium">
                                                    {r.student_name ?? '—'}
                                                </div>
                                                <div className="text-xs text-muted-foreground tabular-nums">
                                                    {r.nis}
                                                </div>
                                            </td>
                                            <td className="px-3 py-2">
                                                <div>{r.description}</div>
                                                {r.error && (
                                                    <div className="text-xs text-destructive">
                                                        {r.error}
                                                    </div>
                                                )}
                                            </td>
                                            <td className="px-3 py-2">
                                                <DateText value={r.due_date} />
                                            </td>
                                            <td className="px-3 py-2 text-right">
                                                <MoneyText amount={r.amount} />
                                            </td>
                                            <td className="px-3 py-2 text-center">
                                                <StatusBadge
                                                    variant={
                                                        r.status ===
                                                        'will_create'
                                                            ? 'success'
                                                            : r.status ===
                                                                'error'
                                                              ? 'destructive'
                                                              : 'muted'
                                                    }
                                                >
                                                    {r.status === 'will_create'
                                                        ? 'Akan dibuat'
                                                        : r.status === 'error'
                                                          ? 'Galat'
                                                          : 'Sudah ada'}
                                                </StatusBadge>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        <div className="flex justify-end">
                            <Button
                                type="button"
                                onClick={handleImport}
                                disabled={
                                    submitting ||
                                    preview.summary.will_create === 0
                                }
                            >
                                <Upload className="size-4" strokeWidth={1.75} />
                                {submitting
                                    ? 'Mengimpor...'
                                    : `Impor ${preview.summary.will_create} tagihan`}
                            </Button>
                        </div>
                    </div>
                ) : (
                    <EmptyState
                        title="Belum ada berkas dipratinjau"
                        description="Unduh template Excel, isi tunggakan dari aplikasi lama, lalu unggah untuk pratinjau."
                    />
                )}
            </div>
        </StaffLayout>
    );
}

OpeningBalancesPage.layout = (page: ReactNode) => page;
