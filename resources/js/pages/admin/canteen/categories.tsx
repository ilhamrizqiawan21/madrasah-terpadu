import CanteenCatalogController from '@/actions/App/Http/Controllers/Admin/CanteenCatalogController';
import type { MasterOption } from '@/components/app/master-crud-page';
import {
    MasterCrudPage,
    masterLayout,
} from '@/components/app/master-crud-page';
import type { PaginatedResource } from '@/types';

type Row = {
    id: number;
    unit_id: number;
    unit_name: string;
    module: string;
    module_label: string;
    name: string;
    products_count: number;
};

type Props = {
    rows: PaginatedResource<Row>;
    filters: { search: string; unit: number | null; module: string | null };
    unitOptions: MasterOption[];
    moduleOptions: MasterOption[];
};

export default function CanteenCategories({
    rows,
    filters,
    unitOptions,
    moduleOptions,
}: Props) {
    return (
        <MasterCrudPage<Row>
            title="Kategori Menu"
            description="Kategori produk kantin dan koperasi sekolah per unit."
            group="Kantin"
            itemNoun="kategori"
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
                    key: 'module',
                    label: 'Saring peruntukan',
                    allLabel: 'Kantin & koperasi',
                    options: moduleOptions,
                },
            ]}
            searchPlaceholder="Cari kategori"
            urls={{
                index: CanteenCatalogController.categories.url(),
                store: CanteenCatalogController.storeCategory.url(),
                update: (id) => CanteenCatalogController.updateCategory.url(id),
                destroy: (id) =>
                    CanteenCatalogController.destroyCategory.url(id),
            }}
            columns={[
                {
                    key: 'name',
                    label: 'Nama kategori',
                    render: (r) => (
                        <span className="font-medium">{r.name}</span>
                    ),
                },
                {
                    key: 'module',
                    label: 'Peruntukan',
                    render: (r) => r.module_label,
                },
                {
                    key: 'products',
                    label: 'Jumlah menu',
                    align: 'center',
                    className: 'tabular-nums',
                    render: (r) => r.products_count,
                },
                {
                    key: 'unit',
                    label: 'Unit',
                    className: 'hidden @3xl:table-cell',
                    render: (r) => r.unit_name,
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
                    key: 'module',
                    label: 'Peruntukan',
                    type: 'select',
                    options: moduleOptions,
                },
                {
                    key: 'name',
                    label: 'Nama kategori',
                    required: true,
                    placeholder: 'Mis. Minuman dingin',
                    wide: true,
                },
            ]}
            defaults={{
                unit_id: unitOptions[0]?.value ?? '',
                module: 'canteen',
                name: '',
            }}
            toForm={(r) => ({
                unit_id: String(r.unit_id),
                module: r.module,
                name: r.name,
            })}
            emptyDescription="Tambahkan kategori seperti Makanan berat, Minuman, atau Snack."
        />
    );
}

CanteenCategories.layout = masterLayout;
