import FacilityController from '@/actions/App/Http/Controllers/Admin/FacilityController';
import type { MasterOption } from '@/components/app/master-crud-page';
import {
    MasterCrudPage,
    masterLayout,
} from '@/components/app/master-crud-page';
import type { PaginatedResource } from '@/types';

type Room = {
    id: number;
    building_id: number;
    building_label: string;
    code: string;
    name: string;
    capacity: number;
};

type Props = {
    rooms: PaginatedResource<Room>;
    filters: { search: string; building: number | null };
    buildingOptions: MasterOption[];
};

export default function Rooms({ rooms, filters, buildingOptions }: Props) {
    return (
        <MasterCrudPage<Room>
            title="Ruangan & Lab"
            description="Ruang kelas, laboratorium, dan fasilitas per gedung."
            group="Akademik"
            itemNoun="ruangan"
            rows={rooms}
            filters={filters}
            filterSelects={[
                {
                    key: 'building',
                    label: 'Saring gedung',
                    allLabel: 'Semua gedung',
                    options: buildingOptions,
                },
            ]}
            searchPlaceholder="Cari kode atau nama ruangan"
            urls={{
                index: FacilityController.rooms.url(),
                store: FacilityController.storeRoom.url(),
                update: (id) => FacilityController.updateRoom.url(id),
                destroy: (id) => FacilityController.destroyRoom.url(id),
            }}
            columns={[
                {
                    key: 'name',
                    label: 'Nama ruangan',
                    render: (r) => (
                        <div>
                            <div className="font-medium">{r.name}</div>
                            <div className="text-xs text-muted-foreground tabular-nums">
                                {r.code}
                            </div>
                        </div>
                    ),
                },
                {
                    key: 'building',
                    label: 'Gedung',
                    className: 'hidden @2xl:table-cell',
                    render: (r) => r.building_label,
                },
                {
                    key: 'capacity',
                    label: 'Kapasitas',
                    align: 'right',
                    render: (r) => `${r.capacity} kursi`,
                },
            ]}
            fields={[
                {
                    key: 'building_id',
                    label: 'Gedung induk',
                    type: 'select',
                    options: buildingOptions,
                    wide: true,
                },
                {
                    key: 'code',
                    label: 'Kode ruang',
                    required: true,
                    placeholder: 'Mis. R-101',
                },
                {
                    key: 'name',
                    label: 'Nama ruangan / fasilitas',
                    required: true,
                },
                {
                    key: 'capacity',
                    label: 'Kapasitas kursi',
                    type: 'number',
                    required: true,
                },
            ]}
            defaults={{
                building_id: buildingOptions[0]?.value ?? '',
                code: '',
                name: '',
                capacity: '30',
            }}
            toForm={(r) => ({
                building_id: String(r.building_id),
                code: r.code,
                name: r.name,
                capacity: String(r.capacity),
            })}
            emptyDescription={
                buildingOptions.length === 0
                    ? 'Tambahkan gedung dulu di menu Gedung.'
                    : 'Tambahkan ruang kelas atau laboratorium.'
            }
        />
    );
}

Rooms.layout = masterLayout;
