import { Printer } from 'lucide-react';
import CanteenCatalogController from '@/actions/App/Http/Controllers/Admin/CanteenCatalogController';
import type { MasterOption } from '@/components/app/master-crud-page';
import {
    MasterCrudPage,
    masterLayout,
} from '@/components/app/master-crud-page';
import { MoneyText } from '@/components/app/money-text';
import { StatusBadge } from '@/components/app/status-badge';
import { Button } from '@/components/ui/button';
import type { PaginatedResource } from '@/types';

type Row = {
    id: number;
    unit_name: string;
    category_id: number;
    category_name: string;
    name: string;
    price: number;
    stock: number | null;
    is_active: boolean;
};

type Props = {
    rows: PaginatedResource<Row>;
    filters: { search: string; unit: number | null; category: number | null };
    unitOptions: MasterOption[];
    categoryOptions: MasterOption[];
};

export default function CanteenProducts({
    rows,
    filters,
    unitOptions,
    categoryOptions,
}: Props) {
    return (
        <MasterCrudPage<Row>
            title="Daftar Menu"
            description="Menu makanan, minuman, dan snack kantin dengan harga jual dan stok porsi. Menu aktif tampil di Kasir Kantin."
            group="Kantin"
            itemNoun="menu"
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
                    key: 'category',
                    label: 'Saring kategori',
                    allLabel: 'Semua kategori',
                    options: categoryOptions,
                },
            ]}
            searchPlaceholder="Cari menu"
            headerActions={
                <Button
                    type="button"
                    variant="soft"
                    onClick={() => window.print()}
                >
                    <Printer className="size-4" strokeWidth={1.75} />
                    Cetak menu
                </Button>
            }
            urls={{
                index: CanteenCatalogController.products.url(),
                store: CanteenCatalogController.storeProduct.url(),
                update: (id) => CanteenCatalogController.updateProduct.url(id),
                destroy: (id) =>
                    CanteenCatalogController.destroyProduct.url(id),
            }}
            columns={[
                {
                    key: 'name',
                    label: 'Nama menu',
                    render: (r) => (
                        <span className="font-medium">{r.name}</span>
                    ),
                },
                {
                    key: 'category',
                    label: 'Kategori',
                    render: (r) => r.category_name,
                },
                {
                    key: 'price',
                    label: 'Harga jual',
                    align: 'right',
                    render: (r) => <MoneyText amount={r.price} />,
                },
                {
                    key: 'stock',
                    label: 'Stok',
                    align: 'center',
                    className: 'tabular-nums',
                    render: (r) =>
                        r.stock === null ? (
                            <span className="text-muted-foreground">
                                Tidak dihitung
                            </span>
                        ) : r.stock === 0 ? (
                            <StatusBadge variant="destructive">
                                Habis
                            </StatusBadge>
                        ) : (
                            r.stock
                        ),
                },
                {
                    key: 'status',
                    label: 'Status',
                    className: 'hidden @3xl:table-cell',
                    render: (r) =>
                        r.is_active ? (
                            <StatusBadge variant="success">Dijual</StatusBadge>
                        ) : (
                            <StatusBadge variant="muted">
                                Disembunyikan
                            </StatusBadge>
                        ),
                },
                {
                    key: 'unit',
                    label: 'Unit',
                    className: 'hidden @5xl:table-cell',
                    render: (r) => r.unit_name,
                },
            ]}
            fields={[
                {
                    key: 'product_category_id',
                    label: 'Kategori menu',
                    type: 'select',
                    options: categoryOptions,
                    wide: true,
                },
                {
                    key: 'name',
                    label: 'Nama makanan / minuman',
                    required: true,
                    wide: true,
                },
                {
                    key: 'price',
                    label: 'Harga jual (Rp)',
                    type: 'number',
                    required: true,
                },
                {
                    key: 'stock',
                    label: 'Stok porsi (kosongkan bila tidak dihitung)',
                    type: 'number',
                },
                {
                    key: 'is_active',
                    label: 'Status',
                    type: 'select',
                    options: [
                        { value: '1', label: 'Dijual di kasir' },
                        { value: '0', label: 'Disembunyikan' },
                    ],
                },
            ]}
            defaults={{
                product_category_id: '',
                name: '',
                price: '',
                stock: '',
                is_active: '1',
            }}
            toForm={(r) => ({
                product_category_id: String(r.category_id),
                name: r.name,
                price: String(r.price),
                stock: r.stock === null ? '' : String(r.stock),
                is_active: r.is_active ? '1' : '0',
            })}
            emptyDescription="Tambahkan kategori di Kategori Menu, lalu daftarkan menu beserta harganya."
        />
    );
}

CanteenProducts.layout = masterLayout;
