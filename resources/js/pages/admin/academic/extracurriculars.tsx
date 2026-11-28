import ExtracurricularController from '@/actions/App/Http/Controllers/Admin/ExtracurricularController';
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
    name: string;
    coach_type: 'internal' | 'external';
    coach_user_id: number | null;
    coach_name: string | null;
    coach_label: string;
    members_count: number;
};

type Props = {
    rows: PaginatedResource<Row>;
    filters: { search: string; unit: number | null };
    unitOptions: MasterOption[];
    teacherOptions: MasterOption[];
};

export default function Extracurriculars({
    rows,
    filters,
    unitOptions,
    teacherOptions,
}: Props) {
    return (
        <MasterCrudPage<Row>
            title="Ekstrakurikuler"
            description="Daftar kegiatan ekstrakurikuler per unit beserta pembinanya (guru atau pelatih dari luar)."
            group="Akademik"
            itemNoun="ekskul"
            rows={rows}
            filters={filters}
            filterSelects={[
                {
                    key: 'unit',
                    label: 'Saring unit',
                    allLabel: 'Semua unit',
                    options: unitOptions,
                },
            ]}
            searchPlaceholder="Cari kegiatan atau pelatih"
            urls={{
                index: ExtracurricularController.index.url(),
                store: ExtracurricularController.store.url(),
                update: (id) => ExtracurricularController.update.url(id),
                destroy: (id) => ExtracurricularController.destroy.url(id),
            }}
            columns={[
                {
                    key: 'name',
                    label: 'Nama kegiatan',
                    render: (r) => (
                        <span className="font-medium">{r.name}</span>
                    ),
                },
                {
                    key: 'coach',
                    label: 'Pembina / pelatih',
                    render: (r) => r.coach_label,
                },
                {
                    key: 'members',
                    label: 'Anggota',
                    align: 'center',
                    className: 'tabular-nums',
                    render: (r) => r.members_count,
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
                    key: 'name',
                    label: 'Nama kegiatan',
                    required: true,
                    placeholder: 'Mis. Pramuka',
                },
                {
                    key: 'coach_type',
                    label: 'Status pembina',
                    type: 'select',
                    options: [
                        { value: 'internal', label: 'Guru internal' },
                        { value: 'external', label: 'Pelatih luar' },
                    ],
                },
                {
                    key: 'coach_user_id',
                    label: 'Guru pembina (bila internal)',
                    type: 'select',
                    options: teacherOptions,
                },
                {
                    key: 'coach_name',
                    label: 'Nama pelatih luar (bila eksternal)',
                    wide: true,
                },
            ]}
            defaults={{
                unit_id: unitOptions[0]?.value ?? '',
                name: '',
                coach_type: 'internal',
                coach_user_id: '',
                coach_name: '',
            }}
            toForm={(r) => ({
                unit_id: String(r.unit_id),
                name: r.name,
                coach_type: r.coach_type,
                coach_user_id:
                    r.coach_user_id === null ? '' : String(r.coach_user_id),
                coach_name: r.coach_name ?? '',
            })}
            emptyDescription="Tambahkan kegiatan seperti Pramuka, Tahfidz, atau Futsal."
        />
    );
}

Extracurriculars.layout = masterLayout;
