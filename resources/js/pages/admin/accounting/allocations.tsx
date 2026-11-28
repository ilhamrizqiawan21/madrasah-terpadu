import { Head, router, useForm } from '@inertiajs/react';
import type { FormEvent, ReactNode } from 'react';
import { useState } from 'react';
import CashAccountController from '@/actions/App/Http/Controllers/Admin/Accounting/CashAccountController';
import { EmptyState } from '@/components/app/empty-state';
import { FormDialog } from '@/components/app/form-dialog';
import { PageHeader } from '@/components/app/page-header';
import { StatusBadge } from '@/components/app/status-badge';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import StaffLayout from '@/layouts/staff-layout';

type Option = { value: string; label: string };
type Expense = {
    id: number;
    name: string;
    allowed: { id: number; name: string }[];
};
type Income = { id: number; name: string };

type Props = {
    unitId: number;
    unitOptions: Option[];
    expenseAccounts: Expense[];
    incomeAccounts: Income[];
};

export default function CashAllocations({
    unitId,
    unitOptions,
    expenseAccounts,
    incomeAccounts,
}: Props) {
    const [editing, setEditing] = useState<Expense | null>(null);
    const form = useForm<{ income_ids: number[] }>({ income_ids: [] });

    const open = (e: Expense) => {
        form.clearErrors();
        form.setData({ income_ids: e.allowed.map((a) => a.id) });
        setEditing(e);
    };

    const submit = (ev: FormEvent<HTMLFormElement>) => {
        ev.preventDefault();
        if (!editing) {
            return;
        }
        form.put(CashAccountController.updateAllocation.url(editing.id), {
            preserveScroll: true,
            onSuccess: () => setEditing(null),
        });
    };

    return (
        <StaffLayout
            breadcrumbs={[
                { title: 'Akuntansi & Kas', href: '#' },
                {
                    title: 'Alokasi Dana',
                    href: CashAccountController.allocations.url(),
                },
            ]}
        >
            <Head title="Alokasi Dana" />

            <div className="@container space-y-4">
                <PageHeader
                    title="Alokasi Dana"
                    description="Atur sumber dana mana yang boleh membiayai setiap pos pengeluaran. Pos tanpa aturan boleh dibiayai sumber dana apa pun di unitnya."
                />

                <Select
                    value={String(unitId)}
                    onValueChange={(v) =>
                        router.get(
                            CashAccountController.allocations.url(),
                            { unit: v },
                            { preserveScroll: true, replace: true },
                        )
                    }
                >
                    <SelectTrigger
                        className="w-full @lg:w-64"
                        aria-label="Pilih unit"
                    >
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        {unitOptions.map((o) => (
                            <SelectItem key={o.value} value={o.value}>
                                {o.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>

                {expenseAccounts.length === 0 ? (
                    <EmptyState
                        title="Belum ada pos pengeluaran"
                        description="Tambahkan pos pengeluaran dan pendapatan di Kategori Akun, lalu atur alokasinya di sini."
                    />
                ) : (
                    <div className="overflow-x-auto rounded-xl border border-border bg-card shadow-xs">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b border-border bg-primary/[0.04] text-left text-xs text-muted-foreground">
                                    <th className="px-3 py-2 font-medium">
                                        Pos pengeluaran
                                    </th>
                                    <th className="px-3 py-2 font-medium">
                                        Sumber dana yang diizinkan
                                    </th>
                                    <th className="px-3 py-2 text-center font-medium">
                                        Aksi
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {expenseAccounts.map((e) => (
                                    <tr key={e.id}>
                                        <td className="px-3 py-2.5 font-medium">
                                            {e.name}
                                        </td>
                                        <td className="px-3 py-2.5">
                                            {e.allowed.length === 0 ? (
                                                <span className="text-muted-foreground">
                                                    Semua sumber dana
                                                </span>
                                            ) : (
                                                <div className="flex flex-wrap gap-1.5">
                                                    {e.allowed.map((a) => (
                                                        <StatusBadge
                                                            key={a.id}
                                                            variant="default"
                                                        >
                                                            {a.name}
                                                        </StatusBadge>
                                                    ))}
                                                </div>
                                            )}
                                        </td>
                                        <td className="px-3 py-2.5 text-center">
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                onClick={() => open(e)}
                                                disabled={
                                                    incomeAccounts.length === 0
                                                }
                                            >
                                                Atur
                                            </Button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>

            <FormDialog
                open={editing !== null}
                onOpenChange={(o) => !o && setEditing(null)}
                title={editing ? `Sumber dana untuk ${editing.name}` : ''}
                description="Centang pos pendapatan yang boleh membiayai pos ini. Kosongkan semua centang untuk mengizinkan semua sumber dana."
                onSubmit={submit}
                processing={form.processing}
                submitLabel="Terapkan aturan"
            >
                <fieldset className="grid gap-2 rounded-md border border-border p-3">
                    <legend className="sr-only">Pos pendapatan</legend>
                    {incomeAccounts.map((i) => (
                        <label
                            key={i.id}
                            className="flex items-center gap-2 text-sm"
                        >
                            <Checkbox
                                checked={form.data.income_ids.includes(i.id)}
                                onCheckedChange={(c) =>
                                    form.setData(
                                        'income_ids',
                                        c === true
                                            ? [...form.data.income_ids, i.id]
                                            : form.data.income_ids.filter(
                                                  (x) => x !== i.id,
                                              ),
                                    )
                                }
                            />
                            {i.name}
                        </label>
                    ))}
                </fieldset>
                <InputError message={form.errors.income_ids} />
            </FormDialog>
        </StaffLayout>
    );
}

CashAllocations.layout = (page: ReactNode) => page;
