import { Head, router } from '@inertiajs/react';
import { ArrowLeft, CheckCircle2, Eye, Send } from 'lucide-react';
import type { ReactNode } from 'react';
import { useMemo, useState } from 'react';
import BillingRunController from '@/actions/App/Http/Controllers/Admin/BillingRunController';
import { ConfirmDialog } from '@/components/app/confirm-dialog';
import { DateText } from '@/components/app/date-text';
import { EmptyState } from '@/components/app/empty-state';
import { ErrorState } from '@/components/app/error-state';
import { MoneyText } from '@/components/app/money-text';
import { PageHeader } from '@/components/app/page-header';
import { StatusBadge } from '@/components/app/status-badge';
import { DateInput } from '@/components/app/date-input';
import { Button } from '@/components/ui/button';
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

type FeeTypeOption = {
    id: number;
    unit_id: number | null;
    name: string;
    category: string;
    recurrence: 'monthly' | 'once';
    default_amount: number;
};

type UnitOption = { id: number; code: string; name: string };
type ClassroomOption = {
    id: number;
    unit_id: number;
    grade: string;
    name: string;
};
type StudentOption = {
    id: number;
    unit_id: number;
    nis: string;
    name: string;
};
type TargetTypeOption = { value: string; label: string };

type PreviewItem = {
    key: string;
    student_id: number;
    student_name: string;
    nis: string;
    classroom_name: string | null;
    period: string;
    period_label: string;
    description: string;
    due_date: string;
    amount: number;
    state: 'will_create' | 'already_exists' | 'skipped_inactive';
    state_label: string;
};

type PreviewResult = {
    items: PreviewItem[];
    summary: {
        will_create: number;
        already_exists: number;
        skipped_inactive: number;
        total_amount: number;
    };
};

type RecentRun = {
    id: number;
    fee_type_name: string;
    target_type_label: string;
    invoice_count: number;
    total_amount: number;
    creator_name: string;
    created_at: string;
};

type Props = {
    feeTypes: FeeTypeOption[];
    units: UnitOption[];
    activeYear: { id: number; label: string; starts_on: string } | null;
    classrooms: ClassroomOption[];
    students: StudentOption[];
    targetTypes: TargetTypeOption[];
    recentRuns: RecentRun[];
};

function currentMonthValue(): string {
    const now = new Date();
    const y = now.getFullYear();
    const m = String(now.getMonth() + 1).padStart(2, '0');

    return `${y}-${m}`;
}

function getXsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : '';
}

export default function BillingIssuePage({
    feeTypes,
    units,
    activeYear,
    classrooms,
    students,
    targetTypes,
    recentRuns,
}: Props) {
    const [step, setStep] = useState<'form' | 'preview'>('form');

    const [feeTypeId, setFeeTypeId] = useState<string>(
        feeTypes[0] ? String(feeTypes[0].id) : '',
    );
    const [targetType, setTargetType] = useState<string>('classroom');
    const [unitId, setUnitId] = useState<string>(
        units[0] ? String(units[0].id) : '',
    );
    const [grade, setGrade] = useState<string>('');
    const [classroomId, setClassroomId] = useState<string>(
        classrooms[0] ? String(classrooms[0].id) : '',
    );
    const [studentIds, setStudentIds] = useState<number[]>([]);
    const [studentQuery, setStudentQuery] = useState('');

    const [periodFrom, setPeriodFrom] = useState<string>(currentMonthValue());
    const [periodTo, setPeriodTo] = useState<string>(currentMonthValue());
    const [dueMode, setDueMode] = useState<'day' | 'date'>('day');
    const [dueDay, setDueDay] = useState<string>('10');
    const [dueDate, setDueDate] = useState<string>('');
    const [defaultAmount, setDefaultAmount] = useState<string>(
        feeTypes[0] ? String(feeTypes[0].default_amount) : '0',
    );

    const [overrides, setOverrides] = useState<Record<string, number>>({});
    const [preview, setPreview] = useState<PreviewResult | null>(null);
    const [previewLoading, setPreviewLoading] = useState(false);
    const [previewError, setPreviewError] = useState<string | null>(null);
    const [submitting, setSubmitting] = useState(false);
    const [confirmOpen, setConfirmOpen] = useState(false);

    const selectedFeeType = useMemo(
        () => feeTypes.find((ft) => String(ft.id) === feeTypeId) ?? null,
        [feeTypes, feeTypeId],
    );

    const filteredClassrooms = useMemo(
        () =>
            selectedFeeType?.unit_id
                ? classrooms.filter(
                      (c) => c.unit_id === selectedFeeType.unit_id,
                  )
                : classrooms,
        [classrooms, selectedFeeType],
    );

    const availableGrades = useMemo(() => {
        const source =
            unitId !== ''
                ? classrooms.filter((c) => String(c.unit_id) === unitId)
                : classrooms;

        return Array.from(new Set(source.map((c) => c.grade)));
    }, [classrooms, unitId]);

    const filteredStudents = useMemo(() => {
        const q = studentQuery.trim().toLowerCase();

        return students
            .filter((s) => {
                if (
                    selectedFeeType?.unit_id &&
                    s.unit_id !== selectedFeeType.unit_id
                ) {
                    return false;
                }
                if (q === '') {
                    return true;
                }

                return (
                    s.name.toLowerCase().includes(q) ||
                    s.nis.toLowerCase().includes(q)
                );
            })
            .slice(0, 40);
    }, [students, studentQuery, selectedFeeType]);

    function handleFeeTypeChange(nextId: string) {
        setFeeTypeId(nextId);
        const found = feeTypes.find((ft) => String(ft.id) === nextId);
        if (found) {
            setDefaultAmount(String(found.default_amount));
            if (found.unit_id) {
                setUnitId(String(found.unit_id));
            }
        }
    }

    function buildPayload() {
        return {
            fee_type_id: Number(feeTypeId),
            target_type: targetType,
            unit_id: unitId !== '' ? Number(unitId) : null,
            grade: grade || null,
            classroom_id: classroomId !== '' ? Number(classroomId) : null,
            student_ids: studentIds,
            period_from:
                selectedFeeType?.recurrence === 'monthly' ? periodFrom : null,
            period_to:
                selectedFeeType?.recurrence === 'monthly' ? periodTo : null,
            due_mode: dueMode,
            due_day: dueMode === 'day' ? Number(dueDay || 10) : null,
            due_date: dueMode === 'date' ? dueDate : null,
            default_amount: Number(defaultAmount || 0),
            overrides,
        };
    }

    async function loadPreview() {
        setPreviewLoading(true);
        setPreviewError(null);

        try {
            const response = await fetch(BillingRunController.preview.url(), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': getXsrfToken(),
                },
                body: JSON.stringify(buildPayload()),
            });

            if (!response.ok) {
                const body = (await response.json().catch(() => null)) as {
                    message?: string;
                    errors?: Record<string, string[]>;
                } | null;
                const firstErr = body?.errors
                    ? Object.values(body.errors)[0]?.[0]
                    : null;
                setPreviewError(
                    firstErr ??
                        body?.message ??
                        'Gagal menyiapkan pratinjau tagihan.',
                );
                setPreviewLoading(false);

                return;
            }

            const data = (await response.json()) as PreviewResult;
            setPreview(data);
            setStep('preview');
        } catch {
            setPreviewError('Koneksi gagal saat menyiapkan pratinjau.');
        } finally {
            setPreviewLoading(false);
        }
    }

    function handleExecute() {
        setConfirmOpen(false);
        setSubmitting(true);

        router.post(BillingRunController.store.url(), buildPayload(), {
            preserveScroll: true,
            onFinish: () => setSubmitting(false),
            onSuccess: () => {
                setStep('form');
                setPreview(null);
                setOverrides({});
            },
        });
    }

    const computedTotal = useMemo(() => {
        if (!preview) {
            return 0;
        }

        return preview.items.reduce((sum, item) => {
            if (item.state !== 'will_create') {
                return sum;
            }

            return sum + (overrides[item.key] ?? item.amount);
        }, 0);
    }, [preview, overrides]);

    return (
        <StaffLayout
            breadcrumbs={[
                {
                    title: 'Keuangan Siswa',
                    href: BillingRunController.create.url(),
                },
                {
                    title: 'Terbitkan tagihan',
                    href: BillingRunController.create.url(),
                },
            ]}
        >
            <Head title="Terbitkan tagihan" />

            <div className="space-y-6">
                <PageHeader
                    title="Terbitkan tagihan"
                    description="Buat tagihan siswa per unit, tingkat, rombel, atau siswa terpilih dengan pratinjau sebelum disimpan."
                />

                <div className="flex items-center gap-3 text-sm">
                    <span
                        className={
                            step === 'form'
                                ? 'font-semibold text-primary'
                                : 'text-muted-foreground'
                        }
                    >
                        1. Jenis &amp; sasaran
                    </span>
                    <span className="text-muted-foreground">/</span>
                    <span
                        className={
                            step === 'preview'
                                ? 'font-semibold text-primary'
                                : 'text-muted-foreground'
                        }
                    >
                        2. Pratinjau &amp; terbitkan
                    </span>
                </div>

                {feeTypes.length === 0 ? (
                    <EmptyState
                        title="Belum ada jenis tagihan aktif"
                        description="Tambahkan dan aktifkan minimal satu jenis tagihan di menu Jenis tagihan sebelum menerbitkan tagihan."
                    />
                ) : step === 'form' ? (
                    <div className="rounded-lg border border-border bg-card p-6 shadow-xs">
                        <div className="grid grid-cols-1 gap-5 md:grid-cols-2">
                            <div className="space-y-1.5">
                                <Label htmlFor="issue-fee-type">
                                    Jenis tagihan
                                </Label>
                                <Select
                                    value={feeTypeId}
                                    onValueChange={handleFeeTypeChange}
                                >
                                    <SelectTrigger id="issue-fee-type">
                                        <SelectValue placeholder="Pilih jenis tagihan" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {feeTypes.map((ft) => (
                                            <SelectItem
                                                key={ft.id}
                                                value={String(ft.id)}
                                            >
                                                {ft.name} (
                                                {ft.recurrence === 'monthly'
                                                    ? 'Bulanan'
                                                    : 'Sekali'}
                                                )
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="space-y-1.5">
                                <Label htmlFor="issue-amount">
                                    Nominal bawaan (Rp)
                                </Label>
                                <Input
                                    id="issue-amount"
                                    type="number"
                                    min={0}
                                    step={1000}
                                    value={defaultAmount}
                                    onChange={(e) =>
                                        setDefaultAmount(e.target.value)
                                    }
                                    className="text-right tabular-nums"
                                />
                            </div>

                            <div className="space-y-1.5">
                                <Label htmlFor="issue-target">Sasaran</Label>
                                <Select
                                    value={targetType}
                                    onValueChange={setTargetType}
                                >
                                    <SelectTrigger id="issue-target">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {targetTypes.map((tt) => (
                                            <SelectItem
                                                key={tt.value}
                                                value={tt.value}
                                            >
                                                {tt.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            {(targetType === 'unit' ||
                                targetType === 'grade') && (
                                <div className="space-y-1.5">
                                    <Label htmlFor="issue-unit">Unit</Label>
                                    <Select
                                        value={unitId}
                                        onValueChange={(val) => {
                                            setUnitId(val);
                                            setGrade('');
                                        }}
                                    >
                                        <SelectTrigger id="issue-unit">
                                            <SelectValue placeholder="Pilih unit" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {units.map((u) => (
                                                <SelectItem
                                                    key={u.id}
                                                    value={String(u.id)}
                                                >
                                                    {u.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                            )}

                            {targetType === 'grade' && (
                                <div className="space-y-1.5">
                                    <Label htmlFor="issue-grade">Tingkat</Label>
                                    <Select
                                        value={grade}
                                        onValueChange={setGrade}
                                    >
                                        <SelectTrigger id="issue-grade">
                                            <SelectValue placeholder="Pilih tingkat" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {availableGrades.map((g) => (
                                                <SelectItem key={g} value={g}>
                                                    Tingkat {g}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                            )}

                            {targetType === 'classroom' && (
                                <div className="space-y-1.5">
                                    <Label htmlFor="issue-classroom">
                                        Rombel ({activeYear?.label ?? 'Aktif'})
                                    </Label>
                                    <Select
                                        value={classroomId}
                                        onValueChange={setClassroomId}
                                    >
                                        <SelectTrigger id="issue-classroom">
                                            <SelectValue placeholder="Pilih rombel" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {filteredClassrooms.map((c) => (
                                                <SelectItem
                                                    key={c.id}
                                                    value={String(c.id)}
                                                >
                                                    {c.name} (Tingkat {c.grade})
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                            )}

                            {targetType === 'students' && (
                                <div className="space-y-2 md:col-span-2">
                                    <Label htmlFor="issue-student-search">
                                        Pilih siswa ({studentIds.length}{' '}
                                        dipilih)
                                    </Label>
                                    <Input
                                        id="issue-student-search"
                                        value={studentQuery}
                                        onChange={(e) =>
                                            setStudentQuery(e.target.value)
                                        }
                                        placeholder="Cari nama atau NIS siswa..."
                                    />
                                    <div className="max-h-52 divide-y divide-border overflow-y-auto rounded-md border border-border">
                                        {filteredStudents.map((s) => {
                                            const checked = studentIds.includes(
                                                s.id,
                                            );

                                            return (
                                                <label
                                                    key={s.id}
                                                    className="flex cursor-pointer items-center gap-3 px-3 py-2 text-sm hover:bg-muted/50"
                                                >
                                                    <Checkbox
                                                        checked={checked}
                                                        onCheckedChange={(
                                                            val,
                                                        ) => {
                                                            setStudentIds(
                                                                (prev) =>
                                                                    val === true
                                                                        ? [
                                                                              ...prev,
                                                                              s.id,
                                                                          ]
                                                                        : prev.filter(
                                                                              (
                                                                                  id,
                                                                              ) =>
                                                                                  id !==
                                                                                  s.id,
                                                                          ),
                                                            );
                                                        }}
                                                    />
                                                    <span className="font-medium">
                                                        {s.name}
                                                    </span>
                                                    <span className="text-xs text-muted-foreground tabular-nums">
                                                        {s.nis}
                                                    </span>
                                                </label>
                                            );
                                        })}
                                    </div>
                                </div>
                            )}

                            {selectedFeeType?.recurrence === 'monthly' ? (
                                <>
                                    <div className="space-y-1.5">
                                        <Label htmlFor="issue-period-from">
                                            Dari bulan
                                        </Label>
                                        <Input
                                            id="issue-period-from"
                                            type="month"
                                            value={periodFrom}
                                            onChange={(e) =>
                                                setPeriodFrom(e.target.value)
                                            }
                                        />
                                    </div>
                                    <div className="space-y-1.5">
                                        <Label htmlFor="issue-period-to">
                                            Sampai bulan
                                        </Label>
                                        <Input
                                            id="issue-period-to"
                                            type="month"
                                            value={periodTo}
                                            onChange={(e) =>
                                                setPeriodTo(e.target.value)
                                            }
                                        />
                                    </div>
                                </>
                            ) : (
                                <div className="rounded-md border border-border bg-muted/40 px-3 py-2.5 text-sm text-muted-foreground md:col-span-2">
                                    Tagihan sekali terbit memakai periode mulai
                                    tahun ajaran aktif:{' '}
                                    <strong className="text-foreground">
                                        {activeYear?.label ??
                                            'Belum ada tahun ajaran aktif'}
                                    </strong>
                                    .
                                </div>
                            )}

                            <div className="space-y-1.5">
                                <Label htmlFor="issue-due-mode">
                                    Mode jatuh tempo
                                </Label>
                                <Select
                                    value={dueMode}
                                    onValueChange={(v) =>
                                        setDueMode(v as 'day' | 'date')
                                    }
                                >
                                    <SelectTrigger id="issue-due-mode">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="day">
                                            Tanggal tetap tiap bulan
                                        </SelectItem>
                                        <SelectItem value="date">
                                            Tanggal tertentu
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            {dueMode === 'day' ? (
                                <div className="space-y-1.5">
                                    <Label htmlFor="issue-due-day">
                                        Tanggal jatuh tempo (1–31)
                                    </Label>
                                    <Input
                                        id="issue-due-day"
                                        type="number"
                                        min={1}
                                        max={31}
                                        value={dueDay}
                                        onChange={(e) =>
                                            setDueDay(e.target.value)
                                        }
                                        className="tabular-nums"
                                    />
                                </div>
                            ) : (
                                <div className="space-y-1.5">
                                    <Label htmlFor="issue-due-date">
                                        Tanggal jatuh tempo
                                    </Label>
                                    <DateInput
                                        id="issue-due-date"
                                        value={dueDate}
                                        onChange={(e) =>
                                            setDueDate(e.target.value)
                                        }
                                    />
                                </div>
                            )}
                        </div>

                        {previewError && (
                            <div className="mt-4">
                                <ErrorState
                                    title="Tidak dapat membuat pratinjau"
                                    description={previewError}
                                    onRetry={loadPreview}
                                />
                            </div>
                        )}

                        <div className="mt-6 flex justify-end">
                            <Button
                                type="button"
                                onClick={loadPreview}
                                disabled={previewLoading}
                            >
                                <Eye className="size-4" strokeWidth={1.75} />
                                {previewLoading
                                    ? 'Menyiapkan pratinjau...'
                                    : 'Lihat pratinjau'}
                            </Button>
                        </div>
                    </div>
                ) : (
                    preview && (
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
                                        Dilewati (nonaktif)
                                    </div>
                                    <div className="mt-1 text-xl font-semibold tabular-nums">
                                        {preview.summary.skipped_inactive}
                                    </div>
                                </div>
                                <div className="rounded-lg border border-border bg-card p-4">
                                    <div className="text-xs text-muted-foreground">
                                        Total nominal
                                    </div>
                                    <div className="mt-1 text-xl font-semibold">
                                        <MoneyText amount={computedTotal} />
                                    </div>
                                </div>
                            </div>

                            {preview.items.length === 0 ? (
                                <EmptyState
                                    title="Tidak ada siswa pada sasaran ini"
                                    description="Pilih unit, rombel, atau siswa lain lalu buat pratinjau kembali."
                                    action={
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={() => setStep('form')}
                                        >
                                            Ubah sasaran
                                        </Button>
                                    }
                                />
                            ) : (
                                <div className="overflow-x-auto rounded-lg border border-border bg-card shadow-xs">
                                    <table className="w-full text-sm">
                                        <thead className="border-b border-border bg-muted/50 text-xs text-muted-foreground">
                                            <tr>
                                                <th className="px-3 py-2.5 text-left font-medium">
                                                    Siswa
                                                </th>
                                                <th className="px-3 py-2.5 text-left font-medium">
                                                    Rombel
                                                </th>
                                                <th className="px-3 py-2.5 text-left font-medium">
                                                    Periode
                                                </th>
                                                <th className="px-3 py-2.5 text-left font-medium">
                                                    Jatuh tempo
                                                </th>
                                                <th className="px-3 py-2.5 text-center font-medium">
                                                    Status
                                                </th>
                                                <th className="px-3 py-2.5 text-right font-medium">
                                                    Nominal (Rp)
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-border">
                                            {preview.items.map((item) => (
                                                <tr key={item.key}>
                                                    <td className="px-3 py-2">
                                                        <div className="font-medium">
                                                            {item.student_name}
                                                        </div>
                                                        <div className="text-xs text-muted-foreground tabular-nums">
                                                            {item.nis}
                                                        </div>
                                                    </td>
                                                    <td className="px-3 py-2 text-muted-foreground">
                                                        {item.classroom_name ??
                                                            '—'}
                                                    </td>
                                                    <td className="px-3 py-2">
                                                        {item.period_label}
                                                    </td>
                                                    <td className="px-3 py-2">
                                                        <DateText
                                                            value={
                                                                item.due_date
                                                            }
                                                        />
                                                    </td>
                                                    <td className="px-3 py-2 text-center">
                                                        <StatusBadge
                                                            variant={
                                                                item.state ===
                                                                'will_create'
                                                                    ? 'success'
                                                                    : item.state ===
                                                                        'skipped_inactive'
                                                                      ? 'warning'
                                                                      : 'muted'
                                                            }
                                                        >
                                                            {item.state_label}
                                                        </StatusBadge>
                                                    </td>
                                                    <td className="px-3 py-2 text-right">
                                                        {item.state ===
                                                        'will_create' ? (
                                                            <Input
                                                                type="number"
                                                                min={0}
                                                                step={1000}
                                                                aria-label={`Nominal ${item.student_name} ${item.period_label}`}
                                                                value={
                                                                    overrides[
                                                                        item.key
                                                                    ] ??
                                                                    item.amount
                                                                }
                                                                onChange={(e) =>
                                                                    setOverrides(
                                                                        (
                                                                            prev,
                                                                        ) => ({
                                                                            ...prev,
                                                                            [item.key]:
                                                                                Math.max(
                                                                                    0,
                                                                                    Number(
                                                                                        e
                                                                                            .target
                                                                                            .value ||
                                                                                            0,
                                                                                    ),
                                                                                ),
                                                                        }),
                                                                    )
                                                                }
                                                                className="ml-auto w-36 text-right tabular-nums"
                                                            />
                                                        ) : (
                                                            <MoneyText
                                                                amount={
                                                                    item.amount
                                                                }
                                                            />
                                                        )}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}

                            <div className="flex flex-wrap items-center justify-between gap-3">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => setStep('form')}
                                    disabled={submitting}
                                >
                                    <ArrowLeft
                                        className="size-4"
                                        strokeWidth={1.75}
                                    />
                                    Kembali
                                </Button>

                                <Button
                                    type="button"
                                    onClick={() => setConfirmOpen(true)}
                                    disabled={
                                        submitting ||
                                        preview.summary.will_create === 0
                                    }
                                >
                                    <Send
                                        className="size-4"
                                        strokeWidth={1.75}
                                    />
                                    {submitting
                                        ? 'Menerbitkan...'
                                        : `Terbitkan ${preview.summary.will_create} tagihan`}
                                </Button>
                            </div>
                        </div>
                    )
                )}

                {recentRuns.length > 0 && (
                    <div className="space-y-3">
                        <h2 className="text-sm font-semibold text-foreground">
                            Riwayat penerbitan terbaru
                        </h2>
                        <div className="overflow-x-auto rounded-lg border border-border bg-card shadow-xs">
                            <table className="w-full text-sm">
                                <thead className="border-b border-border bg-muted/50 text-xs text-muted-foreground">
                                    <tr>
                                        <th className="px-3 py-2 text-left font-medium">
                                            Jenis tagihan
                                        </th>
                                        <th className="px-3 py-2 text-left font-medium">
                                            Sasaran
                                        </th>
                                        <th className="px-3 py-2 text-right font-medium">
                                            Jumlah
                                        </th>
                                        <th className="px-3 py-2 text-right font-medium">
                                            Total nominal
                                        </th>
                                        <th className="px-3 py-2 text-left font-medium">
                                            Petugas
                                        </th>
                                        <th className="px-3 py-2 text-left font-medium">
                                            Waktu
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border">
                                    {recentRuns.map((run) => (
                                        <tr key={run.id}>
                                            <td className="px-3 py-2 font-medium">
                                                <span className="inline-flex items-center gap-1.5">
                                                    <CheckCircle2
                                                        className="size-3.5 text-primary"
                                                        strokeWidth={1.75}
                                                    />
                                                    {run.fee_type_name}
                                                </span>
                                            </td>
                                            <td className="px-3 py-2 text-muted-foreground">
                                                {run.target_type_label}
                                            </td>
                                            <td className="px-3 py-2 text-right tabular-nums">
                                                {run.invoice_count}
                                            </td>
                                            <td className="px-3 py-2 text-right">
                                                <MoneyText
                                                    amount={run.total_amount}
                                                />
                                            </td>
                                            <td className="px-3 py-2 text-muted-foreground">
                                                {run.creator_name}
                                            </td>
                                            <td className="px-3 py-2 text-muted-foreground">
                                                <DateText
                                                    value={run.created_at}
                                                    mode="datetime"
                                                />
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}
            </div>

            <ConfirmDialog
                open={confirmOpen}
                onOpenChange={setConfirmOpen}
                title="Terbitkan tagihan sekarang?"
                description={
                    preview
                        ? `${preview.summary.will_create} tagihan baru akan diterbitkan dengan total nominal Rp ${computedTotal.toLocaleString('id-ID')}.`
                        : ''
                }
                confirmLabel="Ya, terbitkan"
                loading={submitting}
                onConfirm={handleExecute}
            />
        </StaffLayout>
    );
}

BillingIssuePage.layout = (page: ReactNode) => page;
