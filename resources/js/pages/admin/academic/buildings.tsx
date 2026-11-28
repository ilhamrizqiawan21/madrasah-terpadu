import FacilityController from '@/actions/App/Http/Controllers/Admin/FacilityController';
import type { MasterOption } from '@/components/app/master-crud-page';
import {
    MasterCrudPage,
    masterLayout,
} from '@/components/app/master-crud-page';
import type { PaginatedResource } from '@/types';

type Building = {
    id: number;
    unit_id: number;
    unit_name: string;
    code: string;
    name: string;
    rooms_count: number;
};

type Props = {
    buildings: PaginatedResource<Building>;
    filters: { search: string; unit: number | null };
    unitOptions: MasterOption[];
};

export default function Buildings({ buildings, filters, unitOptions }: Props) {
    return (
        <MasterCrudPage<Building>
            title="Gedung"
            description="Arsip gedung dan bangunan sekolah per unit."
            group="Akademik"
            itemNoun="gedung"
            rows={buildings}
            filters={filters}
            filterSelects={[
                {
                    key: 'unit',
                    label: 'Saring unit',
                    allLabel: 'Semua unit',
                    options: unitOptions,
                },
            ]}
            searchPlaceholder="Cari kode atau nama gedung"
            urls={{
                index: FacilityController.buildings.url(),
                store: FacilityController.storeBuilding.url(),
                update: (id) => FacilityController.updateBuilding.url(id),
                destroy: (id) => FacilityController.destroyBuilding.url(id),
            }}
            columns={[
                {
                    key: 'code',
                    label: 'Kode',
                    render: (b) => (
                        <span className="font-medium tabular-nums">
                            {b.code}
                        </span>
                    ),
                },
                { key: 'name', label: 'Nama gedung', render: (b) => b.name },
                {
                    key: 'unit',
                    label: 'Unit',
                    className: 'hidden @2xl:table-cell',
                    render: (b) => b.unit_name,
                },
                {
                    key: 'rooms',
                    label: 'Ruangan',
                    align: 'right',
                    className: 'hidden @3xl:table-cell',
                    render: (b) => b.rooms_count,
                },
            ]}
            fields={[
                {
                    key: 'unit_id',
                    label: 'Unit',
                    type: 'select',
                    options: unitOptions,
                    wide: true,
                },
                {
                    key: 'code',
                    label: 'Kode gedung',
                    required: true,
                    placeholder: 'Mis. GD-A',
                },
                {
                    key: 'name',
                    label: 'Nama gedung / bangunan',
                    required: true,
                },
            ]}
            defaults={{
                unit_id: unitOptions[0]?.value ?? '',
                code: '',
                name: '',
            }}
            toForm={(b) => ({
                unit_id: String(b.unit_id),
                code: b.code,
                name: b.name,
            })}
            emptyDescription="Tambahkan gedung agar ruangan kelas dan laboratorium bisa didata."
        />
    );
}

Buildings.layout = masterLayout;
