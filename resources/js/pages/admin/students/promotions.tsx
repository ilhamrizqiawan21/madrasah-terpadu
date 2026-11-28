import { Head, router, useForm } from '@inertiajs/react';
import { ArrowUpRight, GraduationCap } from 'lucide-react';
import { useMemo, useState } from 'react';
import StudentController from '@/actions/App/Http/Controllers/Admin/StudentController';
import StudentPromotionController from '@/actions/App/Http/Controllers/Admin/StudentPromotionController';
import { ConfirmDialog } from '@/components/app/confirm-dialog';
import type { DataTableColumn } from '@/components/app/data-table';
import { DataTable } from '@/components/app/data-table';
import { ErrorState } from '@/components/app/error-state';
import { PageHeader } from '@/components/app/page-header';
import { StatusBadge } from '@/components/app/status-badge';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import StaffLayout from '@/layouts/staff-layout';

type UnitOption = { id: number; code: string; name: string };
type YearOption = { id: number; label: string; is_active: boolean };
type ClassroomOption = { id: number; name: string };
type Candidate = {
    id: number;
    nis: string;
    name: string;
    already_enrolled: boolean;
};

type Props = {
    filters: {
        unit_id: number | null;
        source_year_id: number | null;
        source_classroom_id: number | null;
        target_year_id: number | null;
        target_classroom_id: number | null;
    };
    unitOptions: UnitOption[];
    yearOptions: YearOption[];
    sourceClassroomOptions: ClassroomOption[];
    targetClassroomOptions: ClassroomOption[];
    candidates: Candidate[];
};

type Mode = 'promote' | 'graduate';

export default function StudentPromotions({
    filters,
    unitOptions,
    yearOptions,
    sourceClassroomOptions,
    targetClassroomOptions,
    candidates,
}: Props) {
    const [mode, setMode] = useState<Mode>('promote');
    const [selectedIds, setSelectedIds] = useState<number[]>(() =>
        candidates.filter((c) => !c.already_enrolled).map((c) => c.id),
    );
    const [confirming, setConfirming] = useState(false);
    const [loading, setLoading] = useState(false);
    const [failed, setFailed] = useState(false);

    const form = useForm<{
        unit_id: number | null;
        source_year_id: number | null;
        source_classroom_id: number | null;
        target_year_id: number | null;
        target_classroom_id: number | null;
        mode: Mode;
        student_ids: number[];
    }>({
        unit_id: filters.unit_id,
        source_year_id: filters.source_year_id,
        source_classroom_id: filters.source_classroom_id,
        target_year_id: filters.target_year_id,
        target_classroom_id: filters.target_classroom_id,
        mode: 'promote',
        student_ids: [],
    });

    const candidateKey = candidates.map((c) => c.id).join(',');
    const [lastKey, setLastKey] = useState(candidateKey);
    if (candidateKey !== lastKey) {
        setLastKey(candidateKey);
        setSelectedIds(
            candidates.filter((c) => !c.already_enrolled).map((c) => c.id),
        );
    }

    const navigate = (next: Partial<Props['filters']>): void => {
        setLoading(true);
        setFailed(false);
        router.get(
            StudentPromotionController.index.url(),
            { ...filters, ...next },
            {
                preserveState: false,
                preserveScroll: true,
                onFinish: () => setLoading(false),
                onNetworkError: () => {
                    setFailed(true);
                    return false;
                },
                onHttpException: () => {
                    setFailed(true);
                    return false;
                },
            },
        );
    };

    const selectable = useMemo(
        () =>
            mode === 'graduate'
                ? candidates
                : candidates.filter((c) => !c.already_enrolled),
        [candidates, mode],
    );

    const allChecked =
        selectable.length > 0 &&
        selectable.every((c) => selectedIds.includes(c.id));

    const toggleAll = (checked: boolean): void => {
        setSelectedIds(checked ? selectable.map((c) => c.id) : []);
    };

    const toggleOne = (id: number, checked: boolean): void => {
        setSelectedIds((prev) =>
            checked
                ? [...new Set([...prev, id])]
                : prev.filter((x) => x !== id),
        );
    };

    const execute = (): void => {
        form.transform((data) => ({
            ...data,
            mode,
            target_classroom_id:
                mode === 'promote' ? data.target_classroom_id : null,
            student_ids: selectedIds,
        }));

        form.post(StudentPromotionController.store.url(), {
            onSuccess: () => setConfirming(false),
            onFinish: () => setConfirming(false),
        });
    };

    const columns: DataTableColumn<Candidate>[] = [
        {
            key: 'select',
            label: 'Pilih',
            align: 'center',
            className: 'w-14',
            render: (row) => (
                <Checkbox
                    aria-label={`Pilih ${row.name}`}
                    checked={selectedIds.includes(row.id)}
                    disabled={mode === 'promote' && row.already_enrolled}
                    onCheckedChange={(v) => toggleOne(row.id, v === true)}
                />
            ),
        },
        {
            key: 'nis',
            label: 'NIS',
            className: 'w-32 tabular-nums',
            render: (row) => row.nis,
        },
        {
            key: 'name',
            label: 'Nama siswa',
            render: (row) => <span className="font-medium">{row.name}</span>,
        },
        {
            key: 'status',
            label: 'Keterangan',
            align: 'center',
            render: (row) =>
                mode === 'promote' && row.already_enrolled ? (
                    <StatusBadge variant="muted">Sudah terdaftar</StatusBadge>
                ) : selectedIds.includes(row.id) ? (
                    <StatusBadge variant="success">
                        {mode === 'graduate' ? 'Diluluskan' : 'Naik kelas'}
                    </StatusBadge>
                ) : (
                    <StatusBadge variant="warning">Tinggal kelas</StatusBadge>
                ),
        },
    ];

    const canSubmit =
        selectedIds.length > 0 &&
        (mode === 'graduate' || filters.target_classroom_id !== null);

    return (
        <StaffLayout
            breadcrumbs={[
                { title: 'Akademik', href: StudentController.index() },
                { title: 'Siswa', href: StudentController.index() },
                {
                    title: 'Kenaikan kelas',
                    href: StudentPromotionController.index(),
                },
            ]}
        >
            <Head title="Kenaikan kelas" />

            <div className="space-y-4">
                <PageHeader
                    title="Kenaikan kelas & kelulusan"
                    description="Naikkan siswa aktif per rombel ke tahun ajaran berikutnya atau tandai lulus. Riwayat rombel sebelumnya tetap tersimpan."
                />

                {failed && (
                    <ErrorState
                        title="Gagal memuat data rombel"
                        description="Periksa koneksi lalu muat ulang halaman."
                        onRetry={() => navigate({})}
                    />
                )}

                <div className="grid items-start gap-4 lg:grid-cols-[minmax(0,22rem)_minmax(0,1fr)]">
                    <aside className="space-y-4 rounded-lg border border-border bg-card p-4">
                        <div className="space-y-1.5">
                            <Label htmlFor="promo-unit">Unit</Label>
                            <Select
                                value={
                                    filters.unit_id
                                        ? String(filters.unit_id)
                                        : ''
                                }
                                onValueChange={(v) =>
                                    navigate({
                                        unit_id: Number(v),
                                        source_classroom_id: null,
                                        target_classroom_id: null,
                                    })
                                }
                            >
                                <SelectTrigger id="promo-unit">
                                    <SelectValue placeholder="Pilih unit" />
                                </SelectTrigger>
                                <SelectContent>
                                    {unitOptions.map((u) => (
                                        <SelectItem
                                            key={u.id}
                                            value={String(u.id)}
                                        >
                                            {u.code} · {u.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="space-y-1.5">
                            <Label htmlFor="promo-source-year">
                                Tahun ajaran asal
                            </Label>
                            <Select
                                value={
                                    filters.source_year_id
                                        ? String(filters.source_year_id)
                                        : ''
                                }
                                onValueChange={(v) =>
                                    navigate({
                                        source_year_id: Number(v),
                                        source_classroom_id: null,
                                    })
                                }
                            >
                                <SelectTrigger id="promo-source-year">
                                    <SelectValue placeholder="Pilih tahun ajaran" />
                                </SelectTrigger>
                                <SelectContent>
                                    {yearOptions.map((y) => (
                                        <SelectItem
                                            key={y.id}
                                            value={String(y.id)}
                                        >
                                            {y.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="space-y-1.5">
                            <Label htmlFor="promo-source-room">
                                Rombel asal
                            </Label>
                            <Select
                                value={
                                    filters.source_classroom_id
                                        ? String(filters.source_classroom_id)
                                        : ''
                                }
                                onValueChange={(v) =>
                                    navigate({
                                        source_classroom_id: Number(v),
                                    })
                                }
                            >
                                <SelectTrigger id="promo-source-room">
                                    <SelectValue placeholder="Pilih rombel asal" />
                                </SelectTrigger>
                                <SelectContent>
                                    {sourceClassroomOptions.map((c) => (
                                        <SelectItem
                                            key={c.id}
                                            value={String(c.id)}
                                        >
                                            {c.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="space-y-1.5 border-t border-border pt-3">
                            <Label>Tujuan</Label>
                            <div className="grid grid-cols-2 gap-2">
                                <Button
                                    type="button"
                                    size="sm"
                                    variant={
                                        mode === 'promote'
                                            ? 'secondary'
                                            : 'outline'
                                    }
                                    onClick={() => setMode('promote')}
                                >
                                    <ArrowUpRight className="size-4" />
                                    Naik kelas
                                </Button>
                                <Button
                                    type="button"
                                    size="sm"
                                    variant={
                                        mode === 'graduate'
                                            ? 'secondary'
                                            : 'outline'
                                    }
                                    onClick={() => setMode('graduate')}
                                >
                                    <GraduationCap className="size-4" />
                                    Lulus
                                </Button>
                            </div>
                        </div>

                        {mode === 'promote' && (
                            <>
                                <div className="space-y-1.5">
                                    <Label htmlFor="promo-target-year">
                                        Tahun ajaran tujuan
                                    </Label>
                                    <Select
                                        value={
                                            filters.target_year_id
                                                ? String(filters.target_year_id)
                                                : ''
                                        }
                                        onValueChange={(v) =>
                                            navigate({
                                                target_year_id: Number(v),
                                                target_classroom_id: null,
                                            })
                                        }
                                    >
                                        <SelectTrigger id="promo-target-year">
                                            <SelectValue placeholder="Pilih tahun ajaran" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {yearOptions.map((y) => (
                                                <SelectItem
                                                    key={y.id}
                                                    value={String(y.id)}
                                                >
                                                    {y.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div className="space-y-1.5">
                                    <Label htmlFor="promo-target-room">
                                        Rombel tujuan
                                    </Label>
                                    <Select
                                        value={
                                            filters.target_classroom_id
                                                ? String(
                                                      filters.target_classroom_id,
                                                  )
                                                : ''
                                        }
                                        onValueChange={(v) =>
                                            navigate({
                                                target_classroom_id: Number(v),
                                            })
                                        }
                                    >
                                        <SelectTrigger id="promo-target-room">
                                            <SelectValue placeholder="Pilih rombel tujuan" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {targetClassroomOptions.map((c) => (
                                                <SelectItem
                                                    key={c.id}
                                                    value={String(c.id)}
                                                >
                                                    {c.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError
                                        message={
                                            form.errors.target_classroom_id
                                        }
                                    />
                                </div>
                            </>
                        )}

                        <div className="space-y-2 border-t border-border pt-3 text-sm">
                            <div className="flex justify-between">
                                <span className="text-muted-foreground">
                                    Siswa dipilih
                                </span>
                                <span className="font-semibold tabular-nums">
                                    {selectedIds.length} dari{' '}
                                    {candidates.length}
                                </span>
                            </div>
                            <InputError message={form.errors.student_ids} />
                            <Button
                                type="button"
                                className="w-full"
                                disabled={!canSubmit || form.processing}
                                onClick={() => setConfirming(true)}
                            >
                                {mode === 'graduate'
                                    ? `Luluskan ${selectedIds.length} siswa`
                                    : `Naikkan ${selectedIds.length} siswa`}
                            </Button>
                        </div>
                    </aside>

                    <div className="space-y-2">
                        <div className="flex flex-wrap items-center justify-between gap-2">
                            <p className="text-sm text-muted-foreground">
                                Hapus centang pada siswa yang tinggal kelas agar
                                tidak ikut dinaikkan.
                            </p>
                            {selectable.length > 0 && (
                                <label className="flex cursor-pointer items-center gap-2 text-sm">
                                    <Checkbox
                                        aria-label="Pilih semua siswa"
                                        checked={allChecked}
                                        onCheckedChange={(v) =>
                                            toggleAll(v === true)
                                        }
                                    />
                                    <span>Pilih semua</span>
                                </label>
                            )}
                        </div>
                        <DataTable
                            columns={columns}
                            data={candidates}
                            rowKey={(row) => row.id}
                            loading={loading}
                            empty={{
                                title: 'Tidak ada siswa aktif di rombel asal',
                                description:
                                    'Pilih unit, tahun ajaran, dan rombel asal yang memiliki siswa aktif.',
                            }}
                        />
                    </div>
                </div>
            </div>

            <ConfirmDialog
                open={confirming}
                onOpenChange={setConfirming}
                title={
                    mode === 'graduate'
                        ? 'Luluskan siswa terpilih?'
                        : 'Naikkan siswa terpilih?'
                }
                description={
                    mode === 'graduate'
                        ? `${selectedIds.length} siswa akan ditandai lulus. Wali yang tidak lagi memiliki anak aktif akan dinonaktifkan.`
                        : `${selectedIds.length} siswa akan didaftarkan ke rombel tujuan pada tahun ajaran yang dipilih.`
                }
                confirmLabel={mode === 'graduate' ? 'Luluskan' : 'Naikkan'}
                variant="default"
                loading={form.processing}
                onConfirm={execute}
            />
        </StaffLayout>
    );
}
