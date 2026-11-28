import CashAccountController from '@/actions/App/Http/Controllers/Admin/Accounting/CashAccountController';
import { ExportButtons } from '@/components/app/export-buttons';
import type { MasterOption } from '@/components/app/master-crud-page';
import {
    MasterCrudPage,
    masterLayout,
} from '@/components/app/master-crud-page';
import { MoneyText } from '@/components/app/money-text';
import { StatusBadge } from '@/components/app/status-badge';
import type { PaginatedResource } from '@/types';

type Row = {
    id: number;
    unit_id: number;
    unit_name: string;
    code: string | null;
    name: string;
    direction: 'in' | 'out';
    is_active: boolean;
    is_system: boolean;
    balance: number | null;
    used: boolean;
};

type Props = {
    rows: PaginatedResource<Row>;
    filters: { search: string; unit: number | null; direction: string | null };
    unitOptions: MasterOption[];
    directionOptions: MasterOption[];
};

export default function CashAccounts({
    rows,
    filters,
    unitOptions,
    directionOptions,
}: Props) {
    const pdfQuery = filters.unit === null ? '' : `?unit=${filters.unit}`;

    return (
        <MasterCrudPage<Row>
            title="Kategori Akun"
            description="Bagan pos akun (COA) buku kas per unit. Pos pendapatan sekaligus menjadi sumber dana untuk membiayai pengeluaran."
            group="Akuntansi & Kas"
            itemNoun="pos akun"
            rows={rows}
            filters={filters}
            filterSelects={[
                {
                    key: 'unit',
                    label: 'Saring unit',
                    allLabel: 'Semua unit',
                    options: unitOptions,
                },
                {
                    key: 'direction',
                    label: 'Saring jenis aliran',
                    allLabel: 'Pendapatan & pengeluaran',
                    options: directionOptions,
                },
            ]}
            searchPlaceholder="Cari kode atau nama pos"
            headerActions={
                <ExportButtons
                    pdfHref={`${CashAccountController.exportPdf.url()}${pdfQuery}`}
                />
            }
            urls={{
                index: CashAccountController.index.url(),
                store: CashAccountController.store.url(),
                update: (id) => CashAccountController.update.url(id),
                destroy: (id) => CashAccountController.destroy.url(id),
            }}
            columns={[
                {
                    key: 'code',
                    label: 'Kode',
                    className: 'tabular-nums whitespace-nowrap',
                    render: (r) => r.code ?? '—',
                },
                {
                    key: 'name',
                    label: 'Nama pos',
                    render: (r) => (
                        <span className="font-medium">
                            {r.name}
                            {r.is_system && (
                                <span className="ml-2 text-xs font-normal text-muted-foreground">
                                    (otomatis)
                                </span>
                            )}
                        </span>
                    ),
                },
                {
                    key: 'direction',
                    label: 'Jenis aliran',
                    render: (r) =>
                        r.direction === 'in' ? (
                            <StatusBadge variant="success">
                                Pendapatan
                            </StatusBadge>
                        ) : (
                            <StatusBadge variant="attention">
                                Pengeluaran
                            </StatusBadge>
                        ),
                },
                {
                    key: 'balance',
                    label: 'Saldo sumber dana',
                    align: 'right',
                    render: (r) =>
                        r.balance === null ? (
                            <span className="text-muted-foreground">—</span>
                        ) : (
                            <MoneyText amount={r.balance} />
                        ),
                },
                {
                    key: 'unit',
                    label: 'Unit',
                    className: 'hidden @3xl:table-cell',
                    render: (r) => r.unit_name,
                },
                {
                    key: 'status',
                    label: 'Status',
                    className: 'hidden @3xl:table-cell',
                    render: (r) =>
                        r.is_active ? (
                            <StatusBadge variant="success">Aktif</StatusBadge>
                        ) : (
                            <StatusBadge variant="muted">Nonaktif</StatusBadge>
                        ),
                },
            ]}
            fields={[
                {
                    key: 'unit_id',
                    label: 'Unit',
                    type: 'select',
                    options: unitOptions,
                },
                {
                    key: 'direction',
                    label: 'Jenis aliran kas',
                    type: 'select',
                    options: directionOptions,
                },
                {
                    key: 'code',
                    label: 'Kode (opsional)',
                    placeholder: 'Mis. 4.1.01',
                },
                {
                    key: 'name',
                    label: 'Nama pos',
                    required: true,
                    placeholder: 'Mis. Biaya listrik, air & internet',
                },
                {
                    key: 'is_active',
                    label: 'Status',
                    type: 'select',
                    options: [
                        { value: '1', label: 'Aktif' },
                        {
                            value: '0',
                            label: 'Nonaktif (tidak bisa dipilih lagi)',
                        },
                    ],
                    wide: true,
                },
            ]}
            defaults={{
                unit_id: unitOptions[0]?.value ?? '',
                direction: 'out',
                code: '',
                name: '',
                is_active: '1',
            }}
            toForm={(r) => ({
                unit_id: String(r.unit_id),
                direction: r.direction,
                code: r.code ?? '',
                name: r.name,
                is_active: r.is_active ? '1' : '0',
            })}
            emptyDescription="Tambahkan pos pendapatan (mis. SPP, hibah, sewa) dan pos pengeluaran (mis. listrik, ATK, honor)."
        />
    );
}

CashAccounts.layout = masterLayout;
