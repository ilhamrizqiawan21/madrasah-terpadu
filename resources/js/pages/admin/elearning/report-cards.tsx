import { Head, router } from '@inertiajs/react';
import {
    Download,
    FileText,
    Globe,
    Lock,
    Search,
    Trash2,
    Upload,
} from 'lucide-react';
import { type FormEvent, type ReactNode, useState } from 'react';
import ReportCardController from '@/actions/App/Http/Controllers/Admin/ReportCardController';
import { ConfirmDialog } from '@/components/app/confirm-dialog';
import { DateText } from '@/components/app/date-text';
import { EmptyState } from '@/components/app/empty-state';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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

type ReportCardInfo = {
    id: number;
    published_at: string | null;
    uploader_name: string;
    updated_at: string;
};

type StudentReportRow = {
    student_id: number;
    nis: string;
    student_name: string;
    classroom_name: string;
    can_manage: boolean;
    report_card: ReportCardInfo | null;
};

type PaginatedRows = {
    data: StudentReportRow[];
    current_page: number;
    last_page: number;
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
};

type Option = { id: number; name: string };

type Props = {
    rows: PaginatedRows;
    classrooms: Option[];
    academicYears: (Option & { is_active: boolean })[];
    filters: {
        academic_year_id: number;
        semester: 'odd' | 'even';
        classroom_id: number | null;
        search: string;
    };
};

export default function ReportCardsIndex({
    rows,
    classrooms,
    academicYears,
    filters,
}: Props) {
    const [search, setSearch] = useState(filters.search);
    const [uploadTarget, setUploadTarget] = useState<StudentReportRow | null>(
        null,
    );
    const [pdfFile, setPdfFile] = useState<File | null>(null);
    const [publishNow, setPublishNow] = useState(false);
    const [submitting, setSubmitting] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [deleteTarget, setDeleteTarget] = useState<StudentReportRow | null>(
        null,
    );

    const applyFilters = (next: Partial<Props['filters']>): void => {
        router.get(
            ReportCardController.index.url({
                query: {
                    academic_year_id:
                        next.academic_year_id ?? filters.academic_year_id,
                    semester: next.semester ?? filters.semester,
                    classroom_id:
                        next.classroom_id ?? filters.classroom_id ?? undefined,
                    search: (next.search ?? search) || undefined,
                },
            }),
            {},
            { preserveState: true, preserveScroll: true },
        );
    };

    const handleSearch = (e: FormEvent): void => {
        e.preventDefault();
        applyFilters({ search });
    };

    const openUpload = (row: StudentReportRow): void => {
        setUploadTarget(row);
        setPdfFile(null);
        setPublishNow(row.report_card?.published_at !== null);
        setErrors({});
    };

    const handleUpload = (e: FormEvent): void => {
        e.preventDefault();
        if (!uploadTarget || !pdfFile) {
            setErrors({ file: 'Pilih berkas PDF rapor terlebih dahulu.' });
            return;
        }

        setSubmitting(true);
        router.post(
            ReportCardController.store.url(),
            {
                student_id: uploadTarget.student_id,
                academic_year_id: filters.academic_year_id,
                semester: filters.semester,
                file: pdfFile,
                publish_now: publishNow ? 1 : 0,
            },
            {
                forceFormData: true,
                preserveScroll: true,
                onSuccess: () => {
                    setUploadTarget(null);
                    setPdfFile(null);
                    setErrors({});
                },
                onError: (errs) => setErrors(errs),
                onFinish: () => setSubmitting(false),
            },
        );
    };

    const handleTogglePublish = (cardId: number): void => {
        router.patch(
            ReportCardController.togglePublish.url(cardId),
            {},
            { preserveScroll: true },
        );
    };

    const handleDelete = (): void => {
        if (!deleteTarget?.report_card) {
            return;
        }
        router.delete(
            ReportCardController.destroy.url(deleteTarget.report_card.id),
            {
                preserveScroll: true,
                onSuccess: () => setDeleteTarget(null),
            },
        );
    };

    return (
        <StaffLayout
            breadcrumbs={[
                { title: 'E-Learning', href: ReportCardController.index.url() },
                { title: 'E-Rapor', href: ReportCardController.index.url() },
            ]}
        >
            <Head title="E-Rapor" />

            <div className="space-y-4">
                <div>
                    <h1 className="text-lg font-semibold tracking-tight">
                        E-Rapor
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Unggah rapor PDF per siswa per semester dan terbitkan
                        agar dapat diunduh wali.
                    </p>
                </div>

                <div className="flex flex-wrap items-end gap-3 rounded-lg border bg-card p-3">
                    <div className="w-44 space-y-1">
                        <Label className="text-xs">Tahun Ajaran</Label>
                        <Select
                            value={String(filters.academic_year_id)}
                            onValueChange={(v) =>
                                applyFilters({ academic_year_id: Number(v) })
                            }
                        >
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {academicYears.map((y) => (
                                    <SelectItem key={y.id} value={String(y.id)}>
                                        {y.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    <div className="w-36 space-y-1">
                        <Label className="text-xs">Semester</Label>
                        <Select
                            value={filters.semester}
                            onValueChange={(v) =>
                                applyFilters({ semester: v as 'odd' | 'even' })
                            }
                        >
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="odd">Ganjil</SelectItem>
                                <SelectItem value="even">Genap</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div className="w-48 space-y-1">
                        <Label className="text-xs">Rombel</Label>
                        <Select
                            value={
                                filters.classroom_id
                                    ? String(filters.classroom_id)
                                    : 'all'
                            }
                            onValueChange={(v) =>
                                applyFilters({
                                    classroom_id:
                                        v === 'all' ? null : Number(v),
                                })
                            }
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Pilih rombel" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">
                                    Semua rombel
                                </SelectItem>
                                {classrooms.map((c) => (
                                    <SelectItem key={c.id} value={String(c.id)}>
                                        {c.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    <form
                        onSubmit={handleSearch}
                        className="flex min-w-[200px] flex-1 items-center gap-2"
                    >
                        <div className="relative flex-1">
                            <Search className="pointer-events-none absolute top-2.5 left-2.5 h-4 w-4 text-muted-foreground" />
                            <Input
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Cari nama atau NIS siswa..."
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
                        icon={FileText}
                        title="Belum ada siswa pada rombel ini"
                        description="Pilih tahun ajaran dan rombel aktif untuk mengunggah berkas e-rapor siswa."
                    />
                ) : (
                    <div className="overflow-x-auto rounded-lg border bg-card">
                        <table className="w-full text-sm">
                            <thead className="border-b bg-muted/40 text-left text-xs font-medium text-muted-foreground">
                                <tr>
                                    <th className="px-4 py-3">Siswa</th>
                                    <th className="px-4 py-3">Rombel</th>
                                    <th className="px-4 py-3">
                                        Status E-Rapor
                                    </th>
                                    <th className="px-4 py-3">
                                        Terbit ke Wali
                                    </th>
                                    <th className="px-4 py-3 text-right">
                                        Aksi
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {rows.data.map((row) => {
                                    const card = row.report_card;
                                    return (
                                        <tr
                                            key={row.student_id}
                                            className="hover:bg-muted/30"
                                        >
                                            <td className="px-4 py-3">
                                                <div className="font-medium">
                                                    {row.student_name}
                                                </div>
                                                <div className="text-xs text-muted-foreground">
                                                    NIS: {row.nis}
                                                </div>
                                            </td>
                                            <td className="px-4 py-3">
                                                {row.classroom_name}
                                            </td>
                                            <td className="px-4 py-3">
                                                {card ? (
                                                    <div className="space-y-0.5 text-xs">
                                                        <Badge variant="secondary">
                                                            PDF Terunggah
                                                        </Badge>
                                                        <div className="text-muted-foreground">
                                                            Oleh{' '}
                                                            {card.uploader_name}{' '}
                                                            ·{' '}
                                                            <DateText
                                                                value={
                                                                    card.updated_at
                                                                }
                                                            />
                                                        </div>
                                                    </div>
                                                ) : (
                                                    <span className="text-xs text-muted-foreground">
                                                        Belum diunggah
                                                    </span>
                                                )}
                                            </td>
                                            <td className="px-4 py-3">
                                                {card?.published_at ? (
                                                    <Badge variant="default">
                                                        Terbit
                                                    </Badge>
                                                ) : card ? (
                                                    <Badge variant="outline">
                                                        Draf
                                                    </Badge>
                                                ) : (
                                                    <span className="text-xs text-muted-foreground">
                                                        —
                                                    </span>
                                                )}
                                            </td>
                                            <td className="px-4 py-3 text-right">
                                                <div className="flex justify-end gap-1.5">
                                                    {card && (
                                                        <Button
                                                            asChild
                                                            size="sm"
                                                            variant="outline"
                                                            title="Unduh PDF"
                                                        >
                                                            <a
                                                                href={ReportCardController.download.url(
                                                                    card.id,
                                                                )}
                                                            >
                                                                <Download className="h-3.5 w-3.5" />
                                                            </a>
                                                        </Button>
                                                    )}

                                                    {row.can_manage && (
                                                        <>
                                                            <Button
                                                                size="sm"
                                                                variant="outline"
                                                                onClick={() =>
                                                                    openUpload(
                                                                        row,
                                                                    )
                                                                }
                                                            >
                                                                <Upload className="mr-1.5 h-3.5 w-3.5" />
                                                                {card
                                                                    ? 'Ganti PDF'
                                                                    : 'Unggah PDF'}
                                                            </Button>

                                                            {card && (
                                                                <>
                                                                    <Button
                                                                        size="sm"
                                                                        variant={
                                                                            card.published_at
                                                                                ? 'secondary'
                                                                                : 'default'
                                                                        }
                                                                        onClick={() =>
                                                                            handleTogglePublish(
                                                                                card.id,
                                                                            )
                                                                        }
                                                                    >
                                                                        {card.published_at ? (
                                                                            <>
                                                                                <Lock className="mr-1.5 h-3.5 w-3.5" />
                                                                                Tarik
                                                                            </>
                                                                        ) : (
                                                                            <>
                                                                                <Globe className="mr-1.5 h-3.5 w-3.5" />
                                                                                Terbitkan
                                                                            </>
                                                                        )}
                                                                    </Button>
                                                                    <Button
                                                                        size="sm"
                                                                        variant="ghost"
                                                                        onClick={() =>
                                                                            setDeleteTarget(
                                                                                row,
                                                                            )
                                                                        }
                                                                        title="Hapus e-rapor"
                                                                    >
                                                                        <Trash2 className="h-3.5 w-3.5 text-destructive" />
                                                                    </Button>
                                                                </>
                                                            )}
                                                        </>
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>

            <Dialog
                open={uploadTarget !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setUploadTarget(null);
                    }
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Unggah PDF E-Rapor</DialogTitle>
                        <DialogDescription>
                            {uploadTarget?.student_name} · Semester{' '}
                            {filters.semester === 'odd' ? 'Ganjil' : 'Genap'}
                        </DialogDescription>
                    </DialogHeader>
                    <form onSubmit={handleUpload} className="space-y-4">
                        <div className="space-y-1.5">
                            <Label htmlFor="rapor-file">
                                Berkas PDF (maks. 10 MB)
                            </Label>
                            <Input
                                id="rapor-file"
                                type="file"
                                accept="application/pdf"
                                onChange={(e) =>
                                    setPdfFile(e.target.files?.[0] ?? null)
                                }
                            />
                            {errors.file && (
                                <p className="text-xs text-destructive">
                                    {errors.file}
                                </p>
                            )}
                        </div>

                        <div className="flex items-center gap-2">
                            <Checkbox
                                id="publish-now"
                                checked={publishNow}
                                onCheckedChange={(checked) =>
                                    setPublishNow(checked === true)
                                }
                            />
                            <Label
                                htmlFor="publish-now"
                                className="text-sm font-normal"
                            >
                                Terbitkan langsung agar dapat diunduh wali
                            </Label>
                        </div>

                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setUploadTarget(null)}
                            >
                                Batal
                            </Button>
                            <Button type="submit" disabled={submitting}>
                                {submitting ? 'Mengunggah...' : 'Simpan PDF'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <ConfirmDialog
                open={deleteTarget !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setDeleteTarget(null);
                    }
                }}
                title="Hapus berkas e-rapor?"
                description={`Berkas rapor ${deleteTarget?.student_name ?? ''} akan dihapus permanen.`}
                confirmLabel="Hapus"
                variant="destructive"
                onConfirm={handleDelete}
            />
        </StaffLayout>
    );
}

ReportCardsIndex.layout = (page: ReactNode) => page;
