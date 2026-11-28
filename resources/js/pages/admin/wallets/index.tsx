import { Head, router, useForm } from '@inertiajs/react';
import { FileText, Plus } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import WalletController from '@/actions/App/Http/Controllers/Admin/WalletController';
import type { DataTableColumn } from '@/components/app/data-table';
import { DataTable } from '@/components/app/data-table';
import { DateText } from '@/components/app/date-text';
import { FormDialog } from '@/components/app/form-dialog';
import { MoneyText } from '@/components/app/money-text';
import { PageHeader } from '@/components/app/page-header';
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
import StaffLayout from '@/layouts/staff-layout';
import { formatTime } from '@/lib/format';
import { dashboard } from '@/routes/admin';
import type { PaginatedResource } from '@/types';

type WalletRow = {
    id: number;
    student_id: number;
    nis: string;
    name: string;
    unit_code: string;
    balance: number;
    daily_limit: number;
    is_frozen: boolean;
};

type TopupRow = {
    id: number;
    student_name: string;
    student_nis: string;
    amount: number;
    balance_after: number;
    actor_name: string;
    created_at: string;
};

type Props = {
    wallets: PaginatedResource<WalletRow>;
    recentTopups: TopupRow[];
    students: { id: number; nis: string; name: string }[];
    filters: { search: string };
    limits: { min_top_up: number; max_top_up: number };
    canTopup: boolean;
};

export default function AdminWalletsIndex({
    wallets,
    recentTopups,
    students,
    filters,
    limits,
    canTopup,
}: Props) {
    const [search, setSearch] = useState(filters.search);
    const [loading, setLoading] = useState(false);
    const [topupOpen, setTopupOpen] = useState(false);

    const form = useForm({
        student_id: students[0] ? String(students[0].id) : '',
        amount: '50000',
        client_uuid: crypto.randomUUID(),
        note: '',
    });

    function visitFilters(nextSearch: string, page?: number) {
        const params: Record<string, string | number> = {};
        if (nextSearch.trim() !== '') {
            params.search = nextSearch.trim();
        }
        if (page && page > 1) {
            params.page = page;
        }
        router.get(WalletController.index(), params, {
            preserveScroll: true,
            preserveState: true,
            onStart: () => setLoading(true),
            onFinish: () => setLoading(false),
        });
    }

    function openTopup(studentId?: number) {
        form.clearErrors();
        form.setData({
            student_id: studentId
                ? String(studentId)
                : students[0]
                  ? String(students[0].id)
                  : '',
            amount: '50000',
            client_uuid: crypto.randomUUID(),
            note: '',
        });
        setTopupOpen(true);
    }

    function submitTopup(e: FormEvent) {
        e.preventDefault();
        form.post(WalletController.topUp().url, {
            preserveScroll: true,
            onSuccess: () => setTopupOpen(false),
        });
    }

    const columns: DataTableColumn<WalletRow>[] = [
        {
            key: 'student',
            label: 'Siswa',
            render: (row) => (
                <div>
                    <div className="font-medium">{row.name}</div>
                    <div className="text-xs text-muted-foreground">
                        {row.nis} · {row.unit_code}
                    </div>
                </div>
            ),
        },
        {
            key: 'balance',
            label: 'Saldo',
            align: 'right',
            render: (row) => (
                <span className="font-semibold">
                    <MoneyText amount={row.balance} />
                </span>
            ),
        },
        {
            key: 'daily_limit',
            label: 'Limit harian',
            align: 'right',
            render: (row) => <MoneyText amount={row.daily_limit} />,
        },
        {
            key: 'status',
            label: 'Status',
            align: 'center',
            render: (row) => (
                <StatusBadge
                    variant={row.is_frozen ? 'destructive' : 'success'}
                >
                    {row.is_frozen ? 'Dibekukan' : 'Aktif'}
                </StatusBadge>
            ),
        },
        ...(canTopup
            ? [
                  {
                      key: 'actions',
                      label: 'Aksi',
                      align: 'center' as const,
                      render: (row: WalletRow) => (
                          <Button
                              type="button"
                              size="sm"
                              variant="outline"
                              onClick={() => openTopup(row.student_id)}
                          >
                              Isi saldo
                          </Button>
                      ),
                  },
              ]
            : []),
    ];

    return (
        <StaffLayout
            breadcrumbs={[
                { title: 'Beranda', href: dashboard() },
                { title: 'Saldo & isi saldo', href: WalletController.index() },
            ]}
        >
            <Head title="Saldo & isi saldo kantin" />

            <div className="space-y-6">
                <PageHeader
                    title="Saldo & isi saldo kantin"
                    description="Kelola saldo dompet kantin siswa dan isi saldo tunai di loket."
                    actions={
                        canTopup && (
                            <Button onClick={() => openTopup()}>
                                <Plus className="size-4" />
                                Isi saldo tunai
                            </Button>
                        )
                    }
                />

                <form
                    className="flex flex-wrap items-center gap-2"
                    onSubmit={(e) => {
                        e.preventDefault();
                        visitFilters(search);
                    }}
                >
                    <Input
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Cari nama atau NIS siswa..."
                        className="w-64"
                    />
                    <Button type="submit" variant="outline" size="sm">
                        Cari
                    </Button>
                </form>

                <DataTable
                    columns={columns}
                    data={wallets.data}
                    rowKey={(row) => row.id}
                    loading={loading}
                    pagination={{
                        currentPage: wallets.meta.current_page,
                        lastPage: wallets.meta.last_page,
                        total: wallets.meta.total,
                        perPage: wallets.meta.per_page,
                        from: wallets.meta.from,
                        to: wallets.meta.to,
                    }}
                    onPageChange={(page) => visitFilters(search, page)}
                    empty={{
                        title: 'Belum ada dompet siswa',
                        description:
                            'Dompet dibuat otomatis saat pengisian saldo pertama atau saat kartu dipindai di kantin.',
                    }}
                />

                {recentTopups.length > 0 && (
                    <section className="rounded-xl border border-border bg-card p-5">
                        <h2 className="text-sm font-semibold">
                            Riwayat isi saldo terbaru
                        </h2>
                        <div className="mt-3 overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead className="text-xs text-muted-foreground">
                                    <tr className="border-b border-border">
                                        <th className="py-2 text-left font-medium">
                                            Siswa
                                        </th>
                                        <th className="px-2 py-2 text-right font-medium">
                                            Nominal
                                        </th>
                                        <th className="px-2 py-2 text-right font-medium">
                                            Saldo akhir
                                        </th>
                                        <th className="px-2 py-2 text-left font-medium">
                                            Petugas
                                        </th>
                                        <th className="px-2 py-2 text-left font-medium">
                                            Waktu
                                        </th>
                                        <th className="py-2 text-right font-medium">
                                            Kuitansi
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border">
                                    {recentTopups.map((row) => (
                                        <tr key={row.id}>
                                            <td className="py-2">
                                                <div className="font-medium">
                                                    {row.student_name}
                                                </div>
                                                <div className="text-xs text-muted-foreground">
                                                    {row.student_nis}
                                                </div>
                                            </td>
                                            <td className="px-2 py-2 text-right font-medium text-primary">
                                                <MoneyText
                                                    amount={row.amount}
                                                />
                                            </td>
                                            <td className="px-2 py-2 text-right">
                                                <MoneyText
                                                    amount={row.balance_after}
                                                />
                                            </td>
                                            <td className="px-2 py-2 text-xs text-muted-foreground">
                                                {row.actor_name}
                                            </td>
                                            <td className="px-2 py-2 text-xs text-muted-foreground">
                                                <DateText
                                                    value={row.created_at}
                                                />{' '}
                                                {formatTime(row.created_at)}
                                            </td>
                                            <td className="py-2 text-right">
                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                    asChild
                                                >
                                                    <a
                                                        href={WalletController.receipt.url(
                                                            { entry: row.id },
                                                        )}
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                    >
                                                        <FileText className="size-3.5" />
                                                        PDF
                                                    </a>
                                                </Button>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </section>
                )}
            </div>

            <FormDialog
                open={topupOpen}
                onOpenChange={setTopupOpen}
                title="Isi saldo tunai dompet"
                onSubmit={submitTopup}
                processing={form.processing}
                submitLabel="Simpan isi saldo"
            >
                <div className="space-y-4">
                    <div className="space-y-1.5">
                        <Label htmlFor="topup-student">Siswa</Label>
                        <Select
                            value={form.data.student_id}
                            onValueChange={(v) => form.setData('student_id', v)}
                        >
                            <SelectTrigger id="topup-student">
                                <SelectValue placeholder="Pilih siswa" />
                            </SelectTrigger>
                            <SelectContent>
                                {students.map((s) => (
                                    <SelectItem key={s.id} value={String(s.id)}>
                                        {s.nis} — {s.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={form.errors.student_id} />
                    </div>

                    <div className="space-y-1.5">
                        <Label htmlFor="topup-amount">
                            Nominal (Rp{' '}
                            {limits.min_top_up.toLocaleString('id-ID')} – Rp{' '}
                            {limits.max_top_up.toLocaleString('id-ID')})
                        </Label>
                        <Input
                            id="topup-amount"
                            type="number"
                            value={form.data.amount}
                            onChange={(e) =>
                                form.setData('amount', e.target.value)
                            }
                        />
                        <InputError message={form.errors.amount} />
                    </div>

                    <div className="space-y-1.5">
                        <Label htmlFor="topup-note">Catatan (opsional)</Label>
                        <Input
                            id="topup-note"
                            value={form.data.note}
                            onChange={(e) =>
                                form.setData('note', e.target.value)
                            }
                            placeholder="Mis. Titipan wali"
                        />
                        <InputError message={form.errors.note} />
                    </div>
                </div>
            </FormDialog>
        </StaffLayout>
    );
}
