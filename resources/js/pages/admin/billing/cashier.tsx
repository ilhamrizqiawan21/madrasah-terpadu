import { Head, router, useForm } from '@inertiajs/react';
import { Banknote, CheckCircle2, FileDown, RotateCcw } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { useMemo, useState } from 'react';
import CashierController from '@/actions/App/Http/Controllers/Admin/CashierController';
import ReceiptController from '@/actions/App/Http/Controllers/Billing/ReceiptController';
import { DateText } from '@/components/app/date-text';
import { EmptyState } from '@/components/app/empty-state';
import { FormDialog } from '@/components/app/form-dialog';
import { MoneyText } from '@/components/app/money-text';
import { PageHeader } from '@/components/app/page-header';
import { StatusBadge } from '@/components/app/status-badge';
import InputError from '@/components/input-error';
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
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import StaffLayout from '@/layouts/staff-layout';

type StudentOption = {
    id: number;
    nis: string;
    name: string;
    unit_code: string;
};

type OpenInvoice = {
    id: number;
    fee_type_name: string;
    description: string;
    due_date: string;
    is_overdue: boolean;
    amount: number;
    adjustment_total: number;
    paid_total: number;
    remaining: number;
};

type RecentPayment = {
    id: number;
    receipt_number: string;
    student_name: string;
    student_nis: string;
    method: string;
    method_label: string;
    amount: number;
    cashier_name: string;
    paid_at: string;
    is_reversed: boolean;
};

type Props = {
    students: StudentOption[];
    selectedStudent: {
        id: number;
        nis: string;
        name: string;
        unit_name: string;
    } | null;
    openInvoices: OpenInvoice[];
    recentPayments: RecentPayment[];
    methods: { value: string; label: string }[];
    canReverse: boolean;
};

export default function CashierIndex({
    students,
    selectedStudent,
    openInvoices,
    recentPayments,
    methods,
    canReverse,
}: Props) {
    const [studentSearch, setStudentSearch] = useState('');
    const [selectedMap, setSelectedMap] = useState<Record<number, number>>({});
    const [reversing, setReversing] = useState<RecentPayment | null>(null);

    const reverseForm = useForm({
        reason: '',
    });

    function submitReverse(e: FormEvent<HTMLFormElement>) {
        e.preventDefault();
        if (!reversing) {
            return;
        }
        reverseForm.post(CashierController.reverse.url(reversing.id), {
            preserveScroll: true,
            onSuccess: () => {
                setReversing(null);
                reverseForm.reset();
            },
        });
    }

    const form = useForm<{
        student_id: number | null;
        method: string;
        reference: string;
        allocations: { invoice_id: number; amount: number }[];
    }>({
        student_id: selectedStudent?.id ?? null,
        method: methods[0]?.value ?? 'cash',
        reference: '',
        allocations: [],
    });

    const filteredStudents = useMemo(() => {
        const q = studentSearch.trim().toLowerCase();

        return students
            .filter(
                (s) =>
                    q === '' ||
                    s.name.toLowerCase().includes(q) ||
                    s.nis.toLowerCase().includes(q),
            )
            .slice(0, 30);
    }, [students, studentSearch]);

    function selectStudent(studentId: number) {
        setSelectedMap({});
        router.get(
            CashierController.index.url({
                query: { student_id: studentId },
            }),
            {},
            { preserveScroll: true },
        );
    }

    function toggleInvoice(inv: OpenInvoice, checked: boolean) {
        setSelectedMap((prev) => {
            const next = { ...prev };
            if (checked) {
                next[inv.id] = inv.remaining;
            } else {
                delete next[inv.id];
            }

            return next;
        });
    }

    const totalSelected = useMemo(
        () =>
            Object.values(selectedMap).reduce(
                (sum, val) => sum + (Number.isFinite(val) ? val : 0),
                0,
            ),
        [selectedMap],
    );

    function submitPayment(e: FormEvent<HTMLFormElement>) {
        e.preventDefault();
        if (!selectedStudent) {
            return;
        }

        const allocations = Object.entries(selectedMap)
            .filter(([, amount]) => amount > 0)
            .map(([invoiceId, amount]) => ({
                invoice_id: Number(invoiceId),
                amount: Number(amount),
            }));

        form.transform(() => ({
            student_id: selectedStudent.id,
            method: form.data.method,
            reference: form.data.reference,
            allocations,
        }));

        form.post(CashierController.store.url(), {
            preserveScroll: true,
            onSuccess: () => {
                setSelectedMap({});
                form.setData('reference', '');
            },
        });
    }

    return (
        <StaffLayout
            breadcrumbs={[
                { title: 'Keuangan', href: CashierController.index.url() },
                {
                    title: 'Kasir pembayaran',
                    href: CashierController.index.url(),
                },
            ]}
        >
            <Head title="Kasir pembayaran" />

            <div className="space-y-6">
                <PageHeader
                    title="Kasir pembayaran"
                    description="Terima pembayaran tunai atau transfer di loket, termasuk cicilan sebagian per tagihan."
                />

                <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    <div className="space-y-3 rounded-lg border border-border bg-card p-4 shadow-xs">
                        <Label htmlFor="cashier-student-search">
                            1. Pilih siswa
                        </Label>
                        <Input
                            id="cashier-student-search"
                            value={studentSearch}
                            onChange={(e) => setStudentSearch(e.target.value)}
                            placeholder="Cari nama atau NIS..."
                        />
                        <div className="max-h-72 divide-y divide-border overflow-y-auto rounded-md border border-border">
                            {filteredStudents.map((s) => {
                                const isActive = selectedStudent?.id === s.id;

                                return (
                                    <button
                                        key={s.id}
                                        type="button"
                                        onClick={() => selectStudent(s.id)}
                                        className={`flex w-full items-center justify-between px-3 py-2 text-left text-sm transition-colors hover:bg-muted/60 ${
                                            isActive
                                                ? 'bg-primary/10 font-medium text-primary'
                                                : ''
                                        }`}
                                    >
                                        <span>{s.name}</span>
                                        <span className="text-xs text-muted-foreground tabular-nums">
                                            {s.nis} · {s.unit_code}
                                        </span>
                                    </button>
                                );
                            })}
                        </div>
                    </div>

                    <div className="space-y-4 lg:col-span-2">
                        {!selectedStudent ? (
                            <EmptyState
                                title="Pilih siswa terlebih dahulu"
                                description="Cari dan klik nama siswa di sebelah kiri untuk menampilkan daftar tagihan terbukanya."
                            />
                        ) : openInvoices.length === 0 ? (
                            <EmptyState
                                title={`Tidak ada tagihan terbuka untuk ${selectedStudent.name}`}
                                description="Semua tagihan siswa ini sudah lunas atau belum ada tagihan yang diterbitkan."
                            />
                        ) : (
                            <form
                                onSubmit={submitPayment}
                                className="space-y-4 rounded-lg border border-border bg-card p-5 shadow-xs"
                                noValidate
                            >
                                <div className="flex flex-wrap items-center justify-between gap-2 border-b border-border pb-3">
                                    <div>
                                        <div className="font-semibold text-foreground">
                                            {selectedStudent.name}
                                        </div>
                                        <div className="text-xs text-muted-foreground tabular-nums">
                                            NIS {selectedStudent.nis} ·{' '}
                                            {selectedStudent.unit_name}
                                        </div>
                                    </div>
                                    <div className="text-right">
                                        <div className="text-xs text-muted-foreground">
                                            Total dibayar
                                        </div>
                                        <div className="text-lg font-semibold text-primary">
                                            <MoneyText amount={totalSelected} />
                                        </div>
                                    </div>
                                </div>

                                <div className="divide-y divide-border rounded-md border border-border">
                                    {openInvoices.map((inv) => {
                                        const isChecked =
                                            selectedMap[inv.id] !== undefined;

                                        return (
                                            <div
                                                key={inv.id}
                                                className="flex flex-col gap-3 p-3 sm:flex-row sm:items-center sm:justify-between"
                                            >
                                                <label className="flex cursor-pointer items-start gap-3">
                                                    <Checkbox
                                                        checked={isChecked}
                                                        onCheckedChange={(
                                                            val,
                                                        ) =>
                                                            toggleInvoice(
                                                                inv,
                                                                val === true,
                                                            )
                                                        }
                                                    />
                                                    <div>
                                                        <div className="font-medium text-foreground">
                                                            {inv.description}
                                                        </div>
                                                        <div className="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                                                            <span>
                                                                Jatuh tempo:{' '}
                                                                <DateText
                                                                    value={
                                                                        inv.due_date
                                                                    }
                                                                />
                                                            </span>
                                                            <span>·</span>
                                                            <span>
                                                                Sisa:{' '}
                                                                <MoneyText
                                                                    amount={
                                                                        inv.remaining
                                                                    }
                                                                />
                                                            </span>
                                                            {inv.is_overdue && (
                                                                <StatusBadge variant="warning">
                                                                    Menunggak
                                                                </StatusBadge>
                                                            )}
                                                        </div>
                                                    </div>
                                                </label>

                                                <div className="w-full sm:w-40">
                                                    <Input
                                                        type="number"
                                                        min={1}
                                                        max={inv.remaining}
                                                        disabled={!isChecked}
                                                        aria-label={`Nominal bayar ${inv.description}`}
                                                        value={
                                                            isChecked
                                                                ? selectedMap[
                                                                      inv.id
                                                                  ]
                                                                : ''
                                                        }
                                                        onChange={(e) =>
                                                            setSelectedMap(
                                                                (prev) => ({
                                                                    ...prev,
                                                                    [inv.id]:
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
                                                        className="text-right tabular-nums"
                                                    />
                                                </div>
                                            </div>
                                        );
                                    })}
                                </div>

                                <InputError message={form.errors.allocations} />

                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <div className="space-y-1.5">
                                        <Label htmlFor="cashier-method">
                                            Metode pembayaran
                                        </Label>
                                        <Select
                                            value={form.data.method}
                                            onValueChange={(v) =>
                                                form.setData('method', v)
                                            }
                                        >
                                            <SelectTrigger id="cashier-method">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {methods.map((m) => (
                                                    <SelectItem
                                                        key={m.value}
                                                        value={m.value}
                                                    >
                                                        {m.label}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        <InputError
                                            message={form.errors.method}
                                        />
                                    </div>

                                    <div className="space-y-1.5">
                                        <Label htmlFor="cashier-reference">
                                            Referensi / catatan (opsional)
                                        </Label>
                                        <Input
                                            id="cashier-reference"
                                            value={form.data.reference}
                                            onChange={(e) =>
                                                form.setData(
                                                    'reference',
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="Mis. No. mutasi bank"
                                            maxLength={120}
                                        />
                                        <InputError
                                            message={form.errors.reference}
                                        />
                                    </div>
                                </div>

                                <div className="flex justify-end">
                                    <Button
                                        type="submit"
                                        disabled={
                                            form.processing ||
                                            totalSelected <= 0
                                        }
                                    >
                                        <Banknote
                                            className="size-4"
                                            strokeWidth={1.75}
                                        />
                                        {form.processing
                                            ? 'Menyimpan...'
                                            : 'Simpan pembayaran'}
                                    </Button>
                                </div>
                            </form>
                        )}
                    </div>
                </div>

                {recentPayments.length > 0 && (
                    <div className="space-y-3">
                        <h2 className="text-sm font-semibold text-foreground">
                            Pembayaran terbaru
                        </h2>
                        <div className="overflow-x-auto rounded-lg border border-border bg-card shadow-xs">
                            <table className="w-full text-sm">
                                <thead className="border-b border-border bg-muted/50 text-xs text-muted-foreground">
                                    <tr>
                                        <th className="px-3 py-2 text-left font-medium">
                                            No. kuitansi
                                        </th>
                                        <th className="px-3 py-2 text-left font-medium">
                                            Siswa
                                        </th>
                                        <th className="px-3 py-2 text-left font-medium">
                                            Metode
                                        </th>
                                        <th className="px-3 py-2 text-right font-medium">
                                            Nominal
                                        </th>
                                        <th className="px-3 py-2 text-left font-medium">
                                            Petugas
                                        </th>
                                        <th className="px-3 py-2 text-left font-medium">
                                            Waktu
                                        </th>
                                        <th className="px-3 py-2 text-center font-medium">
                                            Aksi
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border">
                                    {recentPayments.map((p) => (
                                        <tr key={p.id}>
                                            <td className="px-3 py-2 font-medium tabular-nums">
                                                <span className="inline-flex items-center gap-1.5">
                                                    <CheckCircle2
                                                        className="size-3.5 text-primary"
                                                        strokeWidth={1.75}
                                                    />
                                                    {p.receipt_number}
                                                    {p.is_reversed && (
                                                        <StatusBadge variant="muted">
                                                            Dibalik
                                                        </StatusBadge>
                                                    )}
                                                </span>
                                            </td>
                                            <td className="px-3 py-2">
                                                {p.student_name}{' '}
                                                <span className="text-xs text-muted-foreground tabular-nums">
                                                    ({p.student_nis})
                                                </span>
                                            </td>
                                            <td className="px-3 py-2 text-muted-foreground">
                                                {p.method_label}
                                            </td>
                                            <td className="px-3 py-2 text-right font-medium">
                                                <MoneyText amount={p.amount} />
                                            </td>
                                            <td className="px-3 py-2 text-muted-foreground">
                                                {p.cashier_name}
                                            </td>
                                            <td className="px-3 py-2 text-muted-foreground">
                                                <DateText
                                                    value={p.paid_at}
                                                    mode="datetime"
                                                />
                                            </td>
                                            <td className="px-3 py-2 text-center">
                                                <div className="inline-flex items-center justify-center gap-1">
                                                    <Tooltip>
                                                        <TooltipTrigger asChild>
                                                            <Button
                                                                variant="ghost"
                                                                size="icon"
                                                                asChild
                                                                aria-label={`Unduh kuitansi ${p.receipt_number}`}
                                                            >
                                                                <a
                                                                    href={ReceiptController.staff.url(
                                                                        p.id,
                                                                    )}
                                                                    target="_blank"
                                                                    rel="noreferrer"
                                                                >
                                                                    <FileDown
                                                                        className="size-4"
                                                                        strokeWidth={
                                                                            1.75
                                                                        }
                                                                    />
                                                                </a>
                                                            </Button>
                                                        </TooltipTrigger>
                                                        <TooltipContent>
                                                            Unduh kuitansi PDF
                                                        </TooltipContent>
                                                    </Tooltip>
                                                    {canReverse &&
                                                        !p.is_reversed &&
                                                        p.method !==
                                                            'midtrans' && (
                                                            <Tooltip>
                                                                <TooltipTrigger
                                                                    asChild
                                                                >
                                                                    <Button
                                                                        type="button"
                                                                        variant="ghost"
                                                                        size="icon"
                                                                        aria-label={`Balik pembayaran ${p.receipt_number}`}
                                                                        onClick={() => {
                                                                            reverseForm.clearErrors();
                                                                            reverseForm.reset();
                                                                            setReversing(
                                                                                p,
                                                                            );
                                                                        }}
                                                                    >
                                                                        <RotateCcw
                                                                            className="size-4 text-destructive"
                                                                            strokeWidth={
                                                                                1.75
                                                                            }
                                                                        />
                                                                    </Button>
                                                                </TooltipTrigger>
                                                                <TooltipContent>
                                                                    Balik
                                                                    pembayaran
                                                                </TooltipContent>
                                                            </Tooltip>
                                                        )}
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}
            </div>

            <FormDialog
                open={reversing !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setReversing(null);
                    }
                }}
                title={`Balik pembayaran ${reversing?.receipt_number ?? ''}?`}
                description="Alokasi pembayaran akan dibalik dan status tagihan siswa akan dihitung ulang."
                onSubmit={submitReverse}
                processing={reverseForm.processing}
                submitLabel="Balik pembayaran"
            >
                <div className="space-y-1.5">
                    <Label htmlFor="reverse-reason">Alasan pembalikan</Label>
                    <Input
                        id="reverse-reason"
                        value={reverseForm.data.reason}
                        onChange={(e) =>
                            reverseForm.setData('reason', e.target.value)
                        }
                        placeholder="Mis. Salah input nominal atau transfer gagal"
                        maxLength={255}
                        required
                    />
                    <InputError message={reverseForm.errors.reason} />
                </div>
            </FormDialog>
        </StaffLayout>
    );
}

CashierIndex.layout = (page: ReactNode) => page;
